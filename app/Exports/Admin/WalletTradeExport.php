<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Exports\Admin;

use App\Enums\WalletTradeStatus;
use App\Enums\WalletTradeType;
use App\Exports\AbstractExport;
use App\Models\Wallet\WalletTrade;
use Illuminate\Database\Eloquent\Builder;

/**
 * 钱包流水导出
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WalletTradeExport extends AbstractExport
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
            'id' => '流水号',
            'owner_type' => '持有者类型',
            'owner_id' => '持有者ID',
            'type' => '交易类型',
            'amount' => '变动金额',
            'before_balance' => '变动前余额',
            'after_balance' => '变动后余额',
            'before_frozen' => '变动前冻结',
            'after_frozen' => '变动后冻结',
            'status' => '流水状态',
            'source_type' => '来源类型',
            'source_id' => '来源ID',
            'description' => '交易描述',
            'created_at' => '交易时间',
        ];
    }

    protected function buildQuery(): Builder
    {
        return WalletTrade::query()->filter($this->filters)->orderByDesc('id');
    }

    protected function sheetTitle(): string
    {
        return '钱包流水';
    }

    /**
     * 枚举字段输出中文标签，避免直接序列化成 JSON 结构
     *
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        $result = [];
        foreach (array_keys($this->columns()) as $field) {
            $value = data_get($row, $field);
            if ($value instanceof WalletTradeType || $value instanceof WalletTradeStatus) {
                $value = $value->label();
            }
            $result[] = $this->normalize($value, $field);
        }

        return $result;
    }
}
