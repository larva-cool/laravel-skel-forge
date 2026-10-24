<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Content;

use App\Models\Content\Follow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 取消关注
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('content.follow.delete')]
#[Description('取消当前登录用户对指定对象的关注。')]
class DeleteFollowTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'source_type' => ['required', 'string', 'max:64'],
            'source_id' => ['required', 'integer', 'min:1'],
        ]);

        $deleted = Follow::query()
            ->where('user_id', $request->user()->id)
            ->where('source_type', $arguments['source_type'])
            ->where('source_id', $arguments['source_id'])
            ->delete();

        if (! $deleted) {
            return Response::error('Follow not found.');
        }

        return Response::structured(['message' => 'Follow removed successfully.']);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source_type' => $schema->string()->description('关注对象类型（morph 别名）')->max(64)->required(),
            'source_id' => $schema->integer()->description('关注对象 ID')->min(1)->required(),
        ];
    }
}
