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
 * 提现到账通知
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WithdrawTransferredNotification extends Notification
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
        return new DatabaseMessage([
            'title' => '提现已到账',
            'body' => "提现单「{$this->withdraw->order_no}」已打款成功，实际到账 {$this->withdraw->actual_amount} 元",
            'order_no' => $this->withdraw->order_no,
            'amount' => $this->withdraw->amount,
            'actual_amount' => $this->withdraw->actual_amount,
            'type' => 'wallet.withdraw.transferred',
        ]);
    }
}
