<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User\Notification;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 标记所有未读通知为已读
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('notification.mark_all_read')]
#[Description('将当前登录用户的全部未读通知标记为已读。')]
class MarkAllNotificationsReadTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $count = $user->unreadNotifications()->update(['read_at' => Carbon::now()]);

        return Response::structured([
            'message' => 'All notifications marked as read.',
            'marked_count' => $count,
        ]);
    }
}
