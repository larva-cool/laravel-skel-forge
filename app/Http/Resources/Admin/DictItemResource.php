<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\System\DictItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 字典数据资源
 *
 * @mixin DictItem
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class DictItemResource extends JsonResource
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
            'dict_id' => $this->dict_id,
            'label' => $this->label,
            'value' => $this->value,
            'label_type' => $this->label_type,
            'status' => $this->status,
            'sort' => $this->sort,
            'remark' => $this->remark,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
