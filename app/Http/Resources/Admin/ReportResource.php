<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\CacheKey;
use App\Models\Content\Comment;
use App\Models\Content\Report;
use App\Models\System\Dict;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 举报资源（管理端）
 *
 * @mixin Report
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $categoryMap = static::getCategoryMap();
        $categoryItem = $categoryMap[$this->category] ?? null;
        $isShowRoute = $request->routeIs('admin.reports.show');

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'username' => $this->whenLoaded('user', fn () => $this->user?->username),
            'nickname' => $this->whenLoaded('user', fn () => $this->user?->nickname),
            'category' => $this->category,
            'category_label' => $categoryItem['label'] ?? $this->category,
            'category_label_type' => $categoryItem['label_type'] ?? 'default',
            'reason' => $this->when($isShowRoute, $this->reason),
            'evidence' => $this->when($isShowRoute, $this->evidence ?? []),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_label_type' => $this->status?->labelType() ?? 'default',
            'reportable_type' => $this->reportable_type,
            'reportable_type_label' => $this->humanReportableType(),
            'reportable_id' => $this->reportable_id,
            'reportable_snapshot' => $this->when($isShowRoute, fn () => $this->reportableSnapshot()),
            'handler_id' => $this->handler_id,
            'handler_name' => $this->whenLoaded('handler', fn () => $this->handler?->name),
            'process_remark' => $this->when($isShowRoute, $this->process_remark),
            'process_actions' => $this->when($isShowRoute, $this->process_actions ?? []),
            'processed_at' => $this->processed_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    /**
     * 被举报对象人类可读类型
     */
    protected function humanReportableType(): string
    {
        $type = $this->reportable_type;
        if (! $type) {
            return '';
        }
        $alias = (new Report)->getMorphClass();
        if ($type === Comment::class || str_ends_with($type, '\\Comment')) {
            return '评论';
        }
        if ($type === User::class || str_ends_with($type, '\\User')) {
            return '用户';
        }

        return class_basename($type);
    }

    /**
     * 被举报对象快照（用于后台展示违规内容）
     *
     * @return array<string, mixed>|null
     */
    protected function reportableSnapshot(): ?array
    {
        $target = $this->reportable;
        if ($target === null) {
            return null;
        }

        if ($target instanceof Comment) {
            return [
                'type' => 'comment',
                'id' => $target->id,
                'user_id' => $target->user_id,
                'username' => $target->user?->username,
                'content' => $target->content,
                'status' => $target->status?->value,
                'status_label' => $target->status?->label(),
                'created_at' => $target->created_at?->toDateTimeString(),
            ];
        }

        if ($target instanceof User) {
            return [
                'type' => 'user',
                'id' => $target->id,
                'username' => $target->username,
                'nickname' => $target->nickname,
                'avatar' => $target->avatar,
                'status' => $target->status?->value,
                'status_label' => $target->status?->label(),
                'registered_at' => $target->created_at?->toDateTimeString(),
            ];
        }

        return [
            'type' => Str::lower(class_basename($target)),
            'id' => $target->getKey(),
        ];
    }

    /**
     * 举报分类字典映射（value => [label, label_type]），缓存 2 小时
     *
     * @return array<string, array{label: string, label_type: string}>
     */
    public static function getCategoryMap(): array
    {
        $cacheKey = CacheKey::key(CacheKey::DICT_TYPE, 'report_category').':map';

        /** @var array<string, array{label: string, label_type: string}>|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $dict = Dict::query()
            ->where('code', 'report_category')
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
     * 清除举报分类字典缓存
     */
    public static function flushCategoryMapCache(): void
    {
        Cache::forget(CacheKey::key(CacheKey::DICT_TYPE, 'report_category').':map');
    }
}
