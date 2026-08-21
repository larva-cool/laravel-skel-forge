<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Point\PointTrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 积分交易记录资源
 *
 * @mixin PointTrade
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class PointResource extends JsonResource
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
            'type' => $this->type,
            'type_label' => $this->type_label,
            'points' => $this->points,
            'description' => $this->description,
            'created_at' => $this->created_at->toDateTimeString(),
            'expired_at' => $this->expired_at?->toDateTimeString(),
        ];
    }
}
