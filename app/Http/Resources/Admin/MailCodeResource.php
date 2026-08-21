<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\System\MailCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 邮件验证码资源
 *
 * @mixin MailCode
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class MailCodeResource extends JsonResource
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
            'email' => $this->email,
            'code' => $this->code,
            'ip' => $this->ip,
            'state' => $this->state,
            'state_text' => $this->state === MailCode::USED_STATE ? '已使用' : '未使用',
            'verify_count' => $this->verify_count,
            'send_at' => $this->send_at?->toDateTimeString(),
            'usage_at' => $this->usage_at?->toDateTimeString(),
        ];
    }
}
