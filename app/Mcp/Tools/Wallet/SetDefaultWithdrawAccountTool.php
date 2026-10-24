<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Http\Resources\Api\V1\WithdrawAccountResource;
use App\Models\Wallet\WithdrawAccount;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 设为默认提现收款账户
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.withdraw_account.set_default')]
#[Description('将当前登录用户名下指定的提现收款账户设为默认。')]
class SetDefaultWithdrawAccountTool extends Tool
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

        $account = WithdrawAccount::query()
            ->where('id', $arguments['id'])
            ->where('owner_id', $user->getKey())
            ->where('owner_type', $user->getMorphClass())
            ->first();

        if (! $account) {
            return Response::error('Withdraw account not found or not owned by current user.');
        }

        $account->setDefault();

        return Response::structured((new WithdrawAccountResource($account->fresh()))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('收款账户 ID')->min(1)->required(),
        ];
    }
}
