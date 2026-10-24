<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User\Identity;

use App\Http\Resources\Api\V1\IdentityVerificationResource;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取当前登录用户最新一条实名认证记录
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.identity.get')]
#[Description('获取当前登录用户最新一条实名认证记录，包含认证类型、状态、证件附件路径、审核结果等；未提交过认证时返回 null。')]
#[IsReadOnly]
#[IsIdempotent]
class GetIdentityTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $verification = $user->latestIdentity()->first();

        return Response::structured([
            'verification' => $verification ? (new IdentityVerificationResource($verification))->resolve() : null,
        ]);
    }
}
