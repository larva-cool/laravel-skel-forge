<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Users;

use App\Ai\Tools\Users\ReviewIdentityVerification;
use App\Enums\AdminStatus;
use App\Enums\IdentityStatus;
use App\Enums\IdentityType;
use App\Events\User\IdentityRejected;
use App\Events\User\IdentityVerified;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User\IdentityVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 实名认证审核工具测试
 */
#[CoversClass(ReviewIdentityVerification::class)]
#[Group('ai')]
#[Group('identity')]
class ReviewIdentityVerificationTest extends TestCase
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
        $result = (new ReviewIdentityVerification)->handle(new Request(['id' => 1, 'action' => 'approve']));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 identity-verifications.edit 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['identity-verifications.index']);
        $verification = IdentityVerification::factory()->create();

        $result = (new ReviewIdentityVerification)->forAdmin($admin)->handle(new Request([
            'id' => $verification->id,
            'action' => 'approve',
        ]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertSame(IdentityStatus::PENDING, $verification->fresh()->status);
    }

    #[Test]
    #[TestDox('审核通过后同步用户认证状态与有效期')]
    public function approve_syncs_user_identity_status(): void
    {
        Event::fake([IdentityVerified::class]);

        $admin = $this->createAdminWithPermissions(['identity-verifications.edit']);
        $verification = IdentityVerification::factory()->personal()->create();

        $result = (new ReviewIdentityVerification)->forAdmin($admin)->handle(new Request([
            'id' => $verification->id,
            'action' => 'approve',
        ]));

        $fresh = $verification->fresh();
        $this->assertSame(IdentityStatus::APPROVED, $fresh->status);
        $this->assertSame($admin->id, $fresh->admin_id);
        $this->assertNotNull($fresh->reviewed_at);
        $this->assertNotNull($fresh->expires_at);

        $user = $fresh->verifiable;
        $this->assertSame(IdentityStatus::APPROVED, $user->identity_status);
        $this->assertSame(IdentityType::PERSONAL, $user->identity_type);
        $this->assertStringContainsString('已通过实名认证记录', (string) $result);
        Event::assertDispatched(IdentityVerified::class);
    }

    #[Test]
    #[TestDox('审核驳回后记录驳回原因并同步用户状态')]
    public function reject_records_reason_and_syncs_user_status(): void
    {
        Event::fake([IdentityRejected::class]);

        $admin = $this->createAdminWithPermissions(['identity-verifications.edit']);
        $verification = IdentityVerification::factory()->personal()->create();

        $result = (new ReviewIdentityVerification)->forAdmin($admin)->handle(new Request([
            'id' => $verification->id,
            'action' => 'reject',
            'reason' => 'mismatch',
            'remark' => '证件信息与提交信息不符',
        ]));

        $fresh = $verification->fresh();
        $this->assertSame(IdentityStatus::REJECTED, $fresh->status);
        $this->assertSame('mismatch', $fresh->reject_reason);
        $this->assertSame('证件信息与提交信息不符', $fresh->reject_remark);
        $this->assertSame(IdentityStatus::REJECTED, $fresh->verifiable->identity_status);
        $this->assertStringContainsString('已驳回实名认证记录', (string) $result);
        Event::assertDispatched(IdentityRejected::class);
    }

    #[Test]
    #[TestDox('驳回时缺少驳回原因操作被取消')]
    public function reject_requires_reason(): void
    {
        $admin = $this->createAdminWithPermissions(['identity-verifications.edit']);
        $verification = IdentityVerification::factory()->create();

        $result = (new ReviewIdentityVerification)->forAdmin($admin)->handle(new Request([
            'id' => $verification->id,
            'action' => 'reject',
        ]));

        $this->assertStringContainsString('必须填写驳回原因', (string) $result);
        $this->assertSame(IdentityStatus::PENDING, $verification->fresh()->status);
    }

    #[Test]
    #[TestDox('非待审核状态的记录不可再次审核')]
    public function review_rejects_non_pending_verification(): void
    {
        $admin = $this->createAdminWithPermissions(['identity-verifications.edit']);
        $verification = IdentityVerification::factory()->approved()->create();

        $result = (new ReviewIdentityVerification)->forAdmin($admin)->handle(new Request([
            'id' => $verification->id,
            'action' => 'approve',
        ]));

        $this->assertStringContainsString('仅待审核记录可执行审核', (string) $result);
    }
}
