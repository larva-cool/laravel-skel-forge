<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Admin\Admin;
use App\Models\User;
use Database\Factories\Wallet\RechargeFactory;
use Database\Factories\Wallet\WithdrawFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台数据概览财务维度功能测试
 */
#[CoversClass(DashboardController::class)]
#[Group('admin')]
#[Group('dashboard')]
class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::query()->where('username', 'admin')->first();
        $this->user = User::factory()->create();
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    #[TestDox('未登录访问数据概览返回 401')]
    public function guest_cannot_view_stats(): void
    {
        $this->getJson('/admin/dashboard/stats')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('数据概览返回财务维度结构')]
    public function admin_can_view_finance_structure(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'finance' => [
                    'total_recharge',
                    'total_withdraw',
                    'net_income',
                    'today_recharge',
                    'today_withdraw',
                    'recharge_count',
                    'withdraw_count',
                    'trend',
                ],
            ]);
    }

    #[Test]
    #[TestDox('财务统计仅聚合已到账充值（含赠送）与打款成功提现')]
    public function finance_stats_aggregate_successful_orders_only(): void
    {
        RechargeFactory::new()->forUser($this->user)->success()->create(['amount' => 300, 'bonus_amount' => 0]);
        RechargeFactory::new()->forUser($this->user)->success()->withBonus(50)->create(['amount' => 200]);
        RechargeFactory::new()->forUser($this->user)->create(['amount' => 100]);

        WithdrawFactory::new()->forUser($this->user)->success()->create([
            'amount' => 150,
            'fee' => 0,
            'actual_amount' => 150,
        ]);
        WithdrawFactory::new()->forUser($this->user)->create(['amount' => 80]);

        $this->actingAsAdmin()
            ->getJson('/admin/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('finance.total_recharge', 550)
            ->assertJsonPath('finance.total_withdraw', 150)
            ->assertJsonPath('finance.net_income', 400)
            ->assertJsonPath('finance.recharge_count', 2)
            ->assertJsonPath('finance.withdraw_count', 1);
    }

    #[Test]
    #[TestDox('资金流向趋势返回近 7 天逐日数据')]
    public function finance_trend_returns_daily_series(): void
    {
        RechargeFactory::new()->forUser($this->user)->success()->create(['amount' => 100, 'bonus_amount' => 0]);

        $this->actingAsAdmin()
            ->getJson('/admin/dashboard/stats?days=7')
            ->assertOk()
            ->assertJsonCount(7, 'finance.trend')
            ->assertJsonPath('finance.trend.6.recharge', 100);
    }
}
