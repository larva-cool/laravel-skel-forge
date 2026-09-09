<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Exports\Admin;

use App\Exports\AbstractExport;
use App\Models\Wallet\Withdraw;
use Illuminate\Database\Eloquent\Builder;

/**
 * 提现申请导出
 */
class WithdrawExport extends AbstractExport
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
            'amount' => '提现金额',
            'fee' => '手续费',
            'actual_amount' => '实到金额',
            'account_type' => '账户类型',
            'status' => '状态',
            'reviewed_by' => '审核人ID',
            'reviewed_at' => '审核时间',
            'reject_reason' => '驳回原因',
            'transfer_at' => '打款时间',
            'fail_reason' => '失败原因',
            'created_at' => '申请时间',
        ];
    }

    protected function buildQuery(): Builder
    {
        return Withdraw::query()->filter($this->filters)->orderByDesc('id');
    }

    protected function sheetTitle(): string
    {
        return '提现申请';
    }
}
