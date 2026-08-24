<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\System\DictItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 公开字典项资源（仅暴露前端需要的字段）
 *
 * @mixin DictItem
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class PublicDictItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'label_type' => $this->label_type ?: 'default',
        ];
    }
}
