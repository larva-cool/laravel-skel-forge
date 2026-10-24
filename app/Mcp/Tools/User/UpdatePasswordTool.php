<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 修改当前登录用户的密码
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.password.update')]
#[Description('修改当前登录用户的登录密码，需提供旧密码验证身份。')]
class UpdatePasswordTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'old_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! Hash::check($arguments['old_password'], $user->password)) {
            return Response::error('Old password is incorrect.');
        }

        $user->resetPassword($arguments['password']);

        return Response::structured(['message' => 'Password updated successfully.']);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'old_password' => $schema->string()->description('当前密码')->required(),
            'password' => $schema->string()->description('新密码（需符合密码强度要求）')->required(),
        ];
    }
}
