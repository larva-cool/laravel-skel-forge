<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\App;

use App\Enums\AppPlatform;
use App\Enums\ReleaseStatus;
use App\Models\Admin\Admin;
use App\Models\Model;
use Database\Factories\App\AppVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App 版本模型
 *
 * @property int $id 版本ID
 * @property int $app_release_id 所属应用ID
 * @property AppPlatform $platform 平台
 * @property string $version 语义化版本号
 * @property int $build 构建号
 * @property string $download_url 下载地址
 * @property string|null $release_notes 更新说明
 * @property string|null $min_supported_version 最低支持版本
 * @property int $min_supported_build 最低支持构建号
 * @property bool $force_update 是否强制更新
 * @property int|null $file_size 安装包大小（字节）
 * @property string|null $file_hash 文件哈希
 * @property ReleaseStatus $status 发布状态
 * @property Carbon|null $published_at 发布时间
 * @property int|null $created_by 创建人ID
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 * @property-read AppRelease $app 所属应用
 * @property-read Admin|null $creator 创建人
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('app_versions')]
#[Fillable([
    'app_release_id', 'platform', 'version', 'build', 'download_url',
    'release_notes', 'min_supported_version', 'min_supported_build',
    'force_update', 'file_size', 'file_hash', 'status', 'published_at', 'created_by',
])]
class AppVersion extends Model
{
    /** @use HasFactory<AppVersionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'app_release_id' => 'integer',
            'platform' => AppPlatform::class,
            'version' => 'string',
            'build' => 'integer',
            'download_url' => 'string',
            'release_notes' => 'string',
            'min_supported_version' => 'string',
            'min_supported_build' => 'integer',
            'force_update' => 'boolean',
            'file_size' => 'integer',
            'file_hash' => 'string',
            'status' => ReleaseStatus::class,
            'published_at' => 'datetime',
            'created_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * 所属应用
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(AppRelease::class, 'app_release_id');
    }

    /**
     * 创建人
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * 作用域：已发布
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ReleaseStatus::PUBLISHED);
    }

    /**
     * 作用域：指定平台
     */
    public function scopeForPlatform(Builder $query, AppPlatform $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    /**
     * 作用域：获取指定平台的最新发布版本
     */
    public function scopeLatestForPlatform(Builder $query, int $appId, AppPlatform $platform): Builder
    {
        return $query->where('app_release_id', $appId)
            ->forPlatform($platform)
            ->published()
            ->orderByDesc('build');
    }

    /**
     * 作用域：列表筛选
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['platform']), fn (Builder $q) => $q->where('platform', $filters['platform']))
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['version']), fn (Builder $q) => $q->where('version', 'like', '%'.$filters['version'].'%'))
            ->when(isset($filters['force_update']) && $filters['force_update'] !== null && $filters['force_update'] !== '', fn (Builder $q) => $q->where('force_update', (bool) $filters['force_update']))
            ->when(! empty($filters['start_date']), fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay()))
            ->when(! empty($filters['end_date']), fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay()));
    }

    /**
     * 发布版本
     *
     * 将同应用同平台的其他已发布版本自动置为废弃。
     */
    public function publish(): void
    {
        // 将同应用同平台的已发布版本置为废弃
        static::where('app_release_id', $this->app_release_id)
            ->forPlatform($this->platform)
            ->published()
            ->whereKeyNot($this->id)
            ->update([
                'status' => ReleaseStatus::OBSOLETE->value,
            ]);

        $this->status = ReleaseStatus::PUBLISHED;
        $this->published_at = $this->published_at ?? now();
        $this->save();
    }

    /**
     * 撤回版本
     */
    public function withdraw(): void
    {
        $this->status = ReleaseStatus::WITHDRAWN;
        $this->save();
    }

    /**
     * 废弃版本
     */
    public function obsolete(): void
    {
        $this->status = ReleaseStatus::OBSOLETE;
        $this->save();
    }

    /**
     * 判断当前版本是否比指定构建号新
     */
    public function isNewerThan(int $build): bool
    {
        return $this->build > $build;
    }

    /**
     * 判断是否需要强制更新
     *
     * 当 force_update 为 true 或当前客户端构建号低于最低支持构建号时返回 true。
     */
    public function requiresForceUpdate(int $currentBuild): bool
    {
        return $this->force_update || $currentBuild < $this->min_supported_build;
    }
}
