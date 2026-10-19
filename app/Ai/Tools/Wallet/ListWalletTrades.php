<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Wallet;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\User;
use App\Models\Wallet\WalletTrade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 钱包流水查询工具。
 *
 * 支持按用户、交易类型、流水状态、描述关键字、交易时间范围筛选，
 * 返回分页钱包流水数据。只读操作。
 */
class ListWalletTrades implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'wallet.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询全局钱包流水（金币/余额变动记录）。支持按用户 ID、交易类型、流水状态、交易描述关键字、交易时间范围筛选，以分页形式返回流水信息（含变动金额、变动前后可用/冻结余额等）。只读操作，不能执行调账。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()
                ->description('精确按前台用户 ID 筛选，仅返回该用户的钱包流水'),
            'type' => $schema->string()
                ->enum(['recharge', 'withdraw', 'freeze', 'unfreeze', 'payment', 'refund', 'admin_recharge', 'admin_deduct'])
                ->description('交易类型筛选：recharge=充值，withdraw=提现，freeze=冻结，unfreeze=解冻，payment=消费，refund=退款，admin_recharge=后台充正，admin_deduct=后台扣减'),
            'status' => $schema->string()
                ->enum(['pending', 'success', 'failed'])
                ->description('流水状态筛选：pending=处理中，success=已完成，failed=已失败'),
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配交易描述'),
            'start_date' => $schema->string()
                ->format('date')
                ->description('交易起始日期（YYYY-MM-DD），含当天'),
            'end_date' => $schema->string()
                ->format('date')
                ->description('交易截止日期（YYYY-MM-DD），含当天'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('页码，从 1 开始'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(20)
                ->description('每页条数，最大 50'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $filters = [];

        if ($userId = $request->integer('user_id')) {
            $filters['owner_type'] = (new User)->getMorphClass();
            $filters['owner_id'] = $userId;
        }

        foreach (['type', 'status', 'keyword', 'start_date', 'end_date'] as $key) {
            if ($value = $request->string($key)->toString()) {
                $filters[$key] = $value;
            }
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = WalletTrade::query()
            ->filter($filters)
            ->with('owner')
            ->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (WalletTrade $trade) => [
            'id' => $trade->id,
            'user' => $trade->owner instanceof User ? [
                'id' => $trade->owner->id,
                'username' => $trade->owner->username,
                'name' => $trade->owner->name,
            ] : null,
            'amount' => $trade->amount,
            'before_balance' => $trade->before_balance,
            'after_balance' => $trade->after_balance,
            'before_frozen' => $trade->before_frozen,
            'after_frozen' => $trade->after_frozen,
            'type' => $trade->type->value,
            'type_label' => $trade->type->label(),
            'status' => $trade->status->value,
            'status_label' => $trade->status->label(),
            'description' => $trade->description,
            'created_at' => $trade->created_at?->toDateTimeString(),
        ]);

        return json_encode([
            'data' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
