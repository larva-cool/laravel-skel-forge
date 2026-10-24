<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Content;

use App\Http\Resources\Api\V1\FollowResource;
use App\Models\Content\Follow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 添加关注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('content.follow.create')]
#[Description('关注指定对象；同一用户对同一对象只能关注一次，重复关注返回错误。')]
class CreateFollowTool extends Tool
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

        $exists = Follow::query()
            ->where('user_id', $user->id)
            ->where('source_type', $arguments['source_type'])
            ->where('source_id', $arguments['source_id'])
            ->exists();

        if ($exists) {
            return Response::error('Already followed.');
        }

        $follow = Follow::create([
            'user_id' => $user->id,
            'source_type' => $arguments['source_type'],
            'source_id' => $arguments['source_id'],
            'extra' => $arguments['extra'] ?? null,
        ]);

        return Response::structured((new FollowResource($follow))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source_type' => $schema->string()->description('关注对象类型（morph 别名），如 user、post')->max(64)->required(),
            'source_id' => $schema->integer()->description('关注对象 ID')->min(1)->required(),
            'extra' => $schema->object()->description('可选，扩展信息'),
        ];
    }
}
