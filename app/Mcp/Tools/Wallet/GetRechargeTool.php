<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Http\Resources\Api\V1\RechargeResource;
use App\Models\Wallet\Recharge;
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
 * 获取充值订单详情
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.recharge.get')]
#[Description('根据订单 ID 获取当前登录用户的充值订单详情；不存在的订单或不属于当前用户时返回错误。')]
#[IsReadOnly]
#[IsIdempotent]
class GetRechargeTool extends Tool
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

        $recharge = Recharge::query()
            ->where('id', $arguments['id'])
            ->where('owner_id', $user->getKey())
            ->where('owner_type', $user->getMorphClass())
            ->first();

        if (! $recharge) {
            return Response::error('Recharge not found or not owned by current user.');
        }

        return Response::structured((new RechargeResource($recharge))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('充值订单 ID')->min(1)->required(),
        ];
    }
}
