<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\CacheKey;
use App\Models\System\Dict;
use App\Models\System\Feedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * 用户反馈资源
 *
 * @mixin Feedback
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class FeedbackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $typeMap = static::getTypeMap();
        $typeItem = $typeMap[$this->type] ?? null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'content' => $this->when($request->routeIs('admin.feedbacks.show'), $this->content),
            'images' => $this->images ?? [],
            'type' => $this->type,
            'type_label' => $typeItem['label'] ?? $this->type,
            'type_label_type' => $typeItem['label_type'] ?? 'default',
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_label_type' => $this->status ? match ($this->status) {
                \App\Enums\FeedbackStatus::PENDING => 'info',
                \App\Enums\FeedbackStatus::ANALYZING => 'warning',
                \App\Enums\FeedbackStatus::PLANNED => 'primary',
                \App\Enums\FeedbackStatus::PROCESSING => 'warning',
                \App\Enums\FeedbackStatus::RESOLVED => 'success',
                \App\Enums\FeedbackStatus::CLOSED => 'info',
            } : 'default',
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'priority_label_type' => $this->priority?->labelType() ?? 'default',
            'contact_name' => $this->contact_name,
            'contact_info' => $this->contact_info,
            'user_agent' => $this->when($request->routeIs('admin.feedbacks.show'), $this->user_agent),
            'ip_address' => $this->when($request->routeIs('admin.feedbacks.show'), $this->ip_address),
            'user_id' => $this->user_id,
            'admin_id' => $this->admin_id,
            'admin_name' => $this->whenLoaded('admin', fn () => $this->admin?->name),
            'admin_remark' => $this->admin_remark,
            'resolved_at' => $this->resolved_at?->toDateTimeString(),
            'closed_at' => $this->closed_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * 获取反馈类型字典映射（带缓存）
     *
     * @return array<string, array{label: string, label_type: string}>
     */
    public static function getTypeMap(): array
    {
        // 使用独立缓存键，避免与 DictController::itemsByCode 缓存的完整字典项数组冲突
        $cacheKey = CacheKey::key(CacheKey::DICT_TYPE, 'feedback_type').':map';

        /** @var array<string, array{label: string, label_type: string}>|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $dict = Dict::query()
            ->where('code', 'feedback_type')
            ->where('status', 1)
            ->with(['dictItems' => fn ($q) => $q->where('status', 1)->orderBy('sort')->orderBy('id')])
            ->first();

        $map = [];
        foreach ($dict?->dictItems ?? [] as $item) {
            $map[$item->value] = [
                'label' => $item->label,
                'label_type' => $item->label_type ?: 'default',
            ];
        }

        Cache::put($cacheKey, $map, Carbon::now()->addHours(2));

        return $map;
    }

    /**
     * 清除反馈类型字典缓存
     */
    public static function flushTypeMapCache(): void
    {
        Cache::forget(CacheKey::key(CacheKey::DICT_TYPE, 'feedback_type').':map');
    }
}
