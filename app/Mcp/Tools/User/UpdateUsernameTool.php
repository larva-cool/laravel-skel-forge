<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 修改当前登录用户的用户名
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.username.update')]
#[Description('修改当前登录用户的用户名，需全局唯一且符合用户名规范。')]
class UpdateUsernameTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'username' => ['required', 'string', 'max:20', Rule::unique(User::class)->ignore($request->user()->id)],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->resetUsername($arguments['username']);

        return Response::structured(['message' => 'Username updated successfully.']);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'username' => $schema->string()->description('新用户名，最多 20 字')->max(20)->required(),
        ];
    }
}
