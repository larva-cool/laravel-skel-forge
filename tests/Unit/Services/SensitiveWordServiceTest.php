<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\SensitiveCategory;
use App\Enums\SensitiveLevel;
use App\Models\System\SensitiveWord;
use App\Services\SensitiveWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * SensitiveWordService 单元测试（DFA 算法 + 缓存）
 */
#[CoversClass(SensitiveWordService::class)]
#[Group('services')]
#[Group('sensitive')]
class SensitiveWordServiceTest extends TestCase
{
    use RefreshDatabase;

    private SensitiveWordService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = $this->app->make(SensitiveWordService::class);
    }

    /**
     * 添加测试词（加上 __test__ 前缀避免与种子数据冲突）
     */
    private function addWord(
        string $word,
        SensitiveLevel $level = SensitiveLevel::REVIEW,
        SensitiveCategory $cat = SensitiveCategory::OTHER
    ): void {
        $model = SensitiveWord::withTrashed()->firstOrNew(['word' => '__test__'.$word]);
        $model->fill([
            'category' => $cat,
            'level' => $level,
            'replacement' => '*',
            'hit_count' => 0,
            'status' => true,
        ]);
        $model->deleted_at = null;
        $model->save();
        $this->service->refreshDict();
    }

    /**
     * 用添加的测试词包装输入（输入中插入 __test__ 前缀与存储一致）
     */
    private function t(string $text): string
    {
        return str_replace(
            ['[BLOCK]', '[REVIEW]', '[REPLACE]', '[LONG]', '[STOP]'],
            ['__test__blocker', '__test__abuser', '__test__adword', '__test__fool', '__test__evil'],
            $text
        );
    }

    #[Test]
    #[TestDox('空文本返回通过结果')]
    public function empty_text_passes(): void
    {
        $result = $this->service->detect('');
        $this->assertTrue($result->passed);
        $this->assertSame([], $result->hits);
    }

    #[Test]
    #[TestDox('无敏感词文本原样返回')]
    public function clean_text_passes(): void
    {
        $this->addWord('blocker', SensitiveLevel::BLOCK);
        $result = $this->service->detect('今天天气真好');
        $this->assertTrue($result->passed);
        $this->assertSame([], $result->hits);
        $this->assertSame('今天天气真好', $result->filtered);
    }

    #[Test]
    #[TestDox('命中 BLOCK 级别词返回不通过')]
    public function block_level_word_blocks(): void
    {
        $this->addWord('blocker', SensitiveLevel::BLOCK, SensitiveCategory::POLITICAL);
        $result = $this->service->detect('我在练习__test__blocker');
        $this->assertTrue($result->isBlocked());
        $this->assertFalse($result->passed);
        $this->assertCount(1, $result->hits);
        $this->assertSame('__test__blocker', $result->hits[0]['word']);
    }

    #[Test]
    #[TestDox('命中 REVIEW 级别词通过但需人工审核')]
    public function review_level_word_needs_review(): void
    {
        $this->addWord('abuser', SensitiveLevel::REVIEW, SensitiveCategory::INSULT);
        $result = $this->service->detect('你是__test__abuser');
        $this->assertTrue($result->passed);
        $this->assertTrue($result->needsReview());
        $this->assertSame(SensitiveLevel::REVIEW, $result->maxLevel);
    }

    #[Test]
    #[TestDox('命中 REPLACE 级别词会被替换为掩码字符')]
    public function replace_level_word_gets_masked(): void
    {
        $this->addWord('adword', SensitiveLevel::REPLACE, SensitiveCategory::AD);
        $result = $this->service->detect('详情请__test__adword联系');
        $this->assertTrue($result->passed);
        $this->assertFalse($result->isBlocked());
        // __test__adword 共 12 字符，应全部替换为 *
        $this->assertStringContainsString('*', $result->filtered);
        $this->assertStringNotContainsString('__test__adword', $result->filtered);
    }

    #[Test]
    #[TestDox('英文敏感词默认忽略大小写')]
    public function case_insensitive_matching(): void
    {
        $this->addWord('ABC', SensitiveLevel::REVIEW, SensitiveCategory::INSULT);
        $r1 = $this->service->detect('you __test__abc');
        $r2 = $this->service->detect('you __test__ABC');
        $r3 = $this->service->detect('you __test__Abc');
        $this->assertCount(1, $r1->hits);
        $this->assertCount(1, $r2->hits);
        $this->assertCount(1, $r3->hits);
    }

    #[Test]
    #[TestDox('DFA 贪婪匹配最长词')]
    public function greedy_matches_longest(): void
    {
        // 先加短词再加长词
        $this->addWord('foo', SensitiveLevel::REPLACE, SensitiveCategory::INSULT);
        $this->addWord('fool', SensitiveLevel::REVIEW, SensitiveCategory::INSULT);
        $result = $this->service->detect('你是__test__fool吧');
        // 应匹配到"__test__fool"而不是"__test__foo"
        $this->assertCount(1, $result->hits);
        $this->assertSame('__test__fool', $result->hits[0]['word']);
    }

    #[Test]
    #[TestDox('干扰字符（空格/星号）会被跳过，防止绕过检测')]
    public function ignores_stop_chars_between_chars(): void
    {
        $this->addWord('evil', SensitiveLevel::BLOCK, SensitiveCategory::POLITICAL);
        // 在字符间插入空格和 *
        $text = '__t*e_s*t__evil';  // 干扰字符只作用于敏感词本身
        // 重建预期：直接在敏感词字符之间插入干扰字符
        $evils = '__test__evil';
        $withStops = implode(' *', preg_split('//u', $evils, -1, PREG_SPLIT_NO_EMPTY));
        $result = $this->service->detect('前缀 '.$withStops.' 后缀');
        $this->assertTrue($result->isBlocked());
        $this->assertCount(1, $result->hits);
    }

    #[Test]
    #[TestDox('同时命中多个词时取最高级别')]
    public function multiple_hits_returns_max_level(): void
    {
        $this->addWord('adword', SensitiveLevel::REPLACE, SensitiveCategory::AD);
        $this->addWord('blocker', SensitiveLevel::BLOCK, SensitiveCategory::POLITICAL);
        $result = $this->service->detect('请__test__adword联系__test__blocker');
        $this->assertTrue($result->isBlocked());
        $this->assertCount(2, $result->hits);
    }

    #[Test]
    #[TestDox('禁用状态的敏感词不参与检测')]
    public function disabled_word_is_ignored(): void
    {
        $this->addWord('disabled_word', SensitiveLevel::BLOCK);
        SensitiveWord::where('word', '__test__disabled_word')->update(['status' => false]);
        $this->service->refreshDict();
        $result = $this->service->detect('这是__test__disabled_word');
        $this->assertTrue($result->passed);
        $this->assertCount(0, $result->hits);
    }

    #[Test]
    #[TestDox('refreshDict 可重建缓存')]
    public function refresh_dict_rebuilds_cache(): void
    {
        $this->addWord('cache_word', SensitiveLevel::BLOCK);
        $this->assertTrue($this->service->detect('包含__test__cache_word内容')->isBlocked());

        SensitiveWord::where('word', '__test__cache_word')->delete();
        // 未刷新缓存时仍会命中
        $this->assertTrue($this->service->detect('包含__test__cache_word内容')->isBlocked());

        $this->service->refreshDict();
        $this->assertTrue($this->service->detect('包含__test__cache_word内容')->passed);
    }

    #[Test]
    #[TestDox('incrementHitCount 累加命中次数')]
    public function increment_hit_count_works(): void
    {
        $this->addWord('hit_word', SensitiveLevel::REVIEW);
        $this->service->incrementHitCount(['__test__hit_word', '__test__hit_word', 'not_exist']);
        $this->assertSame(1, (int) SensitiveWord::where('word', '__test__hit_word')->value('hit_count'));
    }

    #[Test]
    #[TestDox('filter 方法返回过滤后文本与拦截标记')]
    public function filter_method_returns_structured_result(): void
    {
        $this->addWord('adword', SensitiveLevel::REPLACE, SensitiveCategory::AD);
        $result = $this->service->filter('请__test__adword咨询');
        $this->assertFalse($result['blocked']);
        $this->assertCount(1, $result['hits']);
        $this->assertStringNotContainsString('__test__adword', $result['filtered']);
    }
}
