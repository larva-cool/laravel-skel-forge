<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools;

use App\Ai\Tools\Audit\ListVerificationCodes;
use App\Ai\Tools\Users\ListUsers;
use App\Ai\Tools\Users\SetUserStatus;
use App\Enums\AdminStatus;
use App\Enums\UserStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AI 工具执行兜底权限校验测试
 *
 * 验证工具 handle() 内的 authorizeAdmin() 兜底：
 * 即使模型被诱导发起了越权工具调用，执行阶段也会被拒绝。
 */
#[CoversClass(ListUsers::class)]
#[CoversClass(SetUserStatus::class)]
#[CoversClass(ListVerificationCodes::class)]
#[Group('ai')]
#[Group('permission')]
class ToolAuthorizationTest extends TestCase
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
        $result = (new ListUsers)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少对应权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['audit-logs.index']);

        $result = (new ListUsers)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时查询工具正常返回数据')]
    public function handle_allows_admin_with_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);
        User::factory()->create();

        $result = (new ListUsers)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('pagination', $payload);
    }

    #[Test]
    #[TestDox('无权限时写工具不执行任何数据变更')]
    public function write_tool_does_not_mutate_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);
        $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

        $result = (new SetUserStatus)->forAdmin($admin)->handle(new Request([
            'id' => $user->id,
            'status' => 'frozen',
        ]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertSame(UserStatus::ACTIVE, $user->fresh()->status);
    }

    #[Test]
    #[TestDox('数组权限点任一满足即可放行')]
    public function array_permission_allows_any_match(): void
    {
        $admin = $this->createAdminWithPermissions(['mail-codes.index']);

        $result = (new ListVerificationCodes)->forAdmin($admin)->handle(new Request(['channel' => 'mail']));

        $this->assertStringNotContainsString('没有执行此操作所需的权限', (string) $result);
    }
}
