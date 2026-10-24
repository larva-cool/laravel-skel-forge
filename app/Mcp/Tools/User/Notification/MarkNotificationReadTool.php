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
 * 标记指定通知为已读
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('notification.mark_read')]
#[Description('将当前登录用户指定的未读通知标记为已读。')]
class MarkNotificationReadTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate([
            'id' => ['required', 'string', 'max:64'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $count = $user->unreadNotifications()
            ->where('id', $arguments['id'])
            ->update(['read_at' => Carbon::now()]);

        return Response::structured([
            'message' => $count ? 'Notification marked as read.' : 'Notification not found or already read.',
            'marked_count' => $count,
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('通知 ID（UUID）')->max(64)->required(),
        ];
    }
}
