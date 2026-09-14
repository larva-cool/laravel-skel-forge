<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use App\Enums\PayChannel;
use App\Enums\RechargeStatus;
use App\Enums\WalletTradeType;
use App\Events\Wallet\RechargeSucceeded;
use App\Models\Admin\Admin;
use App\Models\User;
use App\Models\Wallet\Recharge;
use App\Notifications\Wallet\RechargeSucceededNotification;
use App\Support\WalletHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * 充值服务
 *
 * 负责充值订单的创建、到账确认与超时关闭。
 *
 * 到账入账逻辑统一收敛到 {@see markAsPaid()}，支付渠道接入后回调处理
 * 必须复用该方法，以保证「手动确认」与「渠道回调」两条路径行为完全一致。
 * 详见文档站（~/Skel/doc）「技术方案 · 钱包」第 12.1 节。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RechargeService
{
    /**
     * 创建充值订单
     *
     * @param  User  $user  充值用户
     * @param  float  $amount  充值金额
     * @param  PayChannel  $channel  支付渠道
     * @param  string|null  $clientIp  下单IP
     *
     * @throws ValidationException 充值关闭或金额越界时抛出
     */
    public function create(User $user, float $amount, PayChannel $channel, ?string $clientIp = null): Recharge
    {
        // 校验充值开关
        $this->assertRechargeEnabled();
        // 校验金额范围
        $this->assertAmountInRange($amount);
        // 校验支付渠道是否可用
        $this->assertChannelAvailable($channel);

        $expireMinutes = (int) settings('wallet.recharge_expire_minutes', 30);

        return Recharge::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->id,
            'order_no' => Recharge::generateOrderNo(),
            'amount' => $amount,
            'bonus_amount' => $this->calculateBonus($amount),
            'pay_channel' => $channel,
            'pay_status' => RechargeStatus::PENDING,
            'expired_at' => Carbon::now()->addMinutes($expireMinutes),
            'client_ip' => $clientIp,
        ]);
    }

    /**
     * 管理员手动确认到账
     *
     * @param  Recharge  $order  充值订单
     * @param  Admin  $admin  操作管理员
     * @param  string|null  $remark  确认备注
     *
     * @throws ValidationException 订单已过期时抛出
     */
    public function confirmManually(Recharge $order, Admin $admin, ?string $remark = null): Recharge
    {
        return $this->markAsPaid($order, [
            'confirmed_by' => $admin->getKey(),
            'remark' => $remark,
        ]);
    }

    /**
     * 标记订单已支付并入账（入账内核）
     *
     * 幂等保障（三重）：
     *   1. 事务内对订单行加锁后重新读取状态
     *   2. 已到账（success）直接返回，不重复入账
     *   3. transaction_id 唯一索引作为数据库层兜底
     *
     * @param  Recharge  $order  充值订单
     * @param  array<string, mixed>  $extra  额外写入字段（如 transaction_id、callback_payload）
     *
     * @throws ValidationException 订单已过期或处于其他终态时抛出
     */
    public function markAsPaid(Recharge $order, array $extra = []): Recharge
    {
        $conn = Recharge::query()->getConnection();

        return $conn->transaction(function () use ($order, $extra) {
            /** @var Recharge|null $locked */
            $locked = Recharge::query()->where('id', $order->id)->lockForUpdate()->first();

            if (! $locked) {
                throw ValidationException::withMessages(['order' => '充值订单不存在']);
            }

            // 幂等：已到账直接返回，不重复入账
            if ($locked->pay_status->isSuccess()) {
                return $locked;
            }

            // 其他终态（已过期/已关闭/已失败）不允许再入账
            if ($locked->pay_status->isFinal()) {
                throw ValidationException::withMessages([
                    'order' => "订单当前状态为「{$locked->pay_status->label()}」，不可确认到账",
                ]);
            }

            if ($locked->isExpired()) {
                throw ValidationException::withMessages(['order' => '订单已超过支付有效期，请重新下单']);
            }

            $locked->fill(array_merge([
                'pay_status' => RechargeStatus::SUCCESS,
                'paid_at' => Carbon::now(),
            ], array_filter($extra, static fn ($value) => $value !== null)));
            $locked->save();

            /** @var User|null $user */
            $user = $locked->owner;

            if (! $user instanceof User) {
                throw ValidationException::withMessages(['order' => '订单持有者不存在或类型不支持']);
            }

            // 本金与赠送合并为一笔流水入账
            $description = $this->buildTradeDescription($locked);
            WalletHelper::incr($user, $locked->totalAmount(), $locked, WalletTradeType::RECHARGE, $description);

            Event::dispatch(new RechargeSucceeded($user, $locked));
            $user->notify(new RechargeSucceededNotification($locked));

            return $locked;
        });
    }

    /**
     * 关闭指定订单
     *
     * @throws ValidationException 订单已处于终态时抛出
     */
    public function close(Recharge $order, ?string $remark = null): Recharge
    {
        if ($order->pay_status->isFinal()) {
            throw ValidationException::withMessages([
                'order' => "订单当前状态为「{$order->pay_status->label()}」，不可关闭",
            ]);
        }

        $order->fill([
            'pay_status' => RechargeStatus::CLOSED,
            'remark' => $remark ?? $order->remark,
        ])->save();

        return $order->refresh();
    }

    /**
     * 批量关闭超时未支付订单
     *
     * @param  int  $limit  单次处理上限
     * @return int 关闭的订单数
     */
    public function closeExpired(int $limit = 500): int
    {
        return Recharge::query()
            ->where('pay_status', RechargeStatus::PENDING->value)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', Carbon::now())
            ->limit($limit)
            ->update([
                'pay_status' => RechargeStatus::EXPIRED->value,
                'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * 计算赠送金额
     */
    protected function calculateBonus(float $amount): float
    {
        $rate = (float) settings('wallet.recharge_bonus_rate', 0);

        if ($rate <= 0) {
            return 0.0;
        }

        return round($amount * $rate / 100, 2);
    }

    /**
     * 构造流水描述
     */
    protected function buildTradeDescription(Recharge $order): string
    {
        $bonus = (float) $order->bonus_amount;
        $description = "充值订单 {$order->order_no}";

        if ($bonus > 0) {
            $description .= "（含赠送 {$bonus} 元）";
        }

        return $description;
    }

    /**
     * 校验充值功能是否开启
     *
     * @throws ValidationException
     */
    protected function assertRechargeEnabled(): void
    {
        if (! settings('wallet.enable_recharge', true)) {
            throw ValidationException::withMessages(['amount' => '充值功能已关闭']);
        }
    }

    /**
     * 校验充值金额区间
     *
     * @throws ValidationException
     */
    protected function assertAmountInRange(float $amount): void
    {
        $min = (float) settings('wallet.min_recharge_amount', 1);
        $max = (float) settings('wallet.max_recharge_amount', 50000);

        if ($amount < $min) {
            throw ValidationException::withMessages(['amount' => "单笔充值金额不得低于 {$min} 元"]);
        }

        if ($amount > $max) {
            throw ValidationException::withMessages(['amount' => "单笔充值金额不得高于 {$max} 元"]);
        }
    }

    /**
     * 校验支付渠道是否已接入
     *
     * 未接入的渠道禁止下单，避免产生无法支付的僵尸订单。
     *
     * @throws ValidationException
     */
    protected function assertChannelAvailable(PayChannel $channel): void
    {
        if (! $channel->isAvailable()) {
            throw ValidationException::withMessages([
                'pay_channel' => "支付渠道「{$channel->label()}」暂未开放",
            ]);
        }
    }
}
