<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\Wallet\WithdrawAccountController;
use App\Models\User;
use App\Models\Wallet\WithdrawAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 前台收款账户管理接口功能测试
 */
#[CoversClass(WithdrawAccountController::class)]
#[Group('api')]
#[Group('wallet')]
#[Group('withdraw-account')]
class WithdrawAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    #[TestDox('未登录查看收款账户列表返回 401')]
    public function guest_cannot_list(): void
    {
        $this->getJson('/api/v1/user/wallet/withdraw-accounts')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('未登录添加收款账户返回 401')]
    public function guest_cannot_store(): void
    {
        $this->postJson('/api/v1/user/wallet/withdraw-accounts', [
            'account_type' => 'wechat',
            'real_name' => '张三',
            'account_no' => 'wxtest123',
        ])->assertUnauthorized();
    }

    #[Test]
    #[TestDox('添加微信收款账户：返回 201')]
    public function user_can_store_wechat_account(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/withdraw-accounts', [
                'account_type' => 'wechat',
                'real_name' => '张三',
                'account_no' => 'wxtest123456',
            ])
            ->assertCreated()
            ->assertJsonPath('account_type.value', 'wechat')
            ->assertJsonPath('real_name', '张三')
            ->assertJsonPath('is_default', true)
            ->assertJsonPath('is_verified', true)
            ->assertJsonStructure(['id', 'account_type', 'real_name', 'account_no', 'is_default', 'status', 'created_at']);

        $this->assertDatabaseCount('withdraw_accounts', 1);
    }

    #[Test]
    #[TestDox('添加银行卡收款账户：必填开户银行')]
    public function store_bank_account_requires_bank_name(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/withdraw-accounts', [
                'account_type' => 'bank',
                'real_name' => '李四',
                'account_no' => '6222021234567890',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('bank_name');
    }

    #[Test]
    #[TestDox('添加银行卡收款账户：带开户银行成功')]
    public function user_can_store_bank_account(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/withdraw-accounts', [
                'account_type' => 'bank',
                'real_name' => '李四',
                'account_no' => '6222021234567890',
                'bank_name' => '中国工商银行',
                'bank_branch' => '北京朝阳支行',
            ])
            ->assertCreated()
            ->assertJsonPath('account_type.value', 'bank')
            ->assertJsonPath('bank_name', '中国工商银行')
            ->assertJsonPath('bank_branch', '北京朝阳支行');
    }

    #[Test]
    #[TestDox('重复绑定同类型同账号返回 422')]
    public function duplicate_account_returns_422(): void
    {
        WithdrawAccount::factory()->forUser($this->user)->create([
            'account_type' => 'wechat',
            'account_no' => 'wxtest123',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/withdraw-accounts', [
                'account_type' => 'wechat',
                'real_name' => '张三',
                'account_no' => 'wxtest123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('account_no');
    }

    #[Test]
    #[TestDox('收款账户列表仅返回本人账户，且按默认优先排序')]
    public function list_returns_own_accounts_sorted(): void
    {
        $other = User::factory()->create();
        WithdrawAccount::factory()->forUser($other)->create();

        $default = WithdrawAccount::factory()->forUser($this->user)->default()->create();
        $normal = WithdrawAccount::factory()->forUser($this->user)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/wallet/withdraw-accounts')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        // 默认账户应排在前面
        $this->assertSame($default->id, $response->json('data.0.id'));
        $this->assertTrue($response->json('data.0.is_default'));
    }

    #[Test]
    #[TestDox('修改收款账户：可修改真实姓名和状态')]
    public function user_can_update_account(): void
    {
        $account = WithdrawAccount::factory()->forUser($this->user)->create();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/user/wallet/withdraw-accounts/{$account->id}", [
                'real_name' => '王五',
                'status' => 'disabled',
            ])
            ->assertOk()
            ->assertJsonPath('real_name', '王五')
            ->assertJsonPath('status.value', 'disabled');
    }

    #[Test]
    #[TestDox('修改他人收款账户返回 403')]
    public function user_cannot_update_others_account(): void
    {
        $other = User::factory()->create();
        $account = WithdrawAccount::factory()->forUser($other)->create();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/user/wallet/withdraw-accounts/{$account->id}", [
                'real_name' => '王五',
            ])
            ->assertForbidden();
    }

    #[Test]
    #[TestDox('删除收款账户：返回 204')]
    public function user_can_delete_account(): void
    {
        $account = WithdrawAccount::factory()->forUser($this->user)->create();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/user/wallet/withdraw-accounts/{$account->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('withdraw_accounts', ['id' => $account->id]);
    }

    #[Test]
    #[TestDox('删除他人收款账户返回 403')]
    public function user_cannot_delete_others_account(): void
    {
        $other = User::factory()->create();
        $account = WithdrawAccount::factory()->forUser($other)->create();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/user/wallet/withdraw-accounts/{$account->id}")
            ->assertForbidden();
    }

    #[Test]
    #[TestDox('设为默认账户：同类型下互斥')]
    public function set_default_exclusive_within_same_type(): void
    {
        $first = WithdrawAccount::factory()->forUser($this->user)->default()->create();
        $second = WithdrawAccount::factory()->forUser($this->user)->create();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/user/wallet/withdraw-accounts/{$second->id}/default")
            ->assertOk()
            ->assertJsonPath('is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    #[Test]
    #[TestDox('账号脱敏：列表中账号不显示完整信息')]
    public function account_no_is_masked_in_list(): void
    {
        WithdrawAccount::factory()->forUser($this->user)->create([
            'account_no' => '1234567890123456',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/wallet/withdraw-accounts')
            ->assertOk();

        $accountNo = $response->json('data.0.account_no');
        $this->assertNotSame('1234567890123456', $accountNo);
        $this->assertStringContainsString('*', $accountNo);
    }
}
