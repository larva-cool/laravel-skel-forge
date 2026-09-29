<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\System\Social;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 前台用户社交账号绑定资源
 *
 * 仅返回当前登录用户本人可见的字段，对内部敏感字段（access_token/refresh_token 等）
 * 不做暴露，openid 按渠道部分脱敏展示。
 *
 * @mixin Social
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class SocialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'provider_label' => $this->provider?->label(),
            'openid_masked' => $this->maskOpenid($this->openid),
            'unionid_masked' => $this->maskUnionid($this->unionid),
            'expiry_at' => $this->expiry_at?->toDateTimeString(),
            'bound_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    /**
     * openid 部分脱敏：保留前 4 位 + **** + 后 2 位，长度不足时整体打码。
     */
    protected function maskOpenid(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $length = mb_strlen($value);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 4).'****'.mb_substr($value, -2);
    }

    /**
     * unionid 脱敏：保留前 4 位 + **** + 后 2 位，长度不足时整体打码。
     */
    protected function maskUnionid(?string $value): ?string
    {
        return $this->maskOpenid($value);
    }
}
