<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\FeedbackStatus;
use App\Models\System\Feedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 用户反馈统计资源
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class FeedbackStatResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{total: int, pending: int, today: int, closed: int} $data */
        $data = $this->resource;

        return [
            'total' => $data['total'],
            'pending' => $data['pending'],
            'today' => $data['today'],
            'closed' => $data['closed'],
        ];
    }

    /**
     * 查询统计数据
     *
     * @return array{total: int, pending: int, today: int, closed: int}
     */
    public static function makeStats(): array
    {
        $pendingStatuses = [
            FeedbackStatus::PENDING->value,
            FeedbackStatus::ANALYZING->value,
            FeedbackStatus::PLANNED->value,
            FeedbackStatus::PROCESSING->value,
        ];
        $closedStatuses = [
            FeedbackStatus::RESOLVED->value,
            FeedbackStatus::CLOSED->value,
        ];

        return [
            'total' => Feedback::query()->count(),
            'pending' => Feedback::query()->whereIn('status', $pendingStatuses)->count(),
            'today' => Feedback::query()->whereDate('created_at', now()->toDateString())->count(),
            'closed' => Feedback::query()->whereIn('status', $closedStatuses)->count(),
        ];
    }
}
