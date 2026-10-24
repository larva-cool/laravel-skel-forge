<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Enums\WithdrawAccountType;
use App\Http\Resources\Api\V1\WithdrawAccountResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 添加提现收款账户
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.withdraw_account.create')]
#[Description('为当前登录用户添加提现收款账户。同类型同账号不可重复绑定；首个账户自动设为默认。')]
class CreateWithdrawAccountTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'account_type' => ['required', 'string', Rule::in(WithdrawAccountType::values())],
            'real_name' => ['required', 'string', 'max:32'],
            'account_no' => ['required', 'string', 'max:64'],
            'bank_name' => ['required_if:account_type,bank', 'nullable', 'string', 'max:64'],
            'bank_branch' => ['nullable', 'string', 'max:128'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // 同类型同账号不可重复绑定
        $exists = $user->withdrawAccounts()
            ->where('account_type', $arguments['account_type'])
            ->where('account_no', $arguments['account_no'])
            ->exists();

        if ($exists) {
            return Response::error('Account already bound.');
        }

        $account = $user->withdrawAccounts()->create(array_merge($arguments, ['is_verified' => true]));

        if ($user->withdrawAccounts()->count() === 1) {
            $account->setDefault();
        } elseif (! empty($arguments['is_default'])) {
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
            'account_type' => $schema->string()->description('账户类型：bank / alipay / wechat')->required(),
            'real_name' => $schema->string()->description('真实姓名')->max(32)->required(),
            'account_no' => $schema->string()->description('收款账号')->max(64)->required(),
            'bank_name' => $schema->string()->description('开户银行（bank 类型必填）')->max(64),
            'bank_branch' => $schema->string()->description('支行名称')->max(128),
            'is_default' => $schema->boolean()->description('是否设为默认'),
        ];
    }
}
