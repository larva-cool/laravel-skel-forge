<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\System\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 登录历史响应
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
            'user_agent' => $this->user_agent,
            'address' => $this->address,
            'browser' => $this->browser,
            'login_at' => $this->login_at?->toDateTimeString(),
        ];
    }
}
