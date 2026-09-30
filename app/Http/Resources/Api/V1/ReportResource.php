<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\CacheKey;
use App\Models\Content\Report;
use App\Models\System\Dict;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * 举报资源（用户端）
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

        return [
            'id' => $this->id,
            'category' => $this->category,
            'category_label' => $categoryMap[$this->category]['label'] ?? $this->category,
            'reason' => $this->reason,
            'evidence' => $this->evidence ?? [],
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'target_type' => $this->targetAlias(),
            'target_id' => $this->reportable_id,
            'processed_at' => $this->processed_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }

    /**
     * 返回被举报对象 morph alias（comment / user）
     *
     * enforceMorphMap 下 reportable_type 存储的本身就是 alias；兼容历史全限定类名存储时反查 alias。
     */
    protected function targetAlias(): ?string
    {
        $type = $this->reportable_type;
        if (! $type) {
            return null;
        }

        // 本身就是 morph alias（enforceMorphMap 下的常态）
        if (Relation::getMorphedModel($type)) {
            return $type;
        }

        // 兼容全限定类名存储：类名反查 alias
        $alias = array_search($type, Relation::morphMap(), true);

        return $alias !== false ? (string) $alias : $type;
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
}
