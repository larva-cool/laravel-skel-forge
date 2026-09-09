<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Exports\Admin;

use App\Exports\AbstractExport;
use App\Models\Wallet\Recharge;
use Illuminate\Database\Eloquent\Builder;

/**
 * 充值订单导出
 */
class RechargeExport extends AbstractExport
{
    public const SYNC_THRESHOLD = 2000;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly array $filters = [],
    ) {}

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => 'ID',
            'order_no' => '订单号',
            'owner_type' => '持有者类型',
            'owner_id' => '持有者ID',
            'amount' => '充值金额',
            'bonus_amount' => '赠送金额',
            'pay_channel' => '支付渠道',
            'pay_status' => '支付状态',
            'transaction_id' => '渠道流水号',
            'paid_at' => '支付时间',
            'created_at' => '创建时间',
        ];
    }

    protected function buildQuery(): Builder
    {
        return Recharge::query()->filter($this->filters)->orderByDesc('id');
    }

    protected function sheetTitle(): string
    {
        return '充值订单';
    }
}
