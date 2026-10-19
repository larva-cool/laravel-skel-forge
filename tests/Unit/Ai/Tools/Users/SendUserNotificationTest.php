<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Users;

use App\Ai\Tools\Users\SendUserNotification;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use App\Notifications\User\AdminMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 用户站内通知发送工具测试
 */
#[CoversClass(SendUserNotification::class)]
#[Group('ai')]
#[Group('notification')]
class SendUserNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 创建一个仅拥有指定权限的管理员
     *
     * @param  array<int, string>  $permissions
     */
    private function createAdminWithPermissions(array $permissions): Admin
    {
        $suffix = uniqid();

        $admin = Admin::query()->create([
            'username' => 'limited_'.$suffix,
            'name' => '受限管理员',
            'email' => "limited_{$suffix}@example.com",
            'password' => 'password',
            'status' => AdminStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'limited_'.$suffix,
            'display_name' => '受限角色',
            'guard_name' => AdminMenu::GUARD_NAME,
        ]);

        $role->syncPermissions($permissions);
        $admin->assignRole($role);

        return $admin->refresh();
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new SendUserNotification)->handle(new Request([
            'user_id' => 1,
            'title' => '系统维护通知',
            'body' => '今晚 24:00 系统维护',
        ]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 users.edit 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        Notification::fake();

        $admin = $this->createAdminWithPermissions(['users.index']);
        $user = User::factory()->create();

        $result = (new SendUserNotification)->forAdmin($admin)->handle(new Request([
            'user_id' => $user->id,
            'title' => '系统维护通知',
            'body' => '今晚 24:00 系统维护',
        ]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        Notification::assertNothingSent();
    }

    #[Test]
    #[TestDox('拥有权限时向目标用户发送站内通知')]
    public function handle_sends_notification_to_user(): void
    {
        Notification::fake();

        $admin = $this->createAdminWithPermissions(['users.edit']);
        $user = User::factory()->create();

        $result = (new SendUserNotification)->forAdmin($admin)->handle(new Request([
            'user_id' => $user->id,
            'title' => '系统维护通知',
            'body' => '今晚 24:00 系统维护，请提前保存数据。',
        ]));

        $this->assertStringContainsString('发送站内通知', (string) $result);
        Notification::assertSentTo(
            $user,
            AdminMessageNotification::class,
            fn (AdminMessageNotification $notification) => $notification->title === '系统维护通知'
                && $notification->body === '今晚 24:00 系统维护，请提前保存数据。'
        );
    }

    #[Test]
    #[TestDox('目标用户不存在时操作被取消')]
    public function handle_cancels_when_user_not_found(): void
    {
        Notification::fake();

        $admin = $this->createAdminWithPermissions(['users.edit']);

        $result = (new SendUserNotification)->forAdmin($admin)->handle(new Request([
            'user_id' => 99999,
            'title' => '测试',
            'body' => '测试内容',
        ]));

        $this->assertStringContainsString('未找到目标用户', (string) $result);
        Notification::assertNothingSent();
    }

    #[Test]
    #[TestDox('通知标题或正文为空时操作被取消')]
    public function handle_cancels_when_title_or_body_blank(): void
    {
        Notification::fake();

        $admin = $this->createAdminWithPermissions(['users.edit']);
        $user = User::factory()->create();

        $result = (new SendUserNotification)->forAdmin($admin)->handle(new Request([
            'user_id' => $user->id,
            'title' => '  ',
            'body' => '内容',
        ]));

        $this->assertStringContainsString('不能为空', (string) $result);
        Notification::assertNothingSent();
    }

    #[Test]
    #[TestDox('通知数据载荷符合用户端消息结构约定')]
    public function notification_payload_follows_database_message_convention(): void
    {
        $notification = new AdminMessageNotification('标题', '正文内容');

        $message = $notification->toDatabase(new User);

        $this->assertSame('标题', $message->data['title']);
        $this->assertSame('正文内容', $message->data['body']);
        $this->assertSame('admin.message', $message->data['type']);
    }
}
