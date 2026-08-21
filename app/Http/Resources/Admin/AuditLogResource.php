<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\System\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 审计日志资源
 *
 * @mixin AuditLog
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AuditLogResource extends JsonResource
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
            'operator_id' => $this->operator_id,
            'operator_name' => $this->operator_name,
            'route' => $this->route,
            'method' => $this->method,
            'uri' => $this->uri,
            'title' => $this->title,
            'payload' => $this->payload,
            'status_code' => $this->status_code,
            'is_failed' => $this->isFailed(),
            'error' => $this->error,
            'runtime' => $this->runtime,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
