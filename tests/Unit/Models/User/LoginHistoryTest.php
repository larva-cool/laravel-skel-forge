<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\User;

use App\Models\System\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * LoginHistory 模型单元测试
 */
#[CoversClass(LoginHistory::class)]
#[Group('models')]
class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试工厂创建登录历史记录
     */
    #[Test]
    #[TestDox('工厂创建登录历史记录并验证默认值')]
    public function factory_creates_login_history_with_defaults(): void
    {
        $history = LoginHistory::factory()->create();

        $this->assertInstanceOf(LoginHistory::class, $history);
        $this->assertNotNull($history->ip);
        $this->assertNotNull($history->user_agent);
        $this->assertNotNull($history->login_at);
    }

    /**
     * 测试 CREATED_AT 映射为 login_at
     */
    #[Test]
    #[TestDox('CREATED_AT 常量映射为 login_at')]
    public function created_at_is_mapped_to_login_at(): void
    {
        $this->assertSame('login_at', LoginHistory::CREATED_AT);
    }

    /**
     * 测试 UPDATED_AT 为 null
     */
    #[Test]
    #[TestDox('UPDATED_AT 常量为 null')]
    public function updated_at_is_null(): void
    {
        $this->assertNull(LoginHistory::UPDATED_AT);
    }

    /**
     * 测试创建时自动设置 login_at
     */
    #[Test]
    #[TestDox('创建记录时自动设置 login_at 时间')]
    public function create_sets_login_at_timestamp(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15, 10, 30, 0));

        $history = LoginHistory::factory()->create();

        $this->assertSame('2025-06-15 10:30:00', $history->login_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    /**
     * 测试 user 多态关联
     */
    #[Test]
    #[TestDox('user 多态关联返回关联的 User 实例')]
    public function user_relation_returns_associated_user(): void
    {
        $user = User::factory()->create();
        $history = LoginHistory::factory()->create([
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
        ]);

        $this->assertInstanceOf(User::class, $history->user);
        $this->assertSame($user->id, $history->user->id);
    }

    /**
     * 测试创建登录记录时递增用户 login_count
     */
    #[Test]
    #[TestDox('创建登录记录时递增用户的 login_count')]
    public function create_increments_user_login_count(): void
    {
        $user = User::factory()->create(['login_count' => 5]);

        LoginHistory::factory()->create([
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
            'ip' => '192.168.1.100',
        ]);

        $user->refresh();
        $this->assertSame(6, $user->login_count);
    }

    /**
     * 测试创建登录记录时更新用户 last_login_ip 和 last_login_at
     */
    #[Test]
    #[TestDox('创建登录记录时更新用户的 last_login_ip 和 last_login_at')]
    public function create_updates_user_last_login_info(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15, 10, 30, 0));

        $user = User::factory()->create([
            'last_login_ip' => null,
            'last_login_at' => null,
        ]);

        LoginHistory::factory()->create([
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
            'ip' => '10.0.0.1',
        ]);

        $user->refresh();
        $this->assertSame('10.0.0.1', $user->last_login_ip);
        $this->assertSame('2025-06-15 10:30:00', $user->last_login_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    /**
     * 测试 isTodayLogged 当天有登录记录返回 true
     */
    #[Test]
    #[TestDox('isTodayLogged 当天有登录记录返回 true')]
    public function is_today_logged_returns_true_when_logged_today(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15, 14, 0, 0));

        $user = User::factory()->create();

        LoginHistory::factory()->create([
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
            'login_at' => Carbon::create(2025, 6, 15, 10, 0, 0),
        ]);

        $this->assertTrue(LoginHistory::isTodayLogged($user->id, $user->getMorphClass()));

        Carbon::setTestNow();
    }

    /**
     * 测试 isTodayLogged 当天无登录记录返回 false
     */
    #[Test]
    #[TestDox('isTodayLogged 当天无登录记录返回 false')]
    public function is_today_logged_returns_false_when_not_logged_today(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15, 14, 0, 0));

        $user = User::factory()->create();

        LoginHistory::factory()->create([
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
            'login_at' => Carbon::create(2025, 6, 14, 10, 0, 0),
        ]);

        $this->assertFalse(LoginHistory::isTodayLogged($user->id, $user->getMorphClass()));

        Carbon::setTestNow();
    }

    /**
     * 测试 isTodayLogged 无任何记录返回 false
     */
    #[Test]
    #[TestDox('isTodayLogged 无任何登录记录返回 false')]
    public function is_today_logged_returns_false_when_no_records(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(LoginHistory::isTodayLogged($user->id, $user->getMorphClass()));
    }

    /**
     * 测试 isTodayLogged 区分不同用户
     */
    #[Test]
    #[TestDox('isTodayLogged 区分不同用户的登录记录')]
    public function is_today_logged_distinguishes_different_users(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15, 14, 0, 0));

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        LoginHistory::factory()->create([
            'user_id' => $user1->id,
            'user_type' => $user1->getMorphClass(),
            'login_at' => Carbon::create(2025, 6, 15, 10, 0, 0),
        ]);

        $this->assertTrue(LoginHistory::isTodayLogged($user1->id, $user1->getMorphClass()));
        $this->assertFalse(LoginHistory::isTodayLogged($user2->id, $user2->getMorphClass()));

        Carbon::setTestNow();
    }

    /**
     * 测试隐藏属性 user_id 和 user_type
     */
    #[Test]
    #[TestDox('数组序列化时隐藏 user_id 和 user_type')]
    public function array_serialization_hides_user_id_and_type(): void
    {
        $history = LoginHistory::factory()->create();

        $array = $history->toArray();

        $this->assertArrayNotHasKey('user_id', $array);
        $this->assertArrayNotHasKey('user_type', $array);
    }

    /**
     * 测试多次登录递增 login_count
     */
    #[Test]
    #[TestDox('多次创建登录记录递增 login_count')]
    public function multiple_logins_increment_login_count(): void
    {
        $user = User::factory()->create(['login_count' => 0]);

        LoginHistory::factory()->count(3)->create([
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
        ]);

        $user->refresh();
        $this->assertSame(3, $user->login_count);
    }
}
