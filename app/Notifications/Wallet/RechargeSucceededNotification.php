<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Notifications\Wallet;

use App\Models\Wallet\RechargeOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

/**
 * 充值到账通知
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RechargeSucceededNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly RechargeOrder $order,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(mixed $notifiable): DatabaseMessage
    {
        $bonus = (float) $this->order->bonus_amount;
        $body = "充值订单「{$this->order->order_no}」已到账，充值金额 {$this->order->amount} 元";

        if ($bonus > 0) {
            $body .= "，赠送 {$bonus} 元，实际到账 {$this->order->totalAmount()} 元";
        }

        return new DatabaseMessage([
            'title' => '充值到账',
            'body' => $body,
            'order_no' => $this->order->order_no,
            'amount' => $this->order->amount,
            'bonus_amount' => $this->order->bonus_amount,
            'total_amount' => $this->order->totalAmount(),
            'type' => 'wallet.recharge.succeeded',
        ]);
    }
}
