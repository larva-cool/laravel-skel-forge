<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Agents;

use App\Ai\Agents\Assistant;
use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Audit\ListAuditLogs;
use App\Ai\Tools\Audit\ListLoginHistories;
use App\Ai\Tools\Audit\ListVerificationCodes;
use App\Ai\Tools\Feedbacks\ListFeedbacks;
use App\Ai\Tools\Feedbacks\ReplyFeedback;
use App\Ai\Tools\Settings\GetSetting;
use App\Ai\Tools\Settings\SetSetting;
use App\Ai\Tools\System\GetAreas;
use App\Ai\Tools\System\SystemInfo;
use App\Ai\Tools\Users\GetUser;
use App\Ai\Tools\Users\ListIdentityVerifications;
use App\Ai\Tools\Users\ListUsers;
use App\Ai\Tools\Users\ReviewIdentityVerification;
use App\Ai\Tools\Users\SendUserNotification;
use App\Ai\Tools\Users\SetUserStatus;
use App\Ai\Tools\Wallet\ListRecharges;
use App\Ai\Tools\Wallet\ListWalletTrades;
use App\Ai\Tools\Wallet\ListWithdraws;
use App\Ai\Tools\Wallet\ReviewWithdraw;
use App\Ai\Tools\Web\WebFetch;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Assistant 工具权限过滤测试
 *
 * 验证 tools() 按当前对话管理员的权限点过滤工具：
 * 无权限的工具不暴露给模型，并向工具注入管理员用于执行兜底校验。
 */
#[CoversClass(Assistant::class)]
#[Group('ai')]
#[Group('permission')]
class AssistantToolPermissionsTest extends TestCase
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
     * 获取指定管理员可见的工具类名列表
     *
     * @return array<int, string>
     */
    private function toolClasses(Admin $admin): array
    {
        $tools = Assistant::make()->forUser($admin)->tools();

        return array_values(array_map(static fn ($tool): string => $tool::class, is_array($tools) ? $tools : iterator_to_array($tools)));
    }

    #[Test]
    #[TestDox('无对话参与者时所有受权限管控的工具均被过滤')]
    public function tools_are_deny_by_default_without_participant(): void
    {
        $tools = Assistant::make()->tools();

        $this->assertSame([], is_array($tools) ? $tools : iterator_to_array($tools));
    }

    #[Test]
    #[TestDox('非 Admin 参与者无法获得任何受权限管控的工具')]
    public function tools_are_filtered_for_non_admin_participant(): void
    {
        $user = User::factory()->create();

        $tools = Assistant::make()->forUser($user)->tools();

        $this->assertSame([], is_array($tools) ? $tools : iterator_to_array($tools));
    }

    #[Test]
    #[TestDox('超级管理员可获得全部工具')]
    public function super_admin_gets_all_tools(): void
    {
        $superAdmin = Admin::query()->where('username', 'admin')->firstOrFail();

        $this->assertCount(56, $this->toolClasses($superAdmin));
    }

    #[Test]
    #[TestDox('仅拥有 users.index 时只能看到用户查询工具')]
    public function admin_with_users_index_only_sees_read_tools(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $classes = $this->toolClasses($admin);

        $this->assertContains(ListUsers::class, $classes);
        $this->assertContains(GetUser::class, $classes);
        $this->assertNotContains(SetUserStatus::class, $classes);
        $this->assertNotContains(SetSetting::class, $classes);
        $this->assertNotContains(WebFetch::class, $classes);
        $this->assertCount(3, $classes);
    }

    #[Test]
    #[TestDox('拥有 users.edit 时同时获得用户写操作工具')]
    public function admin_with_users_edit_sees_write_tools(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index', 'users.edit']);

        $classes = $this->toolClasses($admin);

        $this->assertContains(SetUserStatus::class, $classes);
    }

    #[Test]
    #[TestDox('数组权限点任一满足即可使用验证码查询工具')]
    public function tool_with_array_permission_allows_any_match(): void
    {
        $admin = $this->createAdminWithPermissions(['mail-codes.index']);

        $this->assertContains(ListVerificationCodes::class, $this->toolClasses($admin));

        $admin2 = $this->createAdminWithPermissions(['audit-logs.index']);

        $this->assertNotContains(ListVerificationCodes::class, $this->toolClasses($admin2));
    }

    #[Test]
    #[TestDox('chat.use 权限解锁 AI 助手通用工具')]
    public function chat_use_unlocks_general_tools(): void
    {
        $admin = $this->createAdminWithPermissions(['chat.use']);

        $classes = $this->toolClasses($admin);

        $this->assertContains(WebFetch::class, $classes);
        $this->assertContains(SystemInfo::class, $classes);
        $this->assertNotContains(ListUsers::class, $classes);
        $this->assertCount(3, $classes);
    }

    #[Test]
    #[TestDox('可见工具已被注入当前管理员用于执行兜底校验')]
    public function visible_tools_carry_current_admin(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index', 'settings.index']);

        $tools = Assistant::make()->forUser($admin)->tools();
        $tools = is_array($tools) ? $tools : iterator_to_array($tools);

        foreach ($tools as $tool) {
            $this->assertInstanceOf(RequiresPermission::class, $tool);
            $property = new \ReflectionProperty($tool, 'admin');
            $this->assertSame($admin->id, $property->getValue($tool)?->id);
        }

        // 未通过装配直接 new 的工具未注入管理员
        $fresh = new GetSetting;
        $this->assertNull((new \ReflectionProperty($fresh, 'admin'))->getValue($fresh));
    }

    #[Test]
    #[TestDox('权限点过滤覆盖审计与地区工具')]
    public function permission_filtering_covers_audit_and_area_tools(): void
    {
        $admin = $this->createAdminWithPermissions(['login-histories.index', 'areas.index']);

        $classes = $this->toolClasses($admin);

        $this->assertContains(ListLoginHistories::class, $classes);
        $this->assertContains(GetAreas::class, $classes);
        $this->assertCount(2, $classes);
    }

    #[Test]
    #[TestDox('财务、反馈与实名认证工具按各自权限点放行')]
    public function permission_filtering_covers_wallet_feedback_and_identity_tools(): void
    {
        $admin = $this->createAdminWithPermissions([
            'wallet.index',
            'wallet.orders',
            'wallet.withdraws',
            'feedbacks.index',
            'identity-verifications.index',
            'audit-logs.index',
        ]);

        $classes = $this->toolClasses($admin);

        $this->assertContains(ListWalletTrades::class, $classes);
        $this->assertContains(ListRecharges::class, $classes);
        $this->assertContains(ListWithdraws::class, $classes);
        $this->assertContains(ReviewWithdraw::class, $classes);
        $this->assertContains(ListFeedbacks::class, $classes);
        $this->assertContains(ListIdentityVerifications::class, $classes);
        $this->assertContains(ListAuditLogs::class, $classes);
        $this->assertCount(7, $classes);
    }

    #[Test]
    #[TestDox('编辑类权限点解锁审批写操作工具')]
    public function edit_permissions_unlock_approval_write_tools(): void
    {
        $admin = $this->createAdminWithPermissions([
            'identity-verifications.edit',
            'feedbacks.edit',
            'users.edit',
        ]);

        $classes = $this->toolClasses($admin);

        $this->assertContains(ReviewIdentityVerification::class, $classes);
        $this->assertContains(ReplyFeedback::class, $classes);
        $this->assertContains(SendUserNotification::class, $classes);
        $this->assertNotContains(ReviewWithdraw::class, $classes);
    }
}
