<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\System\SensitiveWord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SensitiveWord
 */
class SensitiveWordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'word' => $this->word,
            'category' => $this->category?->value,
            'category_label' => $this->category?->label(),
            'level' => $this->level?->value,
            'level_label' => $this->level?->label(),
            'replacement' => $this->replacement,
            'hit_count' => $this->hit_count,
            'status' => $this->status,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
