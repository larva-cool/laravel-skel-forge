<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use App\Enums\CacheKey;
use App\Models\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * 字典数据模型
 *
 * @property int $id 字典项ID
 * @property int $dict_id 字典ID
 * @property string $label 字典项标签
 * @property string $value 字典项值
 * @property string $label_type 标签样式
 * @property int $status 状态：1启用 0禁用
 * @property int $sort 排序权重，越小越靠前
 * @property string|null $remark 备注
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 *
 * 关系模型：
 * @property Dict $dict 字典类型
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('dict_items')]
#[Fillable(['dict_id', 'label', 'value', 'label_type', 'status', 'sort', 'remark'])]
class DictItem extends Model
{
    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'label_type' => 'default',
        'status' => 1,
        'sort' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'dict_id' => 'integer',
            'label' => 'string',
            'value' => 'string',
            'label_type' => 'string',
            'status' => 'integer',
            'sort' => 'integer',
            'remark' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        parent::booted();

        // 字典项变更时清除对应字典缓存
        $flushCache = function (self $item): void {
            $dict = $item->dict;
            if ($dict) {
                Cache::forget(CacheKey::key(CacheKey::DICT_TYPE, $dict->code));
            }
        };

        static::saved($flushCache);
        static::deleted($flushCache);
    }

    /**
     * 字典类型
     */
    public function dict(): BelongsTo
    {
        return $this->belongsTo(Dict::class, 'dict_id', 'id');
    }
}
