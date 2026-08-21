<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\SettingInputType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * SettingInputType 枚举单元测试
 */
#[CoversClass(SettingInputType::class)]
#[Group('enums')]
class SettingInputTypeTest extends TestCase
{
    #[Test]
    #[TestDox('枚举值正确')]
    public function enum_values_are_correct(): void
    {
        $this->assertSame('string', SettingInputType::STRING->value);
        $this->assertSame('textarea', SettingInputType::TEXTAREA->value);
        $this->assertSame('int', SettingInputType::INT->value);
        $this->assertSame('bool', SettingInputType::BOOL->value);
        $this->assertSame('select', SettingInputType::SELECT->value);
        $this->assertSame('radio', SettingInputType::RADIO->value);
        $this->assertSame('checkbox', SettingInputType::CHECKBOX->value);
        $this->assertSame('remote_select', SettingInputType::REMOTE_SELECT->value);
        $this->assertSame('remote_radio', SettingInputType::REMOTE_RADIO->value);
        $this->assertSame('remote_checkbox', SettingInputType::REMOTE_CHECKBOX->value);
        $this->assertSame('image', SettingInputType::IMAGE->value);
    }

    #[Test]
    #[TestDox('label 返回正确标签')]
    public function label_returns_correct_string(): void
    {
        $this->assertSame('单行文本', SettingInputType::STRING->label());
        $this->assertSame('多行文本', SettingInputType::TEXTAREA->label());
        $this->assertSame('数字', SettingInputType::INT->label());
        $this->assertSame('开关', SettingInputType::BOOL->label());
        $this->assertSame('下拉选择', SettingInputType::SELECT->label());
        $this->assertSame('单选', SettingInputType::RADIO->label());
        $this->assertSame('多选', SettingInputType::CHECKBOX->label());
        $this->assertSame('远程下拉选择', SettingInputType::REMOTE_SELECT->label());
        $this->assertSame('远程单选', SettingInputType::REMOTE_RADIO->label());
        $this->assertSame('远程多选', SettingInputType::REMOTE_CHECKBOX->label());
        $this->assertSame('图片', SettingInputType::IMAGE->label());
    }

    #[Test]
    #[TestDox('isRemote 仅远程类型返回 true')]
    public function is_remote_only_true_for_remote_types(): void
    {
        $this->assertTrue(SettingInputType::REMOTE_SELECT->isRemote());
        $this->assertTrue(SettingInputType::REMOTE_RADIO->isRemote());
        $this->assertTrue(SettingInputType::REMOTE_CHECKBOX->isRemote());
        $this->assertFalse(SettingInputType::STRING->isRemote());
        $this->assertFalse(SettingInputType::SELECT->isRemote());
        $this->assertFalse(SettingInputType::RADIO->isRemote());
        $this->assertFalse(SettingInputType::CHECKBOX->isRemote());
        $this->assertFalse(SettingInputType::IMAGE->isRemote());
    }

    #[Test]
    #[TestDox('hasOptions 仅本地选项类型返回 true')]
    public function has_options_only_true_for_local_option_types(): void
    {
        $this->assertTrue(SettingInputType::SELECT->hasOptions());
        $this->assertTrue(SettingInputType::RADIO->hasOptions());
        $this->assertTrue(SettingInputType::CHECKBOX->hasOptions());
        $this->assertFalse(SettingInputType::STRING->hasOptions());
        $this->assertFalse(SettingInputType::BOOL->hasOptions());
        $this->assertFalse(SettingInputType::REMOTE_SELECT->hasOptions());
        $this->assertFalse(SettingInputType::IMAGE->hasOptions());
    }

    #[Test]
    #[TestDox('jsonSerialize 返回 value 和 label')]
    public function json_serialize_returns_value_and_label(): void
    {
        $this->assertSame(
            ['value' => 'select', 'label' => '下拉选择'],
            SettingInputType::SELECT->jsonSerialize()
        );
    }

    #[Test]
    #[TestDox('keys 返回所有枚举键名')]
    public function keys_returns_all_names(): void
    {
        $this->assertSame(
            ['STRING', 'TEXTAREA', 'INT', 'BOOL', 'SELECT', 'RADIO', 'CHECKBOX', 'REMOTE_SELECT', 'REMOTE_RADIO', 'REMOTE_CHECKBOX', 'IMAGE'],
            SettingInputType::keys()
        );
    }

    #[Test]
    #[TestDox('values 返回所有枚举值')]
    public function values_returns_all_values(): void
    {
        $this->assertSame(
            ['string', 'textarea', 'int', 'bool', 'select', 'radio', 'checkbox', 'remote_select', 'remote_radio', 'remote_checkbox', 'image'],
            SettingInputType::values()
        );
    }

    #[Test]
    #[TestDox('options 返回键值对')]
    public function options_returns_key_value_pairs(): void
    {
        $expected = [
            'string' => '单行文本',
            'textarea' => '多行文本',
            'int' => '数字',
            'bool' => '开关',
            'select' => '下拉选择',
            'radio' => '单选',
            'checkbox' => '多选',
            'remote_select' => '远程下拉选择',
            'remote_radio' => '远程单选',
            'remote_checkbox' => '远程多选',
            'image' => '图片',
        ];

        $this->assertSame($expected, SettingInputType::options());
    }
}
