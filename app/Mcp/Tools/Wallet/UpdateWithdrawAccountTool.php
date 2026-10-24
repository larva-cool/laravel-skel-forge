<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Enums\WithdrawAccountStatus;
use App\Http\Resources\Api\V1\WithdrawAccountResource;
use App\Models\Wallet\WithdrawAccount;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 修改提现收款账户
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.withdraw_account.update')]
#[Description('修改当前登录用户名下指定的提现收款账户。')]
class UpdateWithdrawAccountTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'real_name' => ['sometimes', 'string', 'max:32'],
            'account_no' => ['sometimes', 'string', 'max:64'],
            'bank_name' => ['nullable', 'string', 'max:64'],
            'bank_branch' => ['nullable', 'string', 'max:128'],
            'status' => ['sometimes', 'string', Rule::in(WithdrawAccountStatus::values())],
            'is_default' => ['nullable', 'boolean'],
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

        $account->update(array_filter($arguments, fn ($key) => $key !== 'id', ARRAY_FILTER_USE_KEY));

        if (! empty($arguments['is_default'])) {
            $account->setDefault();
        }

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
            'real_name' => $schema->string()->description('真实姓名')->max(32),
            'account_no' => $schema->string()->description('收款账号')->max(64),
            'bank_name' => $schema->string()->description('开户银行')->max(64),
            'bank_branch' => $schema->string()->description('支行名称')->max(128),
            'status' => $schema->string()->description('状态：active / disabled'),
            'is_default' => $schema->boolean()->description('是否设为默认'),
        ];
    }
}
