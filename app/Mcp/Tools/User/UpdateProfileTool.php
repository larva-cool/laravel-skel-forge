<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\User;

use App\Enums\Gender;
use App\Events\User\UserProfileUpdated;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\System\Area;
use App\Services\SensitiveWordService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * 修改当前登录用户的个人资料
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('user.profile.update')]
#[Description('修改当前登录用户的个人资料，包括昵称、生日、性别、地区、个人网站、介绍、签名等。')]
class UpdateProfileTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate([
            'name' => ['nullable', 'string', 'min:2', 'max:20'],
            'birthday' => ['sometimes', 'date'],
            'gender' => ['nullable', 'integer', new Enum(Gender::class)],
            'province_id' => ['nullable', 'integer', Rule::exists(Area::class, 'id')],
            'city_id' => ['nullable', 'integer', Rule::exists(Area::class, 'id')],
            'district_id' => ['nullable', 'integer', Rule::exists(Area::class, 'id')],
            'website' => ['nullable', 'url', 'max:200'],
            'intro' => ['nullable', 'string', 'max:200'],
            'bio' => ['nullable', 'string', 'max:100'],
        ]);

        // 敏感词处理
        $service = app(SensitiveWordService::class);
        foreach (['intro', 'bio'] as $field) {
            if (! empty($arguments[$field]) && is_string($arguments[$field])) {
                $result = $service->detect($arguments[$field]);
                $arguments[$field] = $result->filtered;
            }
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! empty($arguments['name'])) {
            $user->update(['name' => $arguments['name']]);
        }

        $profileData = array_filter($arguments, fn ($key) => $key !== 'name', ARRAY_FILTER_USE_KEY);
        if (! empty($profileData)) {
            $user->profile->update($profileData);
        }

        $user->refresh()->loadMissing('profile');
        UserProfileUpdated::dispatch($user);

        return Response::structured((new UserResource($user))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('昵称，2-20 字')->min(2)->max(20),
            'birthday' => $schema->string()->description('生日，格式 Y-m-d')->format('date'),
            'gender' => $schema->integer()->description('性别：0 保密 / 1 男 / 2 女'),
            'province_id' => $schema->integer()->description('省份 ID'),
            'city_id' => $schema->integer()->description('城市 ID'),
            'district_id' => $schema->integer()->description('区县 ID'),
            'website' => $schema->string()->description('个人网站 URL')->max(200)->format('uri'),
            'intro' => $schema->string()->description('个人介绍，最多 200 字')->max(200),
            'bio' => $schema->string()->description('个性签名，最多 100 字')->max(100),
        ];
    }
}
