<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Models\Wallet\WithdrawAccount;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * 删除提现收款账户
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.withdraw_account.delete')]
#[Description('删除当前登录用户名下指定的提现收款账户（高风险：删除后不可恢复）。')]
#[IsDestructive]
class DeleteWithdrawAccountTool extends Tool
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

        $deleted = WithdrawAccount::query()
            ->where('id', $arguments['id'])
            ->where('owner_id', $user->getKey())
            ->where('owner_type', $user->getMorphClass())
            ->delete();

        if (! $deleted) {
            return Response::error('Withdraw account not found or not owned by current user.');
        }

        return Response::structured(['message' => 'Withdraw account deleted successfully.']);
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
