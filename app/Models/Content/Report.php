<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Content;

use App\Enums\ReportStatus;
use App\Models\Admin\Admin;
use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * 举报模型
 *
 * @property int $id 举报ID
 * @property int $user_id 举报人用户ID
 * @property int $reportable_id 被举报对象ID
 * @property string $reportable_type 被举报对象类型
 * @property string $category 举报分类（取自字典 report_category）
 * @property string|null $reason 举报描述
 * @property array|null $evidence 证据图片/截图
 * @property ReportStatus $status 处理状态
 * @property int|null $handler_id 处理人管理员ID
 * @property string|null $process_remark 处理备注
 * @property array|null $process_actions 实际处置动作
 * @property Carbon|null $processed_at 处理时间
 * @property Carbon $created_at 举报时间
 * @property Carbon $updated_at 更新时间
 *
 * 关系对象
 * @property User $user 举报人
 * @property Model $reportable 被举报对象
 * @property Admin|null $handler 处理人
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('reports')]
#[Fillable(['user_id', 'reportable_id', 'reportable_type', 'category', 'reason', 'evidence',
    'status', 'handler_id', 'process_remark', 'process_actions', 'processed_at'])]
class Report extends Model
{
    /** 不使用 updated_at，由迁移字段管理 */
    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => ReportStatus::PENDING->value,
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
            'user_id' => 'integer',
            'reportable_id' => 'integer',
            'reportable_type' => 'string',
            'category' => 'string',
            'reason' => 'string',
            'evidence' => 'array',
            'status' => ReportStatus::class,
            'handler_id' => 'integer',
            'process_remark' => 'string',
            'process_actions' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * 举报人
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 被举报对象（多态）
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 处理人管理员
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handler_id');
    }
}
