<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1\User;

use App\Enums\IdentityType;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Api\V1\User\IdentityController;
use App\Models\User;
use Database\Factories\User\IdentityVerificationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 用户端实名认证接口测试
 */
#[CoversClass(IdentityController::class)]
#[Group('api')]
#[Group('identity')]
class IdentityControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function validPersonalPayload(): array
    {
        return [
            'type' => IdentityType::PERSONAL->value,
            'real_name' => '张三',
            'id_card_no' => '110102199003078538',
            'id_card_front' => 'identity/id-card-front.jpg',
        ];
    }

    #[Test]
    #[TestDox('未登录访问提交接口返回 401')]
    public function guest_cannot_submit_identity(): void
    {
        $this->postJson('/api/v1/user/identity', $this->validPersonalPayload())
            ->assertUnauthorized();
    }

    #[Test]
    #[TestDox('个人提交成功进入待审')]
    public function user_can_submit_personal_identity(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/identity', $this->validPersonalPayload());

        $response->assertCreated();

        $this->assertDatabaseHas('identity_verifications', [
            'verifiable_type' => 'user',
            'verifiable_id' => $this->user->id,
            'type' => 'personal',
            'status' => 'pending',
        ]);
    }

    #[Test]
    #[TestDox('存在待审记录时再次提交被拒绝')]
    public function cannot_submit_while_pending_exists(): void
    {
        IdentityVerificationFactory::new()->create([
            'verifiable_type' => 'user',
            'verifiable_id' => $this->user->id,
            'status' => ReviewStatus::PENDING,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/identity', $this->validPersonalPayload())
            ->assertStatus(422);
    }

    #[Test]
    #[TestDox('身份证号格式校验失败返回 422')]
    public function invalid_id_card_no_rejected(): void
    {
        $payload = $this->validPersonalPayload();
        $payload['id_card_no'] = '12345';

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/identity', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_card_no']);
    }

    #[Test]
    #[TestDox('图片路径过长时返回 422')]
    public function image_path_too_long_rejected(): void
    {
        $payload = $this->validPersonalPayload();
        $payload['id_card_front'] = str_repeat('a', 256);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/identity', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_card_front']);
    }

    #[Test]
    #[TestDox('企业提交校验必填字段')]
    public function enterprise_requires_business_fields(): void
    {
        $payload = ['type' => IdentityType::ENTERPRISE->value];

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/identity', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'company_name', 'credit_code', 'legal_person_name',
                'legal_person_id_card_no', 'business_license',
            ]);
    }

    #[Test]
    #[TestDox('企业完整提交可成功')]
    public function enterprise_can_submit(): void
    {
        $payload = [
            'type' => IdentityType::ENTERPRISE->value,
            'company_name' => '示例科技有限公司',
            'credit_code' => '91110000XXXXXXXX1X',
            'legal_person_name' => '李四',
            'legal_person_id_card_no' => '110102199003078538',
            'business_license' => 'identity/business-license.jpg',
        ];

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/identity', $payload)
            ->assertCreated()
            ->assertJsonPath('type', 'enterprise')
            ->assertJsonPath('company_name', '示例科技有限公司');
    }

    #[Test]
    #[TestDox('当前状态接口返回 is_verified 与最新状态')]
    public function status_returns_overview(): void
    {
        $verification = IdentityVerificationFactory::new()->create([
            'verifiable_type' => 'user',
            'verifiable_id' => $this->user->id,
            'status' => ReviewStatus::REJECTED,
            'reject_reason' => 'mismatch',
        ]);

        // 模拟驳回流程同步用户状态
        $this->user->setIdentityStatus(ReviewStatus::REJECTED);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/identity/status')
            ->assertOk()
            ->assertJsonPath('is_verified', false)
            ->assertJsonPath('latest_status', 'rejected')
            ->assertJsonPath('latest_reject_reason', 'mismatch');
    }

    #[Test]
    #[TestDox('无认证记录时状态接口返回未提交')]
    public function status_returns_unsubmitted_when_no_record(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/identity/status')
            ->assertOk()
            ->assertJsonPath('is_verified', false)
            ->assertJsonPath('latest_status', 'unsubmitted')
            ->assertJsonPath('latest_status_label', '未提交');
    }

    #[Test]
    #[TestDox('show 接口返回用户最新一条认证记录')]
    public function show_returns_latest_verification(): void
    {
        IdentityVerificationFactory::new()->count(2)->create([
            'verifiable_type' => 'user',
            'verifiable_id' => $this->user->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/identity')
            ->assertOk()
            ->assertJsonStructure(['id', 'type', 'status']);
    }
}
