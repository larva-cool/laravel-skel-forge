<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Open;

use App\Ai\Tools\Open\RotateOpenAccountToken;
use App\Enums\AdminStatus;
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
 * 开放平台账户令牌轮换工具测试
 */
#[CoversClass(RotateOpenAccountToken::class)]
#[Group('ai')]
#[Group('open-account')]
class RotateOpenAccountTokenTest extends TestCase
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
        $result = (new RotateOpenAccountToken)->handle(new Request(['id' => 1]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 open_accounts.edit 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['open_accounts.index']);
        $account = OpenAccount::factory()->create();

        $result = (new RotateOpenAccountToken)->forAdmin($admin)->handle(new Request(['id' => $account->id]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertSame(0, $account->tokens()->count());
    }

    #[Test]
    #[TestDox('轮换后历史令牌作废并签发新令牌，明文仅返回一次')]
    public function handle_rotates_token_and_returns_plain_text_once(): void
    {
        $admin = $this->createAdminWithPermissions(['open_accounts.edit']);
        $account = OpenAccount::factory()->create();
        $account->createToken('open-api');
        $oldTokenIds = $account->tokens()->pluck('id')->all();

        $result = (new RotateOpenAccountToken)->forAdmin($admin)->handle(new Request(['id' => $account->id]));

        // 历史令牌已删除，仅保留一个新令牌
        $this->assertSame(1, $account->tokens()->count());
        $this->assertNotSame($oldTokenIds, $account->tokens()->pluck('id')->all());

        // 返回文案包含明文 Token 及妥善保管提示
        $this->assertStringContainsString('仅此一次展示', (string) $result);
        $this->assertMatchesRegularExpression('/\d+\|[A-Za-z0-9]+/', (string) $result);
    }

    #[Test]
    #[TestDox('账户不存在时操作被取消')]
    public function handle_cancels_when_account_not_found(): void
    {
        $admin = $this->createAdminWithPermissions(['open_accounts.edit']);

        $result = (new RotateOpenAccountToken)->forAdmin($admin)->handle(new Request(['id' => 99999]));

        $this->assertStringContainsString('未找到目标开放平台账户', (string) $result);
    }
}
