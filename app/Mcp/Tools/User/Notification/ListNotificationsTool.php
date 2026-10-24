<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User\Notification;

use App\Http\Resources\Api\V1\NotificationResource;
use App\Mcp\Tools\Concerns\BuildsPaginatedResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取当前登录用户的通知列表
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.notifications.list')]
#[Description('获取当前登录用户的通知列表（分页），可按通知类型 type 过滤。')]
#[IsReadOnly]
#[IsIdempotent]
class ListNotificationsTool extends Tool
{
    use BuildsPaginatedResponse;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate(array_merge($this->paginationRules(), [
            'type' => ['sometimes', 'string', 'max:128'],
        ]));

        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = $user->notifications();

        if (! empty($arguments['type'])) {
            $query->where('type', $arguments['type']);
        }

        $paginator = $query->paginate($this->resolvePerPage($arguments), ['*'], 'page', $this->resolvePage($arguments));

        return Response::structured($this->paginatedResult($paginator, NotificationResource::class));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return array_merge($this->paginationSchema($schema), [
            'type' => $schema->string()->description('可选，通知类型（通知类完整类名）')->max(128),
        ]);
    }
}
