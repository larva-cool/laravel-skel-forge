<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\SocialProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * SocialProvider 枚举单元测试
 */
#[CoversClass(SocialProvider::class)]
#[Group('enums')]
class SocialProviderTest extends TestCase
{
    #[Test]
    #[TestDox('枚举值为字符串')]
    public function enum_values_are_strings(): void
    {
        $this->assertSame('wechat_mp', SocialProvider::WECHAT_MP->value);
        $this->assertSame('wechat_app', SocialProvider::WECHAT_APP->value);
        $this->assertSame('wechat_mini_program', SocialProvider::WECHAT_MINI_PROGRAM->value);
        $this->assertSame('apple', SocialProvider::APPLE->value);
        $this->assertSame('douyin', SocialProvider::DOUYIN->value);
        $this->assertSame('kuaishou', SocialProvider::KUAISHOU->value);
        $this->assertSame('xiaohongshu', SocialProvider::XIAOHONGSHU->value);
    }

    #[Test]
    #[TestDox('label 返回正确标签')]
    public function label_returns_correct_string(): void
    {
        $this->assertSame('微信公众号', SocialProvider::WECHAT_MP->label());
        $this->assertSame('微信应用', SocialProvider::WECHAT_APP->label());
        $this->assertSame('微信小程序', SocialProvider::WECHAT_MINI_PROGRAM->label());
        $this->assertSame('Apple ID', SocialProvider::APPLE->label());
        $this->assertSame('抖音', SocialProvider::DOUYIN->label());
        $this->assertSame('快手', SocialProvider::KUAISHOU->label());
        $this->assertSame('小红书', SocialProvider::XIAOHONGSHU->label());
    }

    #[Test]
    #[TestDox('jsonSerialize 返回 value 和 label')]
    public function json_serialize_returns_value_and_label(): void
    {
        $json = SocialProvider::APPLE->jsonSerialize();

        $this->assertSame(['value' => 'apple', 'label' => 'Apple ID'], $json);
    }

    #[Test]
    #[TestDox('keys 返回所有枚举键名')]
    public function keys_returns_all_names(): void
    {
        $expected = ['WECHAT_MP', 'WECHAT_APP', 'WECHAT_MINI_PROGRAM', 'APPLE', 'DOUYIN', 'KUAISHOU', 'XIAOHONGSHU'];
        $this->assertSame($expected, SocialProvider::keys());
    }

    #[Test]
    #[TestDox('values 返回所有枚举值')]
    public function values_returns_all_values(): void
    {
        $expected = ['wechat_mp', 'wechat_app', 'wechat_mini_program', 'apple', 'douyin', 'kuaishou', 'xiaohongshu'];
        $this->assertSame($expected, SocialProvider::values());
    }

    #[Test]
    #[TestDox('options 返回键值对')]
    public function options_returns_key_value_pairs(): void
    {
        $options = SocialProvider::options();

        $this->assertCount(7, $options);
        $this->assertSame('微信公众号', $options['wechat_mp']);
        $this->assertSame('Apple ID', $options['apple']);
    }
}
