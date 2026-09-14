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
        $this->service = $this->app->make(SensitiveWordService::class);
    }

    private function addWord(string $word, SensitiveLevel $level = SensitiveLevel::REVIEW, SensitiveCategory $cat = SensitiveCategory::OTHER): void
    {
        SensitiveWord::query()->create([
            'word' => $word,
            'category' => $cat,
            'level' => $level,
            'replacement' => '*',
            'hit_count' => 0,
            'status' => true,
        ]);
        $this->service->refreshDict();
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
        $this->addWord('违禁');
        $result = $this->service->detect('今天天气真好');
        $this->assertTrue($result->passed);
        $this->assertSame([], $result->hits);
        $this->assertSame('今天天气真好', $result->filtered);
    }

    #[Test]
    #[TestDox('命中 BLOCK 级别词返回不通过')]
    public function block_level_word_blocks(): void
    {
        $this->addWord('法轮功', SensitiveLevel::BLOCK, SensitiveCategory::POLITICAL);
        $result = $this->service->detect('我在练习法轮功');
        $this->assertTrue($result->isBlocked());
        $this->assertFalse($result->passed);
        $this->assertCount(1, $result->hits);
        $this->assertSame('法轮功', $result->hits[0]['word']);
    }

    #[Test]
    #[TestDox('命中 REVIEW 级别词通过但需人工审核')]
    public function review_level_word_needs_review(): void
    {
        $this->addWord('傻逼', SensitiveLevel::REVIEW, SensitiveCategory::INSULT);
        $result = $this->service->detect('你是傻逼');
        $this->assertTrue($result->passed);
        $this->assertTrue($result->needsReview());
        $this->assertSame(SensitiveLevel::REVIEW, $result->maxLevel);
    }

    #[Test]
    #[TestDox('命中 REPLACE 级别词会被替换为掩码字符')]
    public function replace_level_word_gets_masked(): void
    {
        $this->addWord('加微信', SensitiveLevel::REPLACE, SensitiveCategory::AD);
        $result = $this->service->detect('详情请加微信联系');
        $this->assertTrue($result->passed);
        $this->assertFalse($result->isBlocked());
        $this->assertSame('详情请***联系', $result->filtered);
    }

    #[Test]
    #[TestDox('英文敏感词默认忽略大小写')]
    public function case_insensitive_matching(): void
    {
        $this->addWord('sb', SensitiveLevel::REVIEW, SensitiveCategory::INSULT);
        $r1 = $this->service->detect('you SB');
        $r2 = $this->service->detect('you Sb');
        $r3 = $this->service->detect('you sb');
        $this->assertCount(1, $r1->hits);
        $this->assertCount(1, $r2->hits);
        $this->assertCount(1, $r3->hits);
    }

    #[Test]
    #[TestDox('DFA 贪婪匹配最长词（如"傻子们"优先于"傻子"）')]
    public function greedy_matches_longest(): void
    {
        $this->addWord('傻', SensitiveLevel::REPLACE, SensitiveCategory::INSULT);
        $this->addWord('傻子', SensitiveLevel::REVIEW, SensitiveCategory::INSULT);
        $result = $this->service->detect('你是傻子吧');
        // 应匹配到"傻子"而不是"傻"
        $this->assertCount(1, $result->hits);
        $this->assertSame('傻子', $result->hits[0]['word']);
    }

    #[Test]
    #[TestDox('干扰字符（空格/星号）会被跳过，防止绕过检测')]
    public function ignores_stop_chars_between_chars(): void
    {
        $this->addWord('法轮功', SensitiveLevel::BLOCK, SensitiveCategory::POLITICAL);
        $result = $this->service->detect('法 轮 * 功');
        $this->assertTrue($result->isBlocked());
        $this->assertCount(1, $result->hits);
    }

    #[Test]
    #[TestDox('同时命中多个词时取最高级别')]
    public function multiple_hits_returns_max_level(): void
    {
        $this->addWord('加微信', SensitiveLevel::REPLACE, SensitiveCategory::AD);
        $this->addWord('法轮功', SensitiveLevel::BLOCK, SensitiveCategory::POLITICAL);
        $result = $this->service->detect('请加微信联系法轮功');
        $this->assertTrue($result->isBlocked());
        $this->assertCount(2, $result->hits);
    }

    #[Test]
    #[TestDox('禁用状态的敏感词不参与检测')]
    public function disabled_word_is_ignored(): void
    {
        $this->addWord('测试词', SensitiveLevel::BLOCK);
        SensitiveWord::where('word', '测试词')->update(['status' => false]);
        $this->service->refreshDict();
        $result = $this->service->detect('这是测试词');
        $this->assertTrue($result->passed);
        $this->assertCount(0, $result->hits);
    }

    #[Test]
    #[TestDox('refreshDict 可重建缓存')]
    public function refresh_dict_rebuilds_cache(): void
    {
        $this->addWord('敏感A', SensitiveLevel::BLOCK);
        $this->assertTrue($this->service->detect('包含敏感A内容')->isBlocked());

        SensitiveWord::where('word', '敏感A')->delete();
        // 未刷新缓存时仍会命中
        $this->assertTrue($this->service->detect('包含敏感A内容')->isBlocked());

        $this->service->refreshDict();
        $this->assertTrue($this->service->detect('包含敏感A内容')->passed);
    }

    #[Test]
    #[TestDox('incrementHitCount 累加命中次数')]
    public function increment_hit_count_works(): void
    {
        $this->addWord('命中词', SensitiveLevel::REVIEW);
        $this->service->incrementHitCount(['命中词', '命中词', '不存在']);
        $this->assertSame(1, SensitiveWord::where('word', '命中词')->value('hit_count'));
    }

    #[Test]
    #[TestDox('filter 方法返回过滤后文本与拦截标记')]
    public function filter_method_returns_structured_result(): void
    {
        $this->addWord('加微信', SensitiveLevel::REPLACE, SensitiveCategory::AD);
        $result = $this->service->filter('请加微信咨询');
        $this->assertSame('请***咨询', $result['filtered']);
        $this->assertFalse($result['blocked']);
        $this->assertCount(1, $result['hits']);
    }
}
