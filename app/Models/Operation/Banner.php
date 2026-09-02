<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Operation;

use App\Enums\LinkType;
use App\Models\Model;
use Database\Factories\Operation\BannerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * Banner / 轮播图模型
 *
 * @property int $id Banner ID
 * @property string $position 广告位标识（对应字典 banner_positions）
 * @property string $title 标题
 * @property string $image 图片 URL
 * @property LinkType $link_type 链接类型
 * @property string|null $link_value 链接值
 * @property int $sort 排序权重
 * @property bool $status 启用状态
 * @property Carbon|null $start_at 生效开始时间
 * @property Carbon|null $end_at 生效结束时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('banners')]
#[Fillable(['position', 'title', 'image', 'link_type', 'link_value', 'sort', 'status', 'start_at', 'end_at'])]
class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory;

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'sort' => 1000,
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
            'position' => 'string',
            'title' => 'string',
            'image' => 'string',
            'link_type' => LinkType::class,
            'link_value' => 'string',
            'sort' => 'integer',
            'status' => 'boolean',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
