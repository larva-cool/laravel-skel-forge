<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Content\Visit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 访问记录资源（Api 模块）
 *
 * @mixin Visit
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class VisitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'ip' => $this->ip,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
