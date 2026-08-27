<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\CacheKey;
use App\Models\Operation\Banner;
use App\Models\System\Dict;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Banner 后台管理资源
 *
 * @mixin Banner
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class BannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $positionMap = static::getPositionMap();

        return [
            'id' => $this->id,
            'position' => $this->position,
            'position_label' => $positionMap[$this->position]['label'] ?? $this->position,
            'title' => $this->title,
            'image' => $this->image,
            'link_type' => $this->link_type?->value,
            'link_type_label' => $this->link_type?->label(),
            'link_value' => $this->link_value,
            'sort' => $this->sort,
            'status' => $this->status,
            'start_at' => $this->start_at?->toDateTimeString(),
            'end_at' => $this->end_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * 获取广告位字典映射（带缓存）
     *
     * @return array<string, array{label: string, label_type: string}>
     */
    public static function getPositionMap(): array
    {
        $cacheKey = CacheKey::key(CacheKey::DICT_TYPE, 'banner_positions').':map';

        /** @var array<string, array{label: string, label_type: string}>|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $dict = Dict::query()
            ->where('code', 'banner_positions')
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
     * 清除广告位字典缓存
     */
    public static function flushPositionMapCache(): void
    {
        Cache::forget(CacheKey::key(CacheKey::DICT_TYPE, 'banner_positions').':map');
    }
}
