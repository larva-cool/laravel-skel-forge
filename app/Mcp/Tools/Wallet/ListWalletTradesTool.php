<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Http\Resources\Api\V1\WalletTradeResource;
use App\Mcp\Tools\Concerns\BuildsPaginatedResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取当前登录用户的钱包流水
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.trades.list')]
#[Description('获取当前登录用户的钱包流水（分页），支持按类型 type、起止日期 start_date/end_date 过滤。')]
#[IsReadOnly]
#[IsIdempotent]
class ListWalletTradesTool extends Tool
{
    use BuildsPaginatedResponse;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate(array_merge($this->paginationRules(), [
            'type' => ['sometimes', 'string', 'max:32'],
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]));

        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = $user->walletTrades();

        if (! empty($arguments['type'])) {
            $query->where('type', $arguments['type']);
        }

        if (! empty($arguments['start_date'])) {
            $query->whereDate('created_at', '>=', $arguments['start_date']);
        }

        if (! empty($arguments['end_date'])) {
            $query->whereDate('created_at', '<=', $arguments['end_date']);
        }

        $paginator = $query->orderByDesc('id')
            ->paginate($this->resolvePerPage($arguments), ['*'], 'page', $this->resolvePage($arguments));

        return Response::structured($this->paginatedResult($paginator, WalletTradeResource::class));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return array_merge($this->paginationSchema($schema), [
            'type' => $schema->string()->description('可选，流水类型（如 recharge、withdraw、adjust 等）')->max(32),
            'start_date' => $schema->string()->description('可选，起始日期 Y-m-d')->format('date'),
            'end_date' => $schema->string()->description('可选，截止日期 Y-m-d')->format('date'),
        ]);
    }
}
