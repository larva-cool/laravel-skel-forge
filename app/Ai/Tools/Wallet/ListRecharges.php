<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Wallet;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\User;
use App\Models\Wallet\Recharge;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 充值订单查询工具。
 *
 * 支持按用户、支付状态、支付渠道、订单号、下单时间范围筛选，
 * 返回分页充值订单数据。只读操作。
 */
class ListRecharges implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'wallet.orders';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询充值订单列表。支持按用户 ID、支付状态、支付渠道、订单号、下单时间范围筛选，以分页形式返回充值订单信息（含金额、赠送金额、渠道流水号、支付时间等）。只读操作，不能执行确认到账或关单。';
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
                ->description('精确按前台用户 ID 筛选，仅返回该用户的充值订单'),
            'pay_status' => $schema->string()
                ->enum(['pending', 'success', 'failed', 'expired', 'closed'])
                ->description('支付状态筛选：pending=待支付，success=已到账，failed=支付失败，expired=已过期，closed=已关闭'),
            'pay_channel' => $schema->string()
                ->enum(['manual', 'wechat', 'alipay', 'apple'])
                ->description('支付渠道筛选：manual=后台确认，wechat=微信支付，alipay=支付宝，apple=苹果内购'),
            'order_no' => $schema->string()
                ->max(50)
                ->description('模糊匹配充值订单号'),
            'start_date' => $schema->string()
                ->format('date')
                ->description('下单起始日期（YYYY-MM-DD），含当天'),
            'end_date' => $schema->string()
                ->format('date')
                ->description('下单截止日期（YYYY-MM-DD），含当天'),
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

        foreach (['pay_status', 'pay_channel', 'order_no', 'start_date', 'end_date'] as $key) {
            if ($value = $request->string($key)->toString()) {
                $filters[$key] = $value;
            }
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = Recharge::query()
            ->filter($filters)
            ->with('owner')
            ->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Recharge $recharge) => [
            'id' => $recharge->id,
            'order_no' => $recharge->order_no,
            'user' => $recharge->owner instanceof User ? [
                'id' => $recharge->owner->id,
                'username' => $recharge->owner->username,
                'name' => $recharge->owner->name,
            ] : null,
            'amount' => $recharge->amount,
            'bonus_amount' => $recharge->bonus_amount,
            'total_amount' => $recharge->totalAmount(),
            'pay_channel' => $recharge->pay_channel->value,
            'pay_channel_label' => $recharge->pay_channel->label(),
            'pay_status' => $recharge->pay_status->value,
            'pay_status_label' => $recharge->pay_status->label(),
            'transaction_id' => $recharge->transaction_id,
            'paid_at' => $recharge->paid_at?->toDateTimeString(),
            'expired_at' => $recharge->expired_at?->toDateTimeString(),
            'created_at' => $recharge->created_at?->toDateTimeString(),
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
