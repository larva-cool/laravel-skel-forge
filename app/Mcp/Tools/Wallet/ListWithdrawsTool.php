<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Http\Resources\Api\V1\WithdrawResource;
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
 * 获取当前登录用户的提现列表
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.withdraw.list')]
#[Description('获取当前登录用户的提现申请列表（分页），可按 status 过滤。')]
#[IsReadOnly]
#[IsIdempotent]
class ListWithdrawsTool extends Tool
{
    use BuildsPaginatedResponse;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate(array_merge($this->paginationRules(), [
            'status' => ['sometimes', 'string', 'max:32'],
        ]));

        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = $user->withdraws();

        if (! empty($arguments['status'])) {
            $query->where('status', $arguments['status']);
        }

        $paginator = $query->orderByDesc('id')
            ->paginate($this->resolvePerPage($arguments), ['*'], 'page', $this->resolvePage($arguments));

        return Response::structured($this->paginatedResult($paginator, WithdrawResource::class));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return array_merge($this->paginationSchema($schema), [
            'status' => $schema->string()->description('可选，提现状态（如 pending、approved、rejected、paid、failed）')->max(32),
        ]);
    }
}
