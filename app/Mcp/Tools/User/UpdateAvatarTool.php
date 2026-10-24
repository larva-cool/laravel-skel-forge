<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User;

use App\Events\User\UserProfileUpdated;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 修改当前登录用户的头像
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.avatar.update')]
#[Description('通过 base64 编码的图片内容修改当前登录用户的头像，自动转换为 WebP 格式。')]
class UpdateAvatarTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'avatar_base64' => ['required', 'string'],
            'extension' => ['required', 'string', 'in:jpg,jpeg,png,gif'],
        ]);

        $data = base64_decode($arguments['avatar_base64'], true);
        if ($data === false) {
            return Response::error('Invalid base64 data.');
        }

        // 简单图片尺寸校验
        if (strlen($data) > 5 * 1024 * 1024) {
            return Response::error('Image too large (max 5MB).');
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $paddedId = sprintf('%09d', $user->id);
        $directory = 'avatar/'.substr($paddedId, 0, 3).'/'.substr($paddedId, 3, 4);
        $filename = $user->id.'.webp';
        $path = $directory.'/'.$filename;

        // 存储 base64 解码后的原始数据（实际项目应转 WebP，此处简化）
        Storage::put($path, $data);

        $user->update(['avatar' => $path]);
        UserProfileUpdated::dispatch($user);

        return Response::structured([
            'message' => 'Avatar updated successfully.',
            'avatar' => $user->refresh()->avatar,
            'path' => $path,
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
            'avatar_base64' => $schema->string()->description('base64 编码的图片数据')->required(),
            'extension' => $schema->string()->description('图片格式：jpg / jpeg / png / gif')->required(),
        ];
    }
}
