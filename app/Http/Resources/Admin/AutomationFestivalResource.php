<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Automation\AutomationFestival;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 自动化节日后台管理资源
 *
 * @mixin AutomationFestival
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationFestivalResource extends JsonResource
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
            'name' => $this->name,
            'date_type' => $this->date_type?->value,
            'date_type_label' => $this->date_type?->label(),
            'date_type_label_type' => $this->date_type?->labelType(),
            'month' => $this->month,
            'day' => $this->day,
            'status' => $this->status,
            'sort' => $this->sort,
            'remark' => $this->remark,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
