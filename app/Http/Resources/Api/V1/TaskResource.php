<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\Task\TaskClaimMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 任务资源
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $progress = $this->getAttribute('user_progress');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'description' => $this->description,
            'condition' => $this->condition,
            'reward_type' => $this->reward_type->value,
            'reward_type_label' => $this->reward_type->label(),
            'reward_amount' => $this->reward_amount,
            'claim_mode' => $this->claim_mode->value,
            'claim_mode_label' => $this->claim_mode->label(),
            'sort_order' => $this->sort_order,
            'target_value' => $this->getTargetValue(),
            'progress' => [
                'current_value' => $progress?->current_value ?? 0,
                'status' => $progress?->status?->value ?? 0,
                'status_label' => $progress?->status?->label() ?? '未开始',
                'is_completed' => $progress?->isCompleted() ?? false,
                'is_rewarded' => $progress?->isRewarded() ?? false,
                'can_claim' => $progress?->status?->value === 1 && $this->claim_mode === TaskClaimMode::MANUAL,
                'period_key' => $this->getAttribute('period_key') ?? 'all',
                'completed_at' => $progress?->completed_at?->toDateTimeString(),
                'rewarded_at' => $progress?->rewarded_at?->toDateTimeString(),
            ],
        ];
    }

    /**
     * 获取任务目标值（基于 condition 推断）
     */
    protected function getTargetValue(): int
    {
        $condition = $this->condition;

        if ($this->type->value === 'complete_profile') {
            return (int) ($condition['completeness_threshold'] ?? 100);
        }

        if ($this->type->value === 'sign_in' && (int) ($condition['serial_days'] ?? 0) > 0) {
            return (int) $condition['serial_days'];
        }

        if ($this->type->value === 'sign_in') {
            return 1;
        }

        return (int) ($condition['target_value'] ?? 1);
    }
}
