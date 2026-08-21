<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use App\Enums\AttachmentType;
use App\Models\Model;
use Database\Factories\System\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * 附件模型
 *
 * @property int $id 附件ID
 * @property int|null $uploader_id 上传者ID
 * @property string|null $uploader_type 上传者类型
 * @property string $disk 存储磁盘
 * @property string $path 存储路径
 * @property string $name 显示名称
 * @property string $original_name 原始文件名
 * @property string $extension 扩展名
 * @property string $mime_type MIME类型
 * @property AttachmentType $type 附件类型
 * @property int $size 文件字节数
 * @property string|null $hash 文件MD5
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 * @property-read string $size_text 人类可读的文件大小
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
#[Table('attachments')]
#[Fillable(['uploader_id', 'uploader_type', 'disk', 'path', 'name', 'original_name', 'extension', 'mime_type', 'type', 'size', 'hash'])]
#[Hidden(['uploader_id', 'uploader_type'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * 允许存放附件的路径前缀
     */
    public const string PATH_PREFIX = 'uploads/';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'uploader_id' => 'integer',
            'uploader_type' => 'string',
            'disk' => 'string',
            'path' => 'string',
            'name' => 'string',
            'original_name' => 'string',
            'extension' => 'string',
            'mime_type' => 'string',
            'type' => AttachmentType::class,
            'size' => 'integer',
            'hash' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * 上传者
     */
    public function uploader(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 人类可读的文件大小
     */
    public function getSizeTextAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $this->size;
        $index = 0;
        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return ($index === 0 ? (string) (int) $size : number_format($size, 2)).' '.$units[$index];
    }

    /**
     * 获取该附件所属磁盘的文件系统实例
     */
    public function storage(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * 列表筛选
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['type']), fn (Builder $q) => $q->where('type', $filters['type']))
            ->when(! empty($filters['disk']), fn (Builder $q) => $q->where('disk', $filters['disk']))
            ->when(! empty($filters['extension']), fn (Builder $q) => $q->where('extension', $filters['extension']))
            ->when(! empty($filters['uploader_id']), fn (Builder $q) => $q->where('uploader_id', $filters['uploader_id']))
            ->when(! empty($filters['keyword']), function (Builder $q) use ($filters) {
                $keyword = '%'.$filters['keyword'].'%';
                $q->where(function (Builder $sub) use ($keyword) {
                    $sub->where('name', 'like', $keyword)->orWhere('original_name', 'like', $keyword);
                });
            })
            ->when(! empty($filters['start_date']), fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay()))
            ->when(! empty($filters['end_date']), fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay()));
    }
}
