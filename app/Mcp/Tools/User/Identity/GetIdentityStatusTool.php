<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User\Identity;

use App\Enums\IdentityStatus;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取当前登录用户的实名认证简要状态
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.identity.status')]
#[Description('获取当前登录用户的实名认证简要状态：是否已通过、类型、最新审核结果与驳回原因。')]
#[IsReadOnly]
#[IsIdempotent]
class GetIdentityStatusTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->refresh();

        $latest = $user->latestIdentity()->first();

        return Response::structured([
            'is_verified' => $user->isVerified(),
            'identity_type' => $user->identity_type?->value,
            'identity_type_label' => $user->identity_type?->label(),
            'latest_status' => $user->identity_status->value,
            'latest_status_label' => $user->identity_status->label(),
            'latest_reject_reason' => $latest && $latest->status === IdentityStatus::REJECTED ? $latest->reject_reason : null,
            'latest_reject_remark' => $latest && $latest->status === IdentityStatus::REJECTED ? $latest->reject_remark : null,
        ]);
    }
}
