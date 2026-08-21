<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * User 模型单元测试
 */
#[CoversClass(User::class)]
#[Group('models')]
class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试工厂创建用户
     */
    #[Test]
    #[TestDox('工厂创建用户并验证默认值')]
    public function factory_creates_user_with_defaults(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(UserStatus::ACTIVE, $user->status);
        $this->assertSame(0, $user->available_points);
        $this->assertSame(0, $user->available_coins);
    }

    /**
     * 测试 factory frozen 状态
     */
    #[Test]
    #[TestDox('factory frozen 状态创建冻结用户')]
    public function factory_frozen_creates_frozen_user(): void
    {
        $user = User::factory()->frozen()->create();

        $this->assertSame(UserStatus::FROZEN, $user->status);
    }

    /**
     * 测试 status 字段转换为 UserStatus 枚举
     */
    #[Test]
    #[TestDox('status 字段转换为 UserStatus 枚举')]
    public function status_is_cast_to_enum(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(UserStatus::class, $user->status);
    }

    /**
     * 测试 name 属性：有昵称时返回昵称
     */
    #[Test]
    #[TestDox('name 访问器：有昵称时返回昵称')]
    public function name_accessor_returns_name_when_set(): void
    {
        $user = User::factory()->create([
            'name' => '张三',
            'username' => 'zhangsan',
        ]);

        $this->assertSame('张三', $user->name);
    }

    /**
     * 测试 name 属性：昵称为空时回退到用户名
     */
    #[Test]
    #[TestDox('name 访问器：昵称为空时回退到用户名')]
    public function name_accessor_falls_back_to_username(): void
    {
        $user = User::factory()->create([
            'name' => null,
            'username' => 'zhangsan',
        ]);

        $this->assertSame('zhangsan', $user->name);
    }

    /**
     * 测试 phone_text 属性：手机号脱敏
     */
    #[Test]
    #[TestDox('phone_text 访问器：手机号脱敏处理')]
    public function phone_text_attribute_masks_phone(): void
    {
        $user = User::factory()->create([
            'phone' => '13800138000',
        ]);

        $this->assertNotSame('13800138000', $user->phone_text);
        $this->assertStringContainsString('*', $user->phone_text);
    }

    /**
     * 测试 phone_text 属性：手机号为空时返回空字符串
     */
    #[Test]
    #[TestDox('phone_text 访问器：手机号为空时返回空字符串')]
    public function phone_text_returns_empty_string_when_phone_is_null(): void
    {
        $user = User::factory()->create([
            'phone' => null,
        ]);

        $this->assertSame('', $user->phone_text);
    }

    /**
     * 测试 status_label 属性返回状态文本
     */
    #[Test]
    #[TestDox('status_label 访问器返回状态中文标签')]
    public function status_label_returns_status_label(): void
    {
        $user = User::factory()->create();

        $this->assertSame('正常', $user->status_label);
    }

    /**
     * 测试 routeNotificationForPhone 返回手机号
     */
    #[Test]
    #[TestDox('routeNotificationForPhone 返回手机号')]
    public function route_notification_for_phone_returns_phone(): void
    {
        $user = User::factory()->create([
            'phone' => '13800138000',
        ]);

        $this->assertSame('13800138000', $user->routeNotificationForPhone(null));
    }

    /**
     * 测试 routeNotificationForPhone 手机号为空时返回 null
     */
    #[Test]
    #[TestDox('routeNotificationForPhone 手机号为空时返回 null')]
    public function route_notification_for_phone_returns_null_when_empty(): void
    {
        $user = User::factory()->create([
            'phone' => null,
        ]);

        $this->assertNull($user->routeNotificationForPhone(null));
    }

    /**
     * 测试 hasAvatar：无头像返回 false
     */
    #[Test]
    #[TestDox('hasAvatar：无头像返回 false')]
    public function has_avatar_returns_false_when_no_avatar(): void
    {
        $user = User::factory()->create(['avatar' => null]);

        $this->assertFalse($user->hasAvatar());
    }

    /**
     * 测试 hasAvatar：有头像返回 true
     */
    #[Test]
    #[TestDox('hasAvatar：有头像返回 true')]
    public function has_avatar_returns_true_when_avatar_set(): void
    {
        $user = User::factory()->create(['avatar' => 'https://example.com/avatar.png']);

        $this->assertTrue($user->hasAvatar());
    }

    /**
     * 测试 hasPassword：有密码返回 true
     */
    #[Test]
    #[TestDox('hasPassword：有密码返回 true')]
    public function has_password_returns_true_when_password_set(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->hasPassword());
    }

    /**
     * 测试 hasPassword：无密码返回 false
     */
    #[Test]
    #[TestDox('hasPassword：无密码返回 false')]
    public function has_password_returns_false_when_empty(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->assertFalse($user->hasPassword());
    }

    /**
     * 测试 isVip：VIP 未过期返回 true
     */
    #[Test]
    #[TestDox('isVip：VIP 未过期返回 true')]
    public function is_vip_returns_true_when_not_expired(): void
    {
        $user = User::factory()->create([
            'vip_expires_at' => Carbon::now()->addDays(30),
        ]);

        $this->assertTrue($user->isVip());
    }

    /**
     * 测试 isVip：VIP 已过期返回 false
     */
    #[Test]
    #[TestDox('isVip：VIP 已过期返回 false')]
    public function is_vip_returns_false_when_expired(): void
    {
        $user = User::factory()->create([
            'vip_expires_at' => Carbon::now()->subDays(1),
        ]);

        $this->assertFalse($user->isVip());
    }

    /**
     * 测试 isVip：无 VIP 时间返回 false
     */
    #[Test]
    #[TestDox('isVip：无 VIP 时间返回 false')]
    public function is_vip_returns_false_when_null(): void
    {
        $user = User::factory()->create([
            'vip_expires_at' => null,
        ]);

        $this->assertFalse($user->isVip());
    }

    /**
     * 测试 addVipDays：非 VIP 用户从当前时间开始计算
     */
    #[Test]
    #[TestDox('addVipDays：非 VIP 用户从当前时间开始计算')]
    public function add_vip_days_starts_from_now_for_non_vip(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        $user = User::factory()->create(['vip_expires_at' => null]);
        $user->addVipDays(30);

        $this->assertTrue($user->fresh()->isVip());
        $this->assertSame('2025-01-31 12:00:00', $user->fresh()->vip_expires_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    /**
     * 测试 addVipDays：已有 VIP 在原过期时间上累加
     */
    #[Test]
    #[TestDox('addVipDays：已有 VIP 在原过期时间上累加')]
    public function add_vip_days_extends_existing_vip(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        $user = User::factory()->create([
            'vip_expires_at' => Carbon::create(2025, 2, 1, 12, 0, 0),
        ]);
        $user->addVipDays(10);

        $this->assertSame('2025-02-11 12:00:00', $user->fresh()->vip_expires_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    /**
     * 测试 markActive 将用户标记为正常
     */
    #[Test]
    #[TestDox('markActive 将用户状态改为正常')]
    public function mark_active_sets_status_to_active(): void
    {
        $user = User::factory()->frozen()->create();

        $this->assertTrue($user->markActive());
        $this->assertSame(UserStatus::ACTIVE, $user->fresh()->status);
    }

    /**
     * 测试 markFrozen 将用户标记为冻结
     */
    #[Test]
    #[TestDox('markFrozen 将用户状态改为冻结')]
    public function mark_frozen_sets_status_to_frozen(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->markFrozen());
        $this->assertSame(UserStatus::FROZEN, $user->fresh()->status);
    }

    /**
     * 测试 password 自动哈希
     */
    #[Test]
    #[TestDox('password 字段自动哈希')]
    public function password_is_auto_hashed(): void
    {
        $user = User::factory()->create(['password' => 'plain-text-password']);

        $this->assertNotSame('plain-text-password', $user->password);
        $this->assertTrue(password_verify('plain-text-password', $user->password));
    }

    /**
     * 测试隐藏敏感字段
     */
    #[Test]
    #[TestDox('序列化时隐藏 password 和 remember_token')]
    public function sensitive_fields_are_hidden_in_array(): void
    {
        $user = User::factory()->create();
        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }
}
