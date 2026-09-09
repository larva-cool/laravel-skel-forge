<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Enums\WalletTradeType;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InsufficientFrozenBalanceException;
use App\Models\User;
use App\Support\WalletHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 钱包余额引擎测试
 */
#[CoversClass(WalletHelper::class)]
#[Group('wallet')]
class WalletHelperTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    #[TestDox('增加余额：余额累加并生成流水快照')]
    public function incr_increases_balance_and_records_snapshot(): void
    {
        $trade = WalletHelper::incr($this->user, 100.50, $this->user, WalletTradeType::RECHARGE, '充值');

        $this->assertSame('100.50', $this->user->fresh()->balance);
        $this->assertSame('100.50', $trade->amount);
        $this->assertSame('0.00', $trade->before_balance);
        $this->assertSame('100.50', $trade->after_balance);
        $this->assertSame('0.00', $trade->before_frozen);
        $this->assertSame('0.00', $trade->after_frozen);
        $this->assertSame(WalletTradeType::RECHARGE, $trade->type);

        $this->assertDatabaseHas('wallet_trades', [
            'owner_type' => 'user',
            'owner_id' => $this->user->id,
            'type' => 'recharge',
        ]);
    }

    #[Test]
    #[TestDox('扣减余额：余额减少且流水金额为负')]
    public function decr_decreases_balance(): void
    {
        WalletHelper::incr($this->user, 200, $this->user, WalletTradeType::RECHARGE, '充值');

        $trade = WalletHelper::decr($this->user, 50, $this->user, WalletTradeType::PAYMENT, '消费');

        $this->assertSame('150.00', $this->user->fresh()->balance);
        $this->assertSame('-50.00', $trade->amount);
        $this->assertSame('200.00', $trade->before_balance);
        $this->assertSame('150.00', $trade->after_balance);
    }

    #[Test]
    #[TestDox('扣减余额：余额不足抛出异常且不产生流水')]
    public function decr_throws_when_balance_insufficient(): void
    {
        WalletHelper::incr($this->user, 10, $this->user, WalletTradeType::RECHARGE, '充值');

        $this->expectException(InsufficientBalanceException::class);

        try {
            WalletHelper::decr($this->user, 50, $this->user, WalletTradeType::PAYMENT, '消费');
        } finally {
            $this->assertSame('10.00', $this->user->fresh()->balance);
            $this->assertSame(1, $this->user->walletTrades()->count());
        }
    }

    #[Test]
    #[TestDox('冻结余额：可用余额减少、冻结余额增加、总资产不变')]
    public function freeze_moves_balance_to_frozen(): void
    {
        WalletHelper::incr($this->user, 300, $this->user, WalletTradeType::RECHARGE, '充值');

        $trade = WalletHelper::freeze($this->user, 120, $this->user, '下单冻结');
        $user = $this->user->fresh();

        $this->assertSame('180.00', $user->balance);
        $this->assertSame('120.00', $user->frozen_balance);
        $this->assertSame(300.0, $user->totalBalance());
        $this->assertSame('300.00', $trade->before_balance);
        $this->assertSame('180.00', $trade->after_balance);
        $this->assertSame('0.00', $trade->before_frozen);
        $this->assertSame('120.00', $trade->after_frozen);
    }

    #[Test]
    #[TestDox('冻结余额：可用余额不足时抛出异常')]
    public function freeze_throws_when_available_insufficient(): void
    {
        WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::freeze($this->user, 80, $this->user, '首次冻结');

        $this->expectException(InsufficientBalanceException::class);
        WalletHelper::freeze($this->user, 50, $this->user, '二次冻结');
    }

    #[Test]
    #[TestDox('解冻余额：冻结金额退回可用余额')]
    public function unfreeze_returns_frozen_to_balance(): void
    {
        WalletHelper::incr($this->user, 300, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::freeze($this->user, 120, $this->user, '下单冻结');

        $trade = WalletHelper::unfreeze($this->user, 120, $this->user, '订单取消');
        $user = $this->user->fresh();

        $this->assertSame('300.00', $user->balance);
        $this->assertSame('0.00', $user->frozen_balance);
        $this->assertSame('120.00', $trade->amount);
        $this->assertSame(WalletTradeType::UNFREEZE, $trade->type);
    }

    #[Test]
    #[TestDox('解冻余额：冻结余额不足时抛出异常')]
    public function unfreeze_throws_when_frozen_insufficient(): void
    {
        WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '充值');

        $this->expectException(InsufficientFrozenBalanceException::class);
        WalletHelper::unfreeze($this->user, 50, $this->user, '异常解冻');
    }

    #[Test]
    #[TestDox('结算冻结：冻结余额减少、可用余额不变、总资产减少')]
    public function settle_frozen_reduces_total_assets(): void
    {
        WalletHelper::incr($this->user, 300, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::freeze($this->user, 120, $this->user, '提现冻结');

        $trade = WalletHelper::settleFrozen($this->user, 120, $this->user, WalletTradeType::WITHDRAW, '提现打款');
        $user = $this->user->fresh();

        $this->assertSame('180.00', $user->balance);
        $this->assertSame('0.00', $user->frozen_balance);
        $this->assertSame(180.0, $user->totalBalance());
        $this->assertSame('-120.00', $trade->amount);
        $this->assertSame('120.00', $trade->before_frozen);
        $this->assertSame('0.00', $trade->after_frozen);
    }

    #[Test]
    #[TestDox('金额非正数时抛出参数异常')]
    public function non_positive_amount_throws_invalid_argument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WalletHelper::incr($this->user, 0, $this->user, WalletTradeType::RECHARGE, '非法金额');
    }

    #[Test]
    #[TestDox('冻结中的余额不可被直接消费')]
    public function frozen_balance_cannot_be_spent(): void
    {
        WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::freeze($this->user, 60, $this->user, '冻结');

        // 可用余额仅剩 40，扣减 50 应失败
        $this->expectException(InsufficientBalanceException::class);
        WalletHelper::decr($this->user, 50, $this->user, WalletTradeType::PAYMENT, '消费');
    }

    #[Test]
    #[TestDox('读取余额与冻结余额')]
    public function can_read_balance_and_frozen_balance(): void
    {
        WalletHelper::incr($this->user, 250, $this->user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::freeze($this->user, 100, $this->user, '冻结');

        $this->assertSame(150.0, WalletHelper::getBalance($this->user));
        $this->assertSame(100.0, WalletHelper::getFrozenBalance($this->user->id));
    }

    #[Test]
    #[TestDox('多笔连续操作后余额与流水快照保持连续一致')]
    public function consecutive_operations_keep_snapshot_chain_consistent(): void
    {
        WalletHelper::incr($this->user, 100, $this->user, WalletTradeType::RECHARGE, '充值1');
        WalletHelper::incr($this->user, 50, $this->user, WalletTradeType::RECHARGE, '充值2');
        WalletHelper::decr($this->user, 30, $this->user, WalletTradeType::PAYMENT, '消费1');

        $trades = $this->user->walletTrades()->orderBy('id')->get();

        $this->assertCount(3, $trades);
        // 每一笔的 before_balance 必须等于上一笔的 after_balance
        $this->assertSame('0.00', $trades[0]->before_balance);
        $this->assertSame('100.00', $trades[0]->after_balance);
        $this->assertSame('100.00', $trades[1]->before_balance);
        $this->assertSame('150.00', $trades[1]->after_balance);
        $this->assertSame('150.00', $trades[2]->before_balance);
        $this->assertSame('120.00', $trades[2]->after_balance);
        $this->assertSame('120.00', $this->user->fresh()->balance);
    }
}
