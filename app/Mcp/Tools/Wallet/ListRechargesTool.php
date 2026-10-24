<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Http\Resources\Api\V1\RechargeResource;
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
 * 获取当前登录用户的充值订单列表
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.recharge.list')]
#[Description('获取当前登录用户的充值订单列表（分页），可按支付状态 pay_status 过滤。')]
#[IsReadOnly]
#[IsIdempotent]
class ListRechargesTool extends Tool
{
    use BuildsPaginatedResponse;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate(array_merge($this->paginationRules(), [
            'pay_status' => ['sometimes', 'string', 'max:32'],
        ]));

        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = $user->recharges();

        if (! empty($arguments['pay_status'])) {
            $query->where('pay_status', $arguments['pay_status']);
        }

        $paginator = $query->orderByDesc('id')
            ->paginate($this->resolvePerPage($arguments), ['*'], 'page', $this->resolvePage($arguments));

        return Response::structured($this->paginatedResult($paginator, RechargeResource::class));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return array_merge($this->paginationSchema($schema), [
            'pay_status' => $schema->string()->description('可选，支付状态（如 pending、paid、closed）')->max(32),
        ]);
    }
}
