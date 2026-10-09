<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Wallet\CloseExpiredRechargesJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * 关闭超时未支付的充值订单
 *
 * 作为 Artisan 命令包装 CloseExpiredRechargesJob，
 * 供数据库驱动的定时任务调度器使用。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Signature('wallet:close-expired-recharges {--limit=500 : 单次处理上限}")]
#[Description('关闭超时未支付的充值订单')]
class CloseExpiredRechargesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        CloseExpiredRechargesJob::dispatch($limit);

        $this->info("已派发关闭超时充值订单任务（limit={$limit}）。");

        return self::SUCCESS;
    }
}
