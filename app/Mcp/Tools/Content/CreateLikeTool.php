<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Content;

use App\Http\Resources\Api\V1\LikeResource;
use App\Models\Content\Like;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 添加点赞
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('content.like.create')]
#[Description('为指定对象添加点赞；同一用户对同一对象只能点赞一次，重复点赞返回错误。')]
class CreateLikeTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'source_type' => ['required', 'string', 'max:64'],
            'source_id' => ['required', 'integer', 'min:1'],
            'extra' => ['nullable', 'array'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // 唯一性检查
        $exists = Like::query()
            ->where('user_id', $user->id)
            ->where('source_type', $arguments['source_type'])
            ->where('source_id', $arguments['source_id'])
            ->exists();

        if ($exists) {
            return Response::error('Already liked.');
        }

        $like = Like::create([
            'user_id' => $user->id,
            'source_type' => $arguments['source_type'],
            'source_id' => $arguments['source_id'],
            'extra' => $arguments['extra'] ?? null,
        ]);

        return Response::structured((new LikeResource($like))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source_type' => $schema->string()->description('点赞对象类型（morph 别名），如 post、comment')->max(64)->required(),
            'source_id' => $schema->integer()->description('点赞对象 ID')->min(1)->required(),
            'extra' => $schema->object()->description('可选，扩展信息'),
        ];
    }
}
