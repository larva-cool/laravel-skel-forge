<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\System\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 登录历史资源
 *
 * @mixin LoginHistory
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class LoginHistoryResource extends JsonResource
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
            'ip' => $this->ip,
            'port' => $this->port,
            'platform' => $this->platform,
            'device' => $this->device,
            'browser' => $this->browser,
            'user_agent' => $this->user_agent,
            'address' => $this->address,
            'login_at' => $this->login_at?->toDateTimeString(),
        ];
    }
}
