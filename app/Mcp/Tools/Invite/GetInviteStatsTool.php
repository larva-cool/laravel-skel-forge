<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Invite;

use App\Models\Invite\InviteRecord;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取当前登录用户的邀请统计
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('invite.stats.get')]
#[Description('获取当前登录用户的邀请统计：累计邀请人数、是否已有邀请人。')]
#[IsReadOnly]
#[IsIdempotent]
class GetInviteStatsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $total = InviteRecord::query()
            ->where('inviter_id', $user->getKey())
            ->where('inviter_type', $user->getMorphClass())
            ->count();

        $hasInviter = InviteRecord::query()
            ->where('invitee_id', $user->getKey())
            ->where('invitee_type', $user->getMorphClass())
            ->exists();

        return Response::structured([
            'invitee_count' => $total,
            'has_inviter' => $hasInviter,
        ]);
    }
}
