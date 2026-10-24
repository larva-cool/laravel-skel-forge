<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Http\Resources\Api\V1\WithdrawResource;
use App\Models\Wallet\Withdraw;
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
 * 获取提现详情
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.withdraw.get')]
#[Description('根据提现 ID 获取当前登录用户的提现申请详情；不存在或不属于当前用户时返回错误。')]
#[IsReadOnly]
#[IsIdempotent]
class GetWithdrawTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $withdraw = Withdraw::query()
            ->where('id', $arguments['id'])
            ->where('owner_id', $user->getKey())
            ->where('owner_type', $user->getMorphClass())
            ->first();

        if (! $withdraw) {
            return Response::error('Withdraw not found or not owned by current user.');
        }

        return Response::structured((new WithdrawResource($withdraw))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('提现申请 ID')->min(1)->required(),
        ];
    }
}
