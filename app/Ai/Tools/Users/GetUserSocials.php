<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Users;

use App\Models\System\Social;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 查询前台用户已绑定的社交账号工具。
 *
 * 只读操作，返回第三方账号的提供商、OpenID/UnionID 摘要、绑定时间和令牌状态，
 * 不返回 access_token / refresh_token 明文。
 */
class GetUserSocials implements Tool
{
    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询指定前台用户已绑定的第三方社交账号（微信公众号/应用/小程序、Apple、抖音、快手、小红书）。返回渠道名称、OpenID/UnionID 摘要、绑定时间和令牌是否有效，不返回令牌明文。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->required()
                ->description('目标用户 ID'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        $user = User::query()->find($request->integer('id'));
        if (! $user) {
            return '未找到目标用户。';
        }

        $socials = Social::query()
            ->ofUserType($user)
            ->orderByDesc('updated_at')
            ->get();

        if ($socials->isEmpty()) {
            return "用户 [{$user->id}] {$user->name} 尚未绑定任何第三方社交账号。";
        }

        return json_encode([
            'user_id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'social_count' => $socials->count(),
            'socials' => $socials->map(function (Social $social) {
                $hasToken = ! empty($social->access_token);
                $isExpired = $social->expiry_at && $social->expiry_at->isPast();

                return [
                    'provider' => $social->provider->value,
                    'provider_label' => $social->provider->label(),
                    'openid' => $this->maskIdentifier($social->openid),
                    'unionid' => $social->unionid ? $this->maskIdentifier($social->unionid) : null,
                    'token_status' => match (true) {
                        ! $hasToken => '无令牌',
                        $isExpired => '已过期',
                        default => '有效',
                    },
                    'bound_at' => $social->created_at?->toDateTimeString(),
                    'updated_at' => $social->updated_at?->toDateTimeString(),
                ];
            })->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 对 OpenID / UnionID 做脱敏，仅保留首尾少量字符。
     */
    protected function maskIdentifier(string $value): string
    {
        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4).str_repeat('*', 8).substr($value, -4);
    }
}
