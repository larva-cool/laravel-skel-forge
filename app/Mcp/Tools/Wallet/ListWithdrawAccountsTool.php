<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Http\Resources\Api\V1\WithdrawAccountResource;
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
 * 获取当前登录用户的收款账户列表
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.withdraw_account.list')]
#[Description('获取当前登录用户的收款账户列表（分页），默认账户排最前，可按 account_type 过滤。')]
#[IsReadOnly]
#[IsIdempotent]
class ListWithdrawAccountsTool extends Tool
{
    use BuildsPaginatedResponse;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate(array_merge($this->paginationRules(), [
            'account_type' => ['sometimes', 'string', 'max:32'],
        ]));

        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = $user->withdrawAccounts()->orderByDesc('is_default')->orderByDesc('id');

        if (! empty($arguments['account_type'])) {
            $query->where('account_type', $arguments['account_type']);
        }

        $paginator = $query->paginate($this->resolvePerPage($arguments), ['*'], 'page', $this->resolvePage($arguments));

        return Response::structured($this->paginatedResult($paginator, WithdrawAccountResource::class));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return array_merge($this->paginationSchema($schema), [
            'account_type' => $schema->string()->description('可选，账户类型（如 bank、alipay、wechat）')->max(32),
        ]);
    }
}
