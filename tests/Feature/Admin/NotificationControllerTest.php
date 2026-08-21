<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\NotificationController;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台通知管理控制器功能测试
 */
#[CoversClass(NotificationController::class)]
#[Group('admin')]
#[Group('notification')]
class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function sendNotification(string $type = 'test', bool $read = false): void
    {
        $this->admin->notify(new class($type) extends Notification
        {
            public function __construct(private string $type) {}

            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['title' => '测试通知', 'type' => $this->type];
            }

            public function databaseType(): string
            {
                return 'App\\Notifications\\'.$this->type.'Notification';
            }
        });

        if ($read) {
            $this->admin->fresh()->notifications()->latest()->first()->update(['read_at' => now()]);
        }
    }

    #[Test]
    #[TestDox('未登录访问通知列表返回 401')]
    public function guest_cannot_list_notifications(): void
    {
        $this->getJson('/admin/notifications')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取通知列表返回 200 与分页数据')]
    public function admin_can_list_notifications(): void
    {
        $this->sendNotification();
        $this->sendNotification();

        $response = $this->actingAsAdmin()->getJson('/admin/notifications');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    #[TestDox('按类型过滤通知')]
    public function admin_can_filter_notifications_by_type(): void
    {
        $this->sendNotification('Test');
        $this->sendNotification('Other');

        $response = $this->actingAsAdmin()->getJson('/admin/notifications?type=App\\Notifications\\TestNotification');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('获取未读通知列表')]
    public function admin_can_list_unread_notifications(): void
    {
        $this->sendNotification();
        $this->sendNotification();
        $this->sendNotification('Test', true);

        $response = $this->actingAsAdmin()->getJson('/admin/notifications/unread');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    #[TestDox('全部标记已读')]
    public function admin_can_mark_all_as_read(): void
    {
        $this->sendNotification();
        $this->sendNotification();

        $this->actingAsAdmin()->putJson('/admin/notifications/mark-all-read')
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertSame(0, $this->admin->fresh()->unreadNotifications()->count());
    }

    #[Test]
    #[TestDox('标记指定通知已读')]
    public function admin_can_mark_single_as_read(): void
    {
        $this->sendNotification();
        $notification = $this->admin->fresh()->unreadNotifications()->first();

        $this->actingAsAdmin()->putJson('/admin/notifications/mark-read', [
            'id' => $notification->id,
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    #[Test]
    #[TestDox('清空已读通知')]
    public function admin_can_clear_read_notifications(): void
    {
        $this->sendNotification();
        $this->sendNotification();
        $this->sendNotification('Test', true);

        $this->actingAsAdmin()->deleteJson('/admin/notifications/clear-read')
            ->assertNoContent();

        $this->assertSame(2, $this->admin->fresh()->notifications()->count());
    }
}
