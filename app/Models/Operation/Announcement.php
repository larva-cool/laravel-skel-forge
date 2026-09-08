<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Operation;

use App\Enums\AnnouncementStatus;
use App\Models\Admin\Admin;
use App\Models\Model;
use Database\Factories\Operation\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * 公告模型
 *
 * @property int $id 公告ID
 * @property string $title 标题
 * @property string|null $summary 摘要
 * @property string $content 正文（富文本 HTML）
 * @property string|null $cover_image 封面图 URL
 * @property AnnouncementStatus $status 状态
 * @property int $sort 排序权重
 * @property int $admin_id 创建人管理员ID
 * @property Carbon|null $published_at 发布时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 * @property-read Admin $admin 创建人
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('announcements')]
#[Fillable(['title', 'summary', 'content', 'cover_image', 'status', 'sort', 'admin_id', 'published_at'])]
class Announcement extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @use HasFactory<AnnouncementFactory> */
    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => AnnouncementStatus::DRAFT,
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
            'title' => 'string',
            'summary' => 'string',
            'content' => 'string',
            'cover_image' => 'string',
            'status' => AnnouncementStatus::class,
            'sort' => 'integer',
            'admin_id' => 'integer',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        parent::booted();

        // 状态变更为 published 时自动写入发布时间
        static::updating(function (self $announcement): void {
            $originalStatus = $announcement->getOriginal('status');
            if ($announcement->status === AnnouncementStatus::PUBLISHED
                && ! ($originalStatus instanceof AnnouncementStatus && $originalStatus->isPublished())
                && $announcement->published_at === null
            ) {
                $announcement->published_at = now();
            }
        });

        // 创建时如果直接是 published 状态，写入发布时间
        static::creating(function (self $announcement): void {
            if ($announcement->status === AnnouncementStatus::PUBLISHED && $announcement->published_at === null) {
                $announcement->published_at = now();
            }
        });
    }

    /**
     * 创建人
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
