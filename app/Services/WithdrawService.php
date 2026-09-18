<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use App\Enums\WalletTradeType;
use App\Enums\WithdrawStatus;
use App\Events\Wallet\WithdrawApproved;
use App\Events\Wallet\WithdrawFailed;
use App\Events\Wallet\WithdrawRejected;
use App\Events\Wallet\WithdrawTransferred;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InsufficientFrozenBalanceException;
use App\Models\Admin\Admin;
use App\Models\User;
use App\Models\Wallet\Withdraw;
use App\Models\Wallet\WithdrawAccount;
use App\Notifications\Wallet\WithdrawApprovedNotification;
use App\Notifications\Wallet\WithdrawFailedNotification;
use App\Notifications\Wallet\WithdrawRejectedNotification;
use App\Notifications\Wallet\WithdrawTransferredNotification;
use App\Support\WalletHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 提现服务
 *
 * 提现资金流转全程使用 Phase 1 的钱包原子方法，保证余额一致：
 *   submit()      冻结申请金额（balance -> frozen_balance）
 *   reject()      解冻退回（frozen_balance -> balance）
 *   approve()     仅状态流转（资金仍处于冻结，待打款）
 *   markTransferred() 结算冻结（frozen_balance 减少，资金流出）
 *   markFailed()  解冻退回（打款失败，frozen_balance -> balance）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WithdrawService
{
    /**
     * 用户提交提现申请
     *
     * @param  User  $user  申请用户
     * @param  float  $amount  提现金额
     * @param  WithdrawAccount  $account  收款账户
     * @param  string|null  $clientIp  下单IP
     *
     * @throws ValidationException 校验不通过时抛出
     */
    public function submit(User $user, float $amount, WithdrawAccount $account, ?string $clientIp = null): Withdraw
    {
        $this->assertWithdrawEnabled();
        $this->assertAmountInRange($amount);
        $this->assertDailyLimit($user);
        $this->assertHasPendingWithdraw($user);
        $this->assertAccountAvailable($user, $account);

        $fee = $this->calculateFee($amount);

        try {
            return DB::transaction(function () use ($user, $amount, $fee, $account, $clientIp) {
                // 先创建提现单，再冻结余额（余额不足会抛异常回滚，连带撤销提现单）
                // 这样冻结流水的 source_type 能正确关联到 withdraw 记录，便于对账
                $withdraw = Withdraw::create([
                    'owner_type' => $user->getMorphClass(),
                    'owner_id' => $user->id,
                    'order_no' => Withdraw::generateOrderNo(),
                    'amount' => $amount,
                    'fee' => $fee,
                    'actual_amount' => round($amount - $fee, 2),
                    'account_type' => $account->account_type,
                    'account_info' => $account->toAccountInfoArray(),
                    'status' => WithdrawStatus::PENDING,
                    'client_ip' => $clientIp,
                ]);

                WalletHelper::freeze($user, $amount, $withdraw, '提现冻结');

                // 标记账户最后使用时间
                $account->markUsed();

                return $withdraw->fresh();
            });
        } catch (InsufficientBalanceException|InsufficientFrozenBalanceException $e) {
            // 将钱包引擎抛出的余额异常转为 ValidationException，便于控制器返回 422
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }
    }

    /**
     * 审核通过
     *
     * 资金仍保持冻结状态，等待后续打款。
     *
     * @throws ValidationException 非待审核状态时抛出
     */
    public function approve(Withdraw $withdraw, Admin $admin, ?string $remark = null): Withdraw
    {
        return DB::transaction(function () use ($withdraw, $admin, $remark) {
            $locked = $this->lockForUpdate($withdraw);
            $this->assertStatus($locked, WithdrawStatus::PENDING, '审核');

            $locked->fill([
                'status' => WithdrawStatus::APPROVED,
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => Carbon::now(),
                'remark' => $remark ?? $locked->remark,
            ])->save();

            $this->notifyOwner($locked, WithdrawApproved::class, WithdrawApprovedNotification::class);

            return $locked;
        });
    }

    /**
     * 审核驳回（解冻退回余额）
     *
     * @throws ValidationException 非待审核状态或原因为空时抛出
     */
    public function reject(Withdraw $withdraw, Admin $admin, string $reason, ?string $remark = null): Withdraw
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => '驳回原因为必填项']);
        }

        return DB::transaction(function () use ($withdraw, $admin, $reason, $remark) {
            $locked = $this->lockForUpdate($withdraw);
            $this->assertStatus($locked, WithdrawStatus::PENDING, '驳回');

            /** @var User|null $user */
            $user = $locked->owner;
            $this->assertOwnerExists($user);

            // 解冻退回申请金额
            WalletHelper::unfreeze($user, (float) $locked->amount, $locked, '提现驳回退回');

            $locked->fill([
                'status' => WithdrawStatus::REJECTED,
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => Carbon::now(),
                'reject_reason' => $reason,
                'remark' => $remark ?? $locked->remark,
            ])->save();

            $this->notifyOwner($locked, WithdrawRejected::class, WithdrawRejectedNotification::class, $reason);

            return $locked;
        });
    }

    /**
     * 标记打款成功（结算冻结金额，资金流出）
     *
     * @throws ValidationException 非审核通过状态时抛出
     */
    public function markTransferred(Withdraw $withdraw, Admin $admin, ?string $remark = null): Withdraw
    {
        return DB::transaction(function () use ($withdraw, $admin, $remark) {
            $locked = $this->lockForUpdate($withdraw);
            $this->assertStatus($locked, WithdrawStatus::APPROVED, '打款');

            /** @var User|null $user */
            $user = $locked->owner;
            $this->assertOwnerExists($user);

            // 结算冻结：冻结余额减少，资金真正流出
            WalletHelper::settleFrozen($user, (float) $locked->amount, $locked, WalletTradeType::WITHDRAW, '提现打款');

            $locked->fill([
                'status' => WithdrawStatus::SUCCESS,
                'reviewed_by' => $admin->getKey(),
                'transfer_at' => Carbon::now(),
                'remark' => $remark ?? $locked->remark,
            ])->save();

            $this->notifyOwner($locked, WithdrawTransferred::class, WithdrawTransferredNotification::class);

            return $locked;
        });
    }

    /**
     * 标记打款失败（解冻退回余额）
     *
     * @throws ValidationException 非审核通过状态或失败原因为空时抛出
     */
    public function markFailed(Withdraw $withdraw, Admin $admin, string $reason, ?string $remark = null): Withdraw
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => '失败原因为必填项']);
        }

        return DB::transaction(function () use ($withdraw, $admin, $reason, $remark) {
            $locked = $this->lockForUpdate($withdraw);
            $this->assertStatus($locked, WithdrawStatus::APPROVED, '标记失败');

            /** @var User|null $user */
            $user = $locked->owner;
            $this->assertOwnerExists($user);

            // 打款失败，冻结金额退回可用余额
            WalletHelper::unfreeze($user, (float) $locked->amount, $locked, '提现打款失败退回');

            $locked->fill([
                'status' => WithdrawStatus::FAILED,
                'reviewed_by' => $admin->getKey(),
                'transfer_at' => Carbon::now(),
                'fail_reason' => $reason,
                'remark' => $remark ?? $locked->remark,
            ])->save();

            $this->notifyOwner($locked, WithdrawFailed::class, WithdrawFailedNotification::class, $reason);

            return $locked;
        });
    }

    /**
     * 计算手续费
     */
    public function calculateFee(float $amount): float
    {
        $rate = (float) settings('wallet.withdraw_fee_rate', 0);

        if ($rate <= 0) {
            return 0.0;
        }

        return round($amount * $rate / 100, 2);
    }

    /**
     * 行锁重读提现单
     */
    protected function lockForUpdate(Withdraw $withdraw): Withdraw
    {
        /** @var Withdraw|null $locked */
        $locked = Withdraw::query()->where('id', $withdraw->id)->lockForUpdate()->first();

        if (! $locked) {
            throw ValidationException::withMessages(['withdraw' => '提现单不存在']);
        }

        return $locked;
    }

    /**
     * 断言当前状态匹配，否则禁止流转
     */
    protected function assertStatus(Withdraw $withdraw, WithdrawStatus $expected, string $action): void
    {
        if ($withdraw->status !== $expected) {
            throw ValidationException::withMessages([
                'withdraw' => "当前状态为「{$withdraw->status->label()}」，不可{$action}",
            ]);
        }
    }

    /**
     * 断言持有者为可提现的用户
     */
    protected function assertOwnerExists(?User $user): void
    {
        if (! $user instanceof User) {
            throw ValidationException::withMessages(['withdraw' => '提现单持有者不存在或类型不支持']);
        }
    }

    /**
     * 派发事件并发送站内信
     *
     * @param  class-string  $eventClass
     * @param  class-string  $notificationClass
     */
    protected function notifyOwner(Withdraw $withdraw, string $eventClass, string $notificationClass, ?string $extra = null): void
    {
        /** @var User|null $user */
        $user = $withdraw->owner;

        if (! $user instanceof User) {
            return;
        }

        event(new $eventClass($user, $withdraw));
        $user->notify(new $notificationClass($withdraw, $extra));
    }

    /**
     * 校验提现功能是否开启
     *
     * @throws ValidationException
     */
    protected function assertWithdrawEnabled(): void
    {
        if (! settings('wallet.enable_withdraw', true)) {
            throw ValidationException::withMessages(['amount' => '提现功能已关闭']);
        }
    }

    /**
     * 校验提现金额区间
     *
     * @throws ValidationException
     */
    protected function assertAmountInRange(float $amount): void
    {
        $min = (float) settings('wallet.min_withdraw_amount', 100);
        $max = (float) settings('wallet.max_withdraw_amount', 50000);

        if ($amount < $min) {
            throw ValidationException::withMessages(['amount' => "单笔提现金额不得低于 {$min} 元"]);
        }

        if ($amount > $max) {
            throw ValidationException::withMessages(['amount' => "单笔提现金额不得高于 {$max} 元"]);
        }
    }

    /**
     * 校验每日提现次数
     *
     * @throws ValidationException
     */
    protected function assertDailyLimit(User $user): void
    {
        $limit = (int) settings('wallet.daily_withdraw_limit', 3);

        if ($limit <= 0) {
            return;
        }

        $count = $user->withdraws()
            ->whereDate('created_at', Carbon::today())
            ->whereIn('status', [WithdrawStatus::PENDING->value, WithdrawStatus::APPROVED->value, WithdrawStatus::SUCCESS->value])
            ->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages(['amount' => "每日最多可提交 {$limit} 笔提现"]);
        }
    }

    /**
     * 校验是否存在待审核提现单（同一用户仅允许一笔待审）
     *
     * @throws ValidationException
     */
    protected function assertHasPendingWithdraw(User $user): void
    {
        $exists = $user->withdraws()
            ->whereIn('status', [WithdrawStatus::PENDING->value, WithdrawStatus::APPROVED->value])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['amount' => '您有一笔提现申请正在处理中，请完成后再提交']);
        }
    }

    /**
     * 校验收款账户是否属于该用户且可用
     *
     * @throws ValidationException
     */
    protected function assertAccountAvailable(User $user, WithdrawAccount $account): void
    {
        if ($account->owner_type !== $user->getMorphClass() || $account->owner_id !== $user->id) {
            throw ValidationException::withMessages(['withdraw_account_id' => '收款账户不属于当前用户']);
        }

        if (! $account->isAvailable()) {
            throw ValidationException::withMessages(['withdraw_account_id' => '收款账户已被禁用']);
        }
    }
}
