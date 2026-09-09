<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Jobs\Wallet;

use App\Services\RechargeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * 关闭超时未支付的充值订单
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CloseExpiredRechargesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  int  $limit  单次处理上限，避免一次性锁定过多行
     */
    public function __construct(
        protected int $limit = 500,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(RechargeService $rechargeService): void
    {
        $count = $rechargeService->closeExpired($this->limit);

        if ($count > 0) {
            Log::info('已关闭超时未支付的充值订单', ['count' => $count]);
        }
    }
}
