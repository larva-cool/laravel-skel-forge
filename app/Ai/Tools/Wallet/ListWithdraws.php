<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Wallet;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\User;
use App\Models\Wallet\Withdraw;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 提现申请查询工具。
 *
 * 支持按用户、提现状态、收款账户类型、提现单号、申请时间范围筛选，
 * 返回分页提现申请数据。只读操作。
 */
class ListWithdraws implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'wallet.withdraws';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询提现申请列表。支持按用户 ID、提现状态、收款账户类型、提现单号、申请时间范围筛选，以分页形式返回提现申请信息（含申请金额、手续费、实际到账、审核/打款时间、驳回原因等）。收款账户明细不在返回范围内。只读操作，不能执行审核或打款。';
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
                ->description('精确按前台用户 ID 筛选，仅返回该用户的提现申请'),
            'status' => $schema->string()
                ->enum(['pending', 'approved', 'rejected', 'success', 'failed'])
                ->description('提现状态筛选：pending=待审核，approved=已通过（待打款），rejected=已驳回，success=打款成功，failed=打款失败'),
            'account_type' => $schema->string()
                ->enum(['wechat', 'alipay', 'bank'])
                ->description('收款账户类型筛选：wechat=微信，alipay=支付宝，bank=银行卡'),
            'order_no' => $schema->string()
                ->max(50)
                ->description('模糊匹配提现单号'),
            'start_date' => $schema->string()
                ->format('date')
                ->description('申请起始日期（YYYY-MM-DD），含当天'),
            'end_date' => $schema->string()
                ->format('date')
                ->description('申请截止日期（YYYY-MM-DD），含当天'),
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

        foreach (['status', 'account_type', 'order_no', 'start_date', 'end_date'] as $key) {
            if ($value = $request->string($key)->toString()) {
                $filters[$key] = $value;
            }
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = Withdraw::query()
            ->filter($filters)
            ->with('owner')
            ->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Withdraw $withdraw) => [
            'id' => $withdraw->id,
            'order_no' => $withdraw->order_no,
            'user' => $withdraw->owner instanceof User ? [
                'id' => $withdraw->owner->id,
                'username' => $withdraw->owner->username,
                'name' => $withdraw->owner->name,
            ] : null,
            'amount' => $withdraw->amount,
            'fee' => $withdraw->fee,
            'actual_amount' => $withdraw->actual_amount,
            'account_type' => $withdraw->account_type->value,
            'account_type_label' => $withdraw->account_type->label(),
            'status' => $withdraw->status->value,
            'status_label' => $withdraw->status->label(),
            'reject_reason' => $withdraw->reject_reason,
            'fail_reason' => $withdraw->fail_reason,
            'reviewed_at' => $withdraw->reviewed_at?->toDateTimeString(),
            'transfer_at' => $withdraw->transfer_at?->toDateTimeString(),
            'created_at' => $withdraw->created_at?->toDateTimeString(),
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
