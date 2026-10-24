<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User\Notification;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * 清空已读通知
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('notification.clear_read')]
#[Description('清空当前登录用户的全部已读通知（不可恢复）。')]
#[IsDestructive]
class ClearReadNotificationsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $count = $user->readNotifications()->delete();

        return Response::structured([
            'message' => 'Read notifications cleared successfully.',
            'cleared_count' => $count,
        ]);
    }
}
