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
        $this->service = $this->app->make(SensitiveWordService::class);
    }

    private function addWord(string $word, SensitiveLevel $level): void
    {
        SensitiveWord::query()->create([
            'word' => $word,
            'category' => SensitiveCategory::OTHER,
            'level' => $level,
            'replacement' => '*',
            'hit_count' => 0,
            'status' => true,
        ]);
        $this->service->refreshDict();
    }

    #[Test]
    #[TestDox('默认 block 模式：REPLACE 和 REVIEW 级别通过校验（由后续 passedValidation 处理）')]
    public function block_mode_allows_replace_and_review(): void
    {
        $this->addWord('广告', SensitiveLevel::REPLACE);
        $this->addWord('脏话', SensitiveLevel::REVIEW);

        $v = Validator::make(
            ['content' => '广告内容和脏话'],
            ['content' => new SensitiveTextRule]
        );
        $this->assertTrue($v->passes());
    }

    #[Test]
    #[TestDox('默认 block 模式：BLOCK 级别校验失败')]
    public function block_mode_blocks_block_level(): void
    {
        $this->addWord('违禁', SensitiveLevel::BLOCK);

        $v = Validator::make(
            ['content' => '这是违禁内容'],
            ['content' => new SensitiveTextRule]
        );
        $this->assertFalse($v->passes());
    }

    #[Test]
    #[TestDox('strict 模式：任意级别命中都校验失败')]
    public function strict_mode_rejects_any_level(): void
    {
        $this->addWord('广告', SensitiveLevel::REPLACE);
        $this->addWord('脏话', SensitiveLevel::REVIEW);
        $this->addWord('违禁', SensitiveLevel::BLOCK);

        foreach (['这里有广告', '这里有脏话', '这里有违禁'] as $text) {
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
        $this->addWord('违禁', SensitiveLevel::BLOCK);

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
