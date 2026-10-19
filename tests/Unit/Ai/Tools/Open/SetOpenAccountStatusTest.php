<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Open;

use App\Ai\Tools\Open\SetOpenAccountStatus;
use App\Enums\AdminStatus;
use App\Enums\UserStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\Open\OpenAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 开放平台账户状态变更工具测试
 */
#[CoversClass(SetOpenAccountStatus::class)]
#[Group('ai')]
#[Group('open-account')]
class SetOpenAccountStatusTest extends TestCase
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
        $result = (new SetOpenAccountStatus)->handle(new Request(['id' => 1, 'status' => 'frozen']));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 open_accounts.edit 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['open_accounts.index']);
        $account = OpenAccount::factory()->create(['status' => UserStatus::ACTIVE]);

        $result = (new SetOpenAccountStatus)->forAdmin($admin)->handle(new Request([
            'id' => $account->id,
            'status' => 'frozen',
        ]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertSame(UserStatus::ACTIVE, $account->fresh()->status);
    }

    #[Test]
    #[TestDox('冻结账户后同步吊销全部令牌')]
    public function handle_freezes_account_and_revokes_tokens(): void
    {
        $admin = $this->createAdminWithPermissions(['open_accounts.edit']);
        $account = OpenAccount::factory()->create(['status' => UserStatus::ACTIVE]);
        $account->createToken('open-api');

        $this->assertSame(1, $account->tokens()->count());

        $result = (new SetOpenAccountStatus)->forAdmin($admin)->handle(new Request([
            'id' => $account->id,
            'status' => 'frozen',
        ]));

        $this->assertSame(UserStatus::FROZEN, $account->fresh()->status);
        $this->assertSame(0, $account->tokens()->count());
        $this->assertStringContainsString('全部访问令牌已吊销', (string) $result);
    }

    #[Test]
    #[TestDox('启用账户后状态变更为正常')]
    public function handle_activates_account(): void
    {
        $admin = $this->createAdminWithPermissions(['open_accounts.edit']);
        $account = OpenAccount::factory()->create(['status' => UserStatus::FROZEN]);

        $result = (new SetOpenAccountStatus)->forAdmin($admin)->handle(new Request([
            'id' => $account->id,
            'status' => 'active',
        ]));

        $this->assertSame(UserStatus::ACTIVE, $account->fresh()->status);
        $this->assertStringContainsString('已启用', (string) $result);
    }

    #[Test]
    #[TestDox('目标状态与当前一致时提示无需变更')]
    public function handle_skips_when_status_unchanged(): void
    {
        $admin = $this->createAdminWithPermissions(['open_accounts.edit']);
        $account = OpenAccount::factory()->create(['status' => UserStatus::ACTIVE]);

        $result = (new SetOpenAccountStatus)->forAdmin($admin)->handle(new Request([
            'id' => $account->id,
            'status' => 'active',
        ]));

        $this->assertStringContainsString('无需变更', (string) $result);
    }
}
