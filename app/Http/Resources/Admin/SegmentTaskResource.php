<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\UserSegment\SegmentTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * 分群异步任务资源
 *
 * @mixin SegmentTask
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admin_id' => $this->admin_id,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'total_count' => $this->total_count,
            'processed_count' => $this->processed_count,
            'added_count' => $this->added_count,
            'progress_percent' => $this->progressPercent(),
            'error_message' => $this->error_message,
            'started_at' => $this->started_at instanceof Carbon
                ? $this->started_at->toDateTimeString()
                : null,
            'finished_at' => $this->finished_at instanceof Carbon
                ? $this->finished_at->toDateTimeString()
                : null,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
