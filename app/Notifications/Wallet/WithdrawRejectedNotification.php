<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Notifications\Wallet;

use App\Models\Wallet\Withdraw;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

/**
 * 提现驳回通知
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WithdrawRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Withdraw $withdraw,
        public readonly ?string $reason = null,
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
        $reason = $this->reason ?: '收款账户信息有误';

        return new DatabaseMessage([
            'title' => '提现申请已驳回',
            'body' => "提现单「{$this->withdraw->order_no}」已被驳回，原因：{$reason}，金额已退回余额",
            'order_no' => $this->withdraw->order_no,
            'amount' => $this->withdraw->amount,
            'reason' => $reason,
            'type' => 'wallet.withdraw.rejected',
        ]);
    }
}
