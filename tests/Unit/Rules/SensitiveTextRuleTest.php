<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Enums\SensitiveCategory;
use App\Enums\SensitiveLevel;
use App\Models\System\SensitiveWord;
use App\Rules\SensitiveTextRule;
use App\Services\SensitiveWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * SensitiveTextRule 验证规则单元测试
 */
#[CoversClass(SensitiveTextRule::class)]
#[Group('rules')]
#[Group('sensitive')]
class SensitiveTextRuleTest extends TestCase
{
    use RefreshDatabase;

    private SensitiveWordService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = $this->app->make(SensitiveWordService::class);
    }

    private function addWord(string $word, SensitiveLevel $level): void
    {
        $model = SensitiveWord::withTrashed()->firstOrNew(['word' => '__test__'.$word]);
        $model->fill([
            'category' => SensitiveCategory::OTHER,
            'level' => $level,
            'replacement' => '*',
            'hit_count' => 0,
            'status' => true,
        ]);
        $model->deleted_at = null;
        $model->save();
        $this->service->refreshDict();
    }

    #[Test]
    #[TestDox('默认 block 模式：REPLACE 和 REVIEW 级别通过校验')]
    public function block_mode_allows_replace_and_review(): void
    {
        $this->addWord('adword', SensitiveLevel::REPLACE);
        $this->addWord('abuser', SensitiveLevel::REVIEW);

        $v = Validator::make(
            ['content' => '__test__adword内容和__test__abuser'],
            ['content' => new SensitiveTextRule]
        );
        $this->assertTrue($v->passes());
    }

    #[Test]
    #[TestDox('默认 block 模式：BLOCK 级别校验失败')]
    public function block_mode_blocks_block_level(): void
    {
        $this->addWord('blocker', SensitiveLevel::BLOCK);

        $v = Validator::make(
            ['content' => '这是__test__blocker内容'],
            ['content' => new SensitiveTextRule]
        );
        $this->assertFalse($v->passes());
    }

    #[Test]
    #[TestDox('strict 模式：任意级别命中都校验失败')]
    public function strict_mode_rejects_any_level(): void
    {
        $this->addWord('adword', SensitiveLevel::REPLACE);
        $this->addWord('abuser', SensitiveLevel::REVIEW);
        $this->addWord('blocker', SensitiveLevel::BLOCK);

        foreach (['这里有__test__adword', '这里有__test__abuser', '这里有__test__blocker'] as $text) {
            $v = Validator::make(
                ['content' => $text],
                ['content' => new SensitiveTextRule('strict')]
            );
            $this->assertFalse($v->passes(), "Expected '{$text}' to fail in strict mode");
        }
    }

    #[Test]
    #[TestDox('无敏感词文本通过校验')]
    public function clean_text_passes(): void
    {
        $this->addWord('blocker', SensitiveLevel::BLOCK);

        $v = Validator::make(
            ['content' => '普通文本'],
            ['content' => new SensitiveTextRule]
        );
        $this->assertTrue($v->passes());
    }

    #[Test]
    #[TestDox('非字符串输入校验失败')]
    public function non_string_fails(): void
    {
        $v = Validator::make(
            ['content' => ['array']],
            ['content' => new SensitiveTextRule]
        );
        $this->assertFalse($v->passes());
    }
}
