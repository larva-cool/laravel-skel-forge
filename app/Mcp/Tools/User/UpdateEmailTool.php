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
 * 修改当前登录用户的邮箱
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.email.update')]
#[Description('修改当前登录用户的邮箱地址，需通过邮件验证码验证。')]
class UpdateEmailTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($request->user()->id)],
            'verify_code' => ['required', 'string', 'min:4', 'max:7'],
        ]);

        // 验证码校验
        $valid = \App\Models\System\MailCode::query()
            ->where('email', $arguments['email'])
            ->where('code', $arguments['verify_code'])
            ->where('expired_at', '>', now())
            ->exists();

        if (! $valid) {
            return Response::error('Invalid or expired verification code.');
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->resetEmail($arguments['email']);

        return Response::structured(['message' => 'Email updated successfully.']);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'email' => $schema->string()->description('新邮箱地址')->format('email')->max(255)->required(),
            'verify_code' => $schema->string()->description('邮件验证码')->min(4)->max(7)->required(),
        ];
    }
}
