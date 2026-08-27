<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Operation\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Banner 前台公开资源
 *
 * @mixin Banner
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class BannerPublicResource extends JsonResource
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
            'title' => $this->title,
            'image' => $this->image,
            'link_type' => $this->link_type?->value,
            'link_value' => $this->link_value,
            'sort' => $this->sort,
        ];
    }
}
