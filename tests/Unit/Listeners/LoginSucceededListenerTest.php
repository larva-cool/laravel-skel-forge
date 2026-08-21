<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Admin\LoginSucceeded as AdminLoginSucceeded;
use App\Events\User\LoginSucceeded as UserLoginSucceeded;
use App\Listeners\Admin\LoginSucceededListener as AdminListener;
use App\Listeners\User\LoginSucceededListener as UserListener;
use App\Models\Admin\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * LoginSucceededListener 单元测试
 */
#[CoversClass(AdminListener::class)]
#[CoversClass(UserListener::class)]
#[Group('listeners')]
class LoginSucceededListenerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('Admin 监听器创建登录历史记录')]
    public function admin_listener_creates_login_history(): void
    {
        $admin = Admin::query()->where('username', 'admin')->first();
        $event = new AdminLoginSucceeded($admin, '127.0.0.1', '12345', 'Mozilla/5.0');

        $listener = new AdminListener;
        $listener->handle($event);

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $admin->id,
            'user_type' => $admin->getMorphClass(),
            'ip' => '127.0.0.1',
            'port' => 12345,
            'user_agent' => 'Mozilla/5.0',
        ]);
    }

    #[Test]
    #[TestDox('User 监听器创建登录历史记录')]
    public function user_listener_creates_login_history(): void
    {
        $user = User::factory()->create();
        $event = new UserLoginSucceeded($user, '192.168.1.1', '54321', 'Chrome');

        $listener = new UserListener;
        $listener->handle($event);

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
            'ip' => '192.168.1.1',
            'port' => 54321,
            'user_agent' => 'Chrome',
        ]);
    }
}
