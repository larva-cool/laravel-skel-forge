<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\Admin\LoginSucceeded as AdminLoginSucceeded;
use App\Events\User\LoginSucceeded as UserLoginSucceeded;
use App\Events\User\TodayFirstLogged;
use App\Models\Admin\Admin;
use App\Models\System\LoginHistory;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 事件单元测试
 */
#[CoversClass(AdminLoginSucceeded::class)]
#[CoversClass(UserLoginSucceeded::class)]
#[CoversClass(TodayFirstLogged::class)]
#[Group('events')]
class EventTest extends TestCase
{
    use RefreshDatabase;

    // ===== Admin LoginSucceeded =====

    #[Test]
    #[TestDox('Admin LoginSucceeded 构造函数正确赋值')]
    public function admin_login_succeeded_constructor(): void
    {
        $admin = Admin::query()->where('username', 'admin')->first();
        $event = new AdminLoginSucceeded($admin, '127.0.0.1', '12345', 'Mozilla/5.0');

        $this->assertSame($admin, $event->user);
        $this->assertSame('127.0.0.1', $event->ip);
        $this->assertSame('12345', $event->port);
        $this->assertSame('Mozilla/5.0', $event->userAgent);
    }

    #[Test]
    #[TestDox('Admin LoginSucceeded broadcastOn 返回 PrivateChannel')]
    public function admin_login_succeeded_broadcast_on(): void
    {
        $admin = Admin::query()->where('username', 'admin')->first();
        $event = new AdminLoginSucceeded($admin, '127.0.0.1', '12345', 'UA');

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    // ===== User LoginSucceeded =====

    #[Test]
    #[TestDox('User LoginSucceeded 构造函数正确赋值')]
    public function user_login_succeeded_constructor(): void
    {
        $user = User::factory()->create();
        $event = new UserLoginSucceeded($user, '192.168.1.1', '54321', 'Chrome');

        $this->assertSame($user, $event->user);
        $this->assertSame('192.168.1.1', $event->ip);
        $this->assertSame('54321', $event->port);
        $this->assertSame('Chrome', $event->userAgent);
    }

    #[Test]
    #[TestDox('User LoginSucceeded broadcastOn 返回 PrivateChannel')]
    public function user_login_succeeded_broadcast_on(): void
    {
        $user = User::factory()->create();
        $event = new UserLoginSucceeded($user, '127.0.0.1', '12345', 'UA');

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    // ===== TodayFirstLogged =====

    #[Test]
    #[TestDox('TodayFirstLogged 构造函数正确赋值')]
    public function today_first_logged_constructor(): void
    {
        $admin = Admin::query()->where('username', 'admin')->first();
        $loginHistory = LoginHistory::create([
            'user_id' => $admin->id,
            'user_type' => Admin::class,
            'ip' => '127.0.0.1',
            'port' => '8080',
            'user_agent' => 'Test',
            'login_at' => now(),
        ]);

        $event = new TodayFirstLogged($loginHistory);

        $this->assertSame($loginHistory, $event->loginHistory);
    }

    #[Test]
    #[TestDox('TodayFirstLogged broadcastOn 返回 PrivateChannel')]
    public function today_first_logged_broadcast_on(): void
    {
        $admin = Admin::query()->where('username', 'admin')->first();
        $loginHistory = LoginHistory::create([
            'user_id' => $admin->id,
            'user_type' => Admin::class,
            'ip' => '127.0.0.1',
            'port' => '8080',
            'user_agent' => 'Test',
            'login_at' => now(),
        ]);

        $event = new TodayFirstLogged($loginHistory);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }
}
