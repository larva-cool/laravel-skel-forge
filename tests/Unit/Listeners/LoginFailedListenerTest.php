<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Admin\LoginFailed;
use App\Listeners\Admin\LoginFailedListener;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * LoginFailedListener 单元测试
 */
#[CoversClass(LoginFailedListener::class)]
#[Group('listeners')]
class LoginFailedListenerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('Admin 监听器创建 result 为 false 的登录历史记录')]
    public function admin_listener_creates_failed_login_history(): void
    {
        $admin = Admin::query()->where('username', 'admin')->first();
        $event = new LoginFailed($admin, '127.0.0.1', '12345', 'Mozilla/5.0');

        (new LoginFailedListener)->handle($event);

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $admin->id,
            'user_type' => $admin->getMorphClass(),
            'result' => false,
            'port' => 12345,
            'user_agent' => 'Mozilla/5.0',
        ]);
    }

    #[Test]
    #[TestDox('登录失败不会累加管理员的登录次数和最后登录时间')]
    public function failed_login_does_not_update_admin_login_stats(): void
    {
        $admin = Admin::query()->where('username', 'admin')->first();
        $originalCount = $admin->login_count;

        (new LoginFailedListener)->handle(
            new LoginFailed($admin, '127.0.0.1', '12345', 'Mozilla/5.0')
        );

        $admin->refresh();

        $this->assertSame($originalCount, $admin->login_count);
        $this->assertNull($admin->last_login_at);
    }
}
