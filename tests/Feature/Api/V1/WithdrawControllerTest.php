<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\WalletTradeType;
use App\Enums\WithdrawAccountType;
use App\Http\Controllers\Api\V1\Wallet\WithdrawController;
use App\Models\User;
use App\Services\WithdrawService;
use App\Support\WalletHelper;
use Database\Factories\Wallet\WithdrawFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 前台提现接口功能测试
 */
#[CoversClass(WithdrawController::class)]
#[Group('api')]
#[Group('wallet')]
#[Group('withdraw')]
class WithdrawControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    #[TestDox('未登录提交提现返回 401')]
    public function guest_cannot_submit(): void
    {
        $this->postJson('/api/v1/user/wallet/withdraws', [
            'amount' => 200,
            'account_type' => 'wechat',
            'account_info' => ['account' => 'abc', 'real_name' => '张三'],
        ])->assertUnauthorized();
    }

    #[Test]
    #[TestDox('未登录查看列表返回 401')]
    public function guest_cannot_list(): void
    {
        $this->getJson('/api/v1/user/wallet/withdraws')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('提交提现：返回 201 与待审核申请，余额被冻结')]
    public function user_can_submit_withdraw(): void
    {
        WalletHelper::incr($this->user, 1000, $this->user, WalletTradeType::RECHARGE, 'setup');

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/withdraws', [
                'amount' => 200,
                'account_type' => 'wechat',
                'account_info' => ['account' => 'wxtest', 'real_name' => '张三'],
            ])
            ->assertCreated()
            ->assertJsonPath('amount', '200.00')
            ->assertJsonPath('status.value', 'pending')
            ->assertJsonPath('fee', '0.00')
            ->assertJsonPath('actual_amount', '200.00')
            ->assertJsonStructure(['id', 'order_no', 'account_info', 'created_at']);

        $freshUser = $this->user->fresh();
        $this->assertSame('800.00', $freshUser->balance);
        $this->assertSame('200.00', $freshUser->frozen_balance);
        $this->assertSame(1, $this->user->withdraws()->count());
    }

    #[Test]
    #[TestDox('提交提现：金额为 0 返回 422')]
    public function submit_with_zero_amount_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/withdraws', [
                'amount' => 0,
                'account_type' => 'wechat',
                'account_info' => ['account' => 'abc', 'real_name' => '张三'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    #[Test]
    #[TestDox('提交提现：缺少账户信息返回 422')]
    public function submit_without_account_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/withdraws', [
                'amount' => 200,
                'account_type' => 'wechat',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account_info', 'account_info.account', 'account_info.real_name']);
    }

    #[Test]
    #[TestDox('提交提现：不支持的账户类型返回 422')]
    public function submit_with_invalid_account_type_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/withdraws', [
                'amount' => 200,
                'account_type' => 'paypal',
                'account_info' => ['account' => 'abc', 'real_name' => '张三'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('account_type');
    }

    #[Test]
    #[TestDox('提交提现：余额不足返回 422（由 ValidationException 转换）')]
    public function submit_with_insufficient_balance_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/user/wallet/withdraws', [
                'amount' => 200,
                'account_type' => 'wechat',
                'account_info' => ['account' => 'abc', 'real_name' => '张三'],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('withdraws', 0);
    }

    #[Test]
    #[TestDox('提现列表仅返回本人申请，且不暴露敏感字段')]
    public function list_is_scoped_to_current_user_and_sanitized(): void
    {
        $other = User::factory()->create();
        WalletHelper::incr($other, 1000, $other, WalletTradeType::RECHARGE, 'setup');
        WithdrawFactory::new()->forUser($this->user)->create(['amount' => 300]);
        app(WithdrawService::class)->submit(
            $other,
            200,
            WithdrawAccountType::WECHAT,
            ['account' => 'other', 'real_name' => '李四'],
        );

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/user/wallet/withdraws')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.amount', '300.00');

        // 前台资源脱敏处理，不暴露 owner/reviewed_by/client_ip
        $data = $response->json('data.0');
        $this->assertArrayNotHasKey('owner', $data);
        $this->assertArrayNotHasKey('reviewed_by', $data);
        $this->assertArrayNotHasKey('client_ip', $data);
        $this->assertArrayNotHasKey('remark', $data);
        // 账号应脱敏
        $this->assertIsArray($data['account_info']);
    }

    #[Test]
    #[TestDox('提现详情：可查看本人申请')]
    public function user_can_view_own_withdraw(): void
    {
        WalletHelper::incr($this->user, 1000, $this->user, WalletTradeType::RECHARGE, 'setup');
        $order = app(WithdrawService::class)->submit(
            $this->user,
            200,
            WithdrawAccountType::WECHAT,
            ['account' => 'wxtest', 'real_name' => '张三'],
        );

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/user/wallet/withdraws/{$order->id}")
            ->assertOk()
            ->assertJsonPath('id', $order->id)
            ->assertJsonPath('amount', '200.00');
    }

    #[Test]
    #[TestDox('提现详情：越权查看他人申请返回 403')]
    public function user_cannot_view_others_withdraw(): void
    {
        $other = User::factory()->create();
        $order = WithdrawFactory::new()->forUser($other)->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/user/wallet/withdraws/{$order->id}")
            ->assertForbidden();
    }

    #[Test]
    #[TestDox('存在待审核申请时再次提交返回 422')]
    public function submit_rejects_when_pending_exists(): void
    {
        WalletHelper::incr($this->user, 5000, $this->user, WalletTradeType::RECHARGE, 'setup');
        $this->actingAs($this->user, 'sanctum');

        $this->postJson('/api/v1/user/wallet/withdraws', [
            'amount' => 200,
            'account_type' => 'wechat',
            'account_info' => ['account' => 'a1', 'real_name' => '张三'],
        ])->assertCreated();

        $this->postJson('/api/v1/user/wallet/withdraws', [
            'amount' => 300,
            'account_type' => 'wechat',
            'account_info' => ['account' => 'a1', 'real_name' => '张三'],
        ])->assertStatus(422);
    }
}
