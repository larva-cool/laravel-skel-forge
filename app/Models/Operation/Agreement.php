<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Operation;

use App\Enums\AgreementStatus;
use App\Enums\CacheKey;
use App\Models\Admin\Admin;
use App\Models\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * 用户协议模型
 *
 * @property int $id 协议ID
 * @property string $title 协议标题
 * @property string $category 协议类别（字典值）
 * @property string $content 协议内容（富文本 HTML）
 * @property string $version 版本号
 * @property AgreementStatus $status 状态
 * @property Carbon|null $effective_at 生效时间
 * @property int $sort 排序权重
 * @property int|null $admin_id 创建/编辑管理员ID
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 * @property-read Admin $admin 创建人
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('agreements')]
#[Fillable(['title', 'category', 'content', 'version', 'status', 'effective_at', 'sort', 'admin_id'])]
class Agreement extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => AgreementStatus::DRAFT,
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
            'category' => 'string',
            'content' => 'string',
            'version' => 'string',
            'status' => AgreementStatus::class,
            'effective_at' => 'datetime',
            'sort' => 'integer',
            'admin_id' => 'integer',
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

        // 状态变更为 published 时自动写入生效时间
        static::updating(function (self $agreement): void {
            $originalStatus = $agreement->getOriginal('status');
            if ($agreement->status === AgreementStatus::PUBLISHED
                && ! ($originalStatus instanceof AgreementStatus && $originalStatus->isPublished())
                && $agreement->effective_at === null
            ) {
                $agreement->effective_at = now();
            }
        });

        // 创建时如果直接是 published 状态，写入生效时间
        static::creating(function (self $agreement): void {
            if ($agreement->status === AgreementStatus::PUBLISHED && $agreement->effective_at === null) {
                $agreement->effective_at = now();
            }
        });

        // 清除缓存
        static::saved(function (self $agreement): void {
            Cache::forget(CacheKey::key(self::CACHE_KEY, $agreement->category));
        });

        static::deleted(function (self $agreement): void {
            Cache::forget(CacheKey::key(self::CACHE_KEY, $agreement->category));
        });
    }

    /**
     * 创建人
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * 根据类别获取最新已发布的协议（带缓存）
     */
    public static function getPublishedByCategory(string $category): ?self
    {
        $cacheKey = CacheKey::key(self::CACHE_KEY, $category);

        /** @var array<string, mixed>|null $data */
        $data = Cache::remember($cacheKey, Carbon::now()->addHours(2), function () use ($category) {
            return static::query()
                ->where('category', $category)
                ->where('status', AgreementStatus::PUBLISHED)
                ->orderByDesc('effective_at')
                ->orderByDesc('id')
                ->first()
                ?->toArray();
        });

        if (! $data) {
            return null;
        }

        return static::newFromBuilder($data);
    }
    private const string CACHE_KEY = 'agreements:%s';
}
