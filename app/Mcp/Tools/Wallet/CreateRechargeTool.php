<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Wallet;

use App\Enums\PayChannel;
use App\Http\Resources\Api\V1\RechargeResource;
use App\Services\RechargeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * 创建充值订单
 *
 * 高风险操作：涉及资金，需确认金额后调用。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('wallet.recharge.create')]
#[Description('为当前登录用户创建充值订单（高风险：涉及资金）。金额受系统设置的最小/最大限制，仅支持已接入的支付渠道。')]
#[IsDestructive]
class CreateRechargeTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, RechargeService $rechargeService): ResponseFactory
    {
        $arguments = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'pay_channel' => ['required', 'string', Rule::in(PayChannel::availableValues())],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $recharge = $rechargeService->create(
            $user,
            (float) $arguments['amount'],
            PayChannel::from($arguments['pay_channel']),
            null,
        );

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
            'amount' => $schema->number()->description('充值金额（元），必须大于 0')->required(),
            'pay_channel' => $schema->string()->description('支付渠道，如 manual（人工确认）')->required(),
        ];
    }
}
