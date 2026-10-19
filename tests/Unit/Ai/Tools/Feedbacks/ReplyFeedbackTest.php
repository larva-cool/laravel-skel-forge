<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Feedbacks;

use App\Ai\Tools\Feedbacks\ReplyFeedback;
use App\Enums\AdminStatus;
use App\Enums\FeedbackStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\System\Feedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 用户反馈处理工具测试
 */
#[CoversClass(ReplyFeedback::class)]
#[Group('ai')]
#[Group('feedback')]
class ReplyFeedbackTest extends TestCase
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

    /**
     * 创建一条反馈
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createFeedback(array $overrides = []): Feedback
    {
        return Feedback::query()->create(array_merge([
            'title' => '充值未到账',
            'content' => '支付成功后余额未增加',
            'type' => 'bug',
        ], $overrides));
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ReplyFeedback)->handle(new Request(['id' => 1, 'status' => 4]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 feedbacks.edit 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['feedbacks.index']);
        $feedback = $this->createFeedback();

        $result = (new ReplyFeedback)->forAdmin($admin)->handle(new Request([
            'id' => $feedback->id,
            'status' => 4,
        ]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertSame(FeedbackStatus::PENDING, $feedback->fresh()->status);
    }

    #[Test]
    #[TestDox('更新状态时自动记录处理人与解决时间')]
    public function handle_updates_status_and_records_handler(): void
    {
        $admin = $this->createAdminWithPermissions(['feedbacks.edit']);
        $feedback = $this->createFeedback();

        $result = (new ReplyFeedback)->forAdmin($admin)->handle(new Request([
            'id' => $feedback->id,
            'status' => 4,
            'admin_remark' => '已核实为渠道延迟，余额已补发',
        ]));

        $fresh = $feedback->fresh();
        $this->assertSame(FeedbackStatus::RESOLVED, $fresh->status);
        $this->assertSame($admin->id, $fresh->admin_id);
        $this->assertSame('已核实为渠道延迟，余额已补发', $fresh->admin_remark);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertStringContainsString('已处理反馈', (string) $result);
    }

    #[Test]
    #[TestDox('仅填写处理备注时不改变状态与处理人')]
    public function handle_updates_remark_only(): void
    {
        $admin = $this->createAdminWithPermissions(['feedbacks.edit']);
        $feedback = $this->createFeedback();

        $result = (new ReplyFeedback)->forAdmin($admin)->handle(new Request([
            'id' => $feedback->id,
            'admin_remark' => '已联系用户补充截图',
        ]));

        $fresh = $feedback->fresh();
        $this->assertSame(FeedbackStatus::PENDING, $fresh->status);
        $this->assertSame('已联系用户补充截图', $fresh->admin_remark);
        $this->assertNull($fresh->admin_id);
        $this->assertStringContainsString('已填写处理备注', (string) $result);
    }

    #[Test]
    #[TestDox('状态与备注均缺失时操作被取消')]
    public function handle_cancels_when_nothing_provided(): void
    {
        $admin = $this->createAdminWithPermissions(['feedbacks.edit']);
        $feedback = $this->createFeedback();

        $result = (new ReplyFeedback)->forAdmin($admin)->handle(new Request([
            'id' => $feedback->id,
        ]));

        $this->assertStringContainsString('至少提供', (string) $result);
        $this->assertSame(FeedbackStatus::PENDING, $feedback->fresh()->status);
    }

    #[Test]
    #[TestDox('已有处理人的反馈再次变更状态时不覆盖处理人')]
    public function handle_keeps_existing_handler(): void
    {
        $admin = $this->createAdminWithPermissions(['feedbacks.edit']);
        $feedback = $this->createFeedback(['admin_id' => 1, 'status' => FeedbackStatus::PROCESSING]);

        (new ReplyFeedback)->forAdmin($admin)->handle(new Request([
            'id' => $feedback->id,
            'status' => 4,
        ]));

        $this->assertSame(1, $feedback->fresh()->admin_id);
    }
}
