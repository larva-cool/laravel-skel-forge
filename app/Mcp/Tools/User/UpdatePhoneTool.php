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
 * 修改当前登录用户的手机号
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.phone.update')]
#[Description('修改当前登录用户的手机号，需通过短信验证码验证。')]
class UpdatePhoneTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'phone' => ['required', 'string', 'max:20', Rule::unique(User::class)->ignore($request->user()->id)],
            'verify_code' => ['required', 'string', 'min:4', 'max:6'],
        ]);

        // 验证码校验
        $valid = \App\Models\System\PhoneCode::query()
            ->where('phone', $arguments['phone'])
            ->where('code', $arguments['verify_code'])
            ->where('expired_at', '>', now())
            ->exists();

        if (! $valid) {
            return Response::error('Invalid or expired verification code.');
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->resetPhone($arguments['phone']);

        return Response::structured(['message' => 'Phone updated successfully.']);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'phone' => $schema->string()->description('新手机号')->max(20)->required(),
            'verify_code' => $schema->string()->description('短信验证码')->min(4)->max(6)->required(),
        ];
    }
}
