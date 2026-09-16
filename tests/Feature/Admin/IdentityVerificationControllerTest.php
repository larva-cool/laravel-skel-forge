<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Admin\IdentityVerificationController;
use App\Models\Admin\Admin;
use App\Models\User;
use Database\Factories\User\IdentityVerificationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台实名认证管理功能测试
 */
#[CoversClass(IdentityVerificationController::class)]
#[Group('admin')]
#[Group('identity')]
class IdentityVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    #[TestDox('未登录访问列表返回 401')]
    public function guest_cannot_list(): void
    {
        $this->getJson('/admin/identity-verifications')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('列表分页与筛选')]
    public function admin_can_list_with_filters(): void
    {
        $user = User::factory()->create();
        IdentityVerificationFactory::new()->count(3)->create([
            'verifiable_type' => $user::class,
            'verifiable_id' => $user->id,
        ]);
        IdentityVerificationFactory::new()->enterprise()->create([
            'verifiable_type' => $user::class,
            'verifiable_id' => $user->id,
        ]);

        $response = $this->actingAsAdmin()->getJson('/admin/identity-verifications?type=personal');
        $response->assertOk()->assertJsonPath('meta.total', 3);

        $response = $this->actingAsAdmin()->getJson('/admin/identity-verifications?type=enterprise');
        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('通过：同步 users.identity_verified 与 identity_type')]
    public function approve_syncs_user_flags(): void
    {
        $user = User::factory()->create();
        $verification = IdentityVerificationFactory::new()->personal()->create([
            'verifiable_type' => $user::class,
            'verifiable_id' => $user->id,
        ]);

        $this->actingAsAdmin()
            ->postJson("/admin/identity-verifications/{$verification->id}/approve")
            ->assertOk()
            ->assertJsonPath('status', 'approved');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'identity_type' => 'personal',
            'identity_verified' => 1,
        ]);
    }

    #[Test]
    #[TestDox('驳回：写入原因和备注')]
    public function reject_records_reason_and_remark(): void
    {
        $user = User::factory()->create();
        $verification = IdentityVerificationFactory::new()->create([
            'verifiable_type' => $user::class,
            'verifiable_id' => $user->id,
            'status' => ReviewStatus::PENDING,
        ]);

        $this->actingAsAdmin()
            ->postJson("/admin/identity-verifications/{$verification->id}/reject", [
                'reason' => 'mismatch',
                'remark' => '姓名与身份证不符',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('reject_reason', 'mismatch')
            ->assertJsonPath('reject_reason_label', '证件信息不符');

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'status' => 'rejected',
            'reject_reason' => 'mismatch',
            'admin_id' => $this->admin->id,
        ]);
    }

    #[Test]
    #[TestDox('已审核完成的记录再次审核被拒绝')]
    public function cannot_review_completed_record(): void
    {
        $verification = IdentityVerificationFactory::new()->approved()->create();

        $this->actingAsAdmin()
            ->postJson("/admin/identity-verifications/{$verification->id}/approve")
            ->assertStatus(422);
    }

    #[Test]
    #[TestDox('统计接口返回正确结构')]
    public function stats_returns_summary(): void
    {
        IdentityVerificationFactory::new()->count(2)->create(['status' => ReviewStatus::PENDING]);
        IdentityVerificationFactory::new()->approved()->create();

        $this->actingAsAdmin()
            ->getJson('/admin/identity-verifications/stats')
            ->assertOk()
            ->assertJsonStructure(['total', 'pending', 'today', 'approved', 'rejected']);
    }

    #[Test]
    #[TestDox('驳回接口 reason 枚举校验失败返回 422')]
    public function reject_validates_reason(): void
    {
        $verification = IdentityVerificationFactory::new()->create();

        $this->actingAsAdmin()
            ->postJson("/admin/identity-verifications/{$verification->id}/reject", [
                'reason' => 'invalid_value',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }
}
