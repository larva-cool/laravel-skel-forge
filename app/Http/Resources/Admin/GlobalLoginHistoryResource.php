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
 * 全局登录历史资源
 *
 * 与 LoginHistoryResource 的区别：额外输出归属主体（guard、user_id、user_name），
 * 用于跨管理员与前台用户的全局登录日志列表。
 *
 * @mixin LoginHistory
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class GlobalLoginHistoryResource extends JsonResource
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
            'guard' => $this->user_type,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name ?? $this->user?->username),
            'result' => $this->result,
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
