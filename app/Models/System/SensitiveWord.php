<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use App\Enums\SensitiveCategory;
use App\Enums\SensitiveLevel;
use App\Models\Model;
use Database\Factories\System\SensitiveWordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * 敏感词模型
 *
 * @property int $id ID
 * @property string $word 敏感词
 * @property SensitiveCategory $category 分类
 * @property SensitiveLevel $level 命中级别
 * @property string $replacement 替换字符
 * @property int $hit_count 命中次数
 * @property bool $status 是否启用
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('sensitive_words')]
#[Fillable(['word', 'category', 'level', 'replacement', 'hit_count', 'status'])]
class SensitiveWord extends Model
{
    /** @use HasFactory<SensitiveWordFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'category' => SensitiveCategory::OTHER,
        'level' => SensitiveLevel::REVIEW,
        'replacement' => '*',
        'hit_count' => 0,
        'status' => true,
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'word' => 'string',
            'category' => SensitiveCategory::class,
            'level' => SensitiveLevel::class,
            'replacement' => 'string',
            'hit_count' => 'integer',
            'status' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * 查询启用状态的敏感词
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', true);
    }
}
