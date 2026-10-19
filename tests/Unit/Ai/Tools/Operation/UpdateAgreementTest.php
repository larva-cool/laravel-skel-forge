<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Operation;

use App\Ai\Tools\Operation\UpdateAgreement;
use App\Enums\AdminStatus;
use App\Enums\AgreementStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\Operation\Agreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 用户协议更新工具测试
 */
#[CoversClass(UpdateAgreement::class)]
#[Group('ai')]
#[Group('agreement')]
class UpdateAgreementTest extends TestCase
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
        $result = (new UpdateAgreement)->handle(new Request(['id' => 1, 'title' => '新标题']));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 agreements.edit 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['agreements.index']);
        $agreement = Agreement::factory()->create(['title' => '原始标题']);

        $result = (new UpdateAgreement)->forAdmin($admin)->handle(new Request([
            'id' => $agreement->id,
            'title' => '新标题',
        ]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertSame('原始标题', $agreement->fresh()->title);
    }

    #[Test]
    #[TestDox('更新版本号与状态后记录编辑人与生效时间')]
    public function handle_updates_agreement_and_records_admin(): void
    {
        $admin = $this->createAdminWithPermissions(['agreements.edit']);
        $agreement = Agreement::factory()->create([
            'status' => AgreementStatus::DRAFT,
            'effective_at' => null,
        ]);

        $result = (new UpdateAgreement)->forAdmin($admin)->handle(new Request([
            'id' => $agreement->id,
            'version' => '2.0',
            'status' => 'published',
        ]));

        $fresh = $agreement->fresh();
        $this->assertSame('2.0', $fresh->version);
        $this->assertSame(AgreementStatus::PUBLISHED, $fresh->status);
        $this->assertSame($admin->id, $fresh->admin_id);
        $this->assertNotNull($fresh->effective_at);
        $this->assertStringContainsString('已更新协议', (string) $result);
    }

    #[Test]
    #[TestDox('未提供任何变更字段时操作被取消')]
    public function handle_cancels_when_nothing_provided(): void
    {
        $admin = $this->createAdminWithPermissions(['agreements.edit']);
        $agreement = Agreement::factory()->create(['title' => '原始标题']);

        $result = (new UpdateAgreement)->forAdmin($admin)->handle(new Request([
            'id' => $agreement->id,
        ]));

        $this->assertStringContainsString('至少提供一项', (string) $result);
        $this->assertSame('原始标题', $agreement->fresh()->title);
    }
}
