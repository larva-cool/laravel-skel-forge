<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\System\Area;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 地区资源
 *
 * @mixin Area
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AreaResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'area_code' => $this->area_code,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'city_code' => $this->city_code,
            'sort' => $this->sort,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'children' => self::collection($this->whenLoaded('childrenRecursive')),
        ];
    }
}
