<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Auth;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * 撤销指定的访问令牌
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('auth.token.delete')]
#[Description('撤销当前登录用户名下指定 ID 的访问令牌；若撤销当前使用的令牌，将立即失效。')]
#[IsDestructive]
class DeleteTokenTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $deleted = $user->tokens()->where('id', $arguments['id'])->delete();

        if (! $deleted) {
            return Response::error('Token not found.');
        }

        return Response::structured(['message' => 'Token revoked successfully.']);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('令牌 ID')->min(1)->required(),
        ];
    }
}
