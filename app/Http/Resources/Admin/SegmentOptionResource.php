<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\UserSegment\Segment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 分群轻量下拉选项资源（公告群发等场景复用）
 *
 * @mixin Segment
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label_type' => $this->label_type ?: 'default',
            'type' => $this->type?->value,
        ];
    }
}
