<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Http\Resources\Api\V1\WithdrawResource;
use App\Models\Wallet\WithdrawAccount;
use App\Services\WithdrawService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * 提交提现申请
 *
 * 高风险操作：涉及资金冻结，需确认金额与收款账户后调用。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.withdraw.create')]
#[Description('为当前登录用户提交提现申请（高风险：涉及资金冻结）。仅支持已绑定的收款账户，金额受系统设置限制，同一用户仅允许一笔待审核提现。')]
#[IsDestructive]
class CreateWithdrawTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, WithdrawService $withdrawService): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'withdraw_account_id' => ['required', 'integer', 'min:1'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $account = WithdrawAccount::query()
            ->where('id', $arguments['withdraw_account_id'])
            ->where('owner_id', $user->getKey())
            ->where('owner_type', $user->getMorphClass())
            ->first();

        if (! $account) {
            return Response::error('Withdraw account not found or not owned by current user.');
        }

        $withdraw = $withdrawService->submit(
            $user,
            (float) $arguments['amount'],
            $account,
            null,
        );

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
            'amount' => $schema->number()->description('提现金额（元），必须大于 0')->required(),
            'withdraw_account_id' => $schema->integer()->description('收款账户 ID')->min(1)->required(),
        ];
    }
}
