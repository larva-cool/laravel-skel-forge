<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\System;

use App\Enums\FeedbackPriority;
use App\Enums\FeedbackStatus;
use App\Models\Admin\Admin;
use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 用户反馈模型
 *
 * @property int $id 反馈ID
 * @property string $code 反馈编号
 * @property string $title 标题
 * @property string $content 详细描述
 * @property array<int, string>|null $images 反馈图片URL列表
 * @property string $type 反馈类型（字典 feedback_type 的 value）
 * @property FeedbackStatus $status 状态
 * @property FeedbackPriority $priority 优先级
 * @property string|null $contact_name 联系人
 * @property string|null $contact_info 联系方式
 * @property string|null $user_agent 浏览器UA
 * @property string|null $ip_address 提交IP
 * @property int|null $user_id 关联用户ID
 * @property int|null $admin_id 处理人ID
 * @property string|null $admin_remark 管理员处理备注
 * @property Carbon|null $resolved_at 解决时间
 * @property Carbon|null $closed_at 关闭时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property-read User|null $user 提交用户
 * @property-read Admin|null $admin 处理人
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('feedbacks')]
#[Fillable([
    'title', 'content', 'images', 'type', 'status', 'priority',
    'contact_name', 'contact_info',
    'user_agent', 'ip_address', 'user_id',
    'admin_id', 'admin_remark', 'resolved_at', 'closed_at',
])]
class Feedback extends Model
{
    /**
     * The model's attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => FeedbackStatus::PENDING,
        'priority' => FeedbackPriority::LOW,
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
            'code' => 'string',
            'title' => 'string',
            'content' => 'string',
            'images' => 'array',
            'type' => 'string',
            'status' => FeedbackStatus::class,
            'priority' => FeedbackPriority::class,
            'contact_name' => 'string',
            'contact_info' => 'string',
            'user_agent' => 'string',
            'ip_address' => 'string',
            'user_id' => 'integer',
            'admin_id' => 'integer',
            'admin_remark' => 'string',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
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

        // 创建时自动生成反馈编号
        static::creating(function (self $feedback): void {
            if (empty($feedback->code)) {
                $feedback->code = static::generateCode();
            }
        });

        // 状态变更时自动设置时间戳
        static::updating(function (self $feedback): void {
            $originalStatus = $feedback->getOriginal('status');
            if ($originalStatus instanceof FeedbackStatus) {
                // 变为已解决
                if ($feedback->status === FeedbackStatus::RESOLVED && $originalStatus !== FeedbackStatus::RESOLVED) {
                    $feedback->resolved_at = now();
                }
                // 变为已关闭
                if ($feedback->status === FeedbackStatus::CLOSED && $originalStatus !== FeedbackStatus::CLOSED) {
                    $feedback->closed_at = now();
                }
            }
        });
    }

    /**
     * 提交用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 处理管理员
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * 生成唯一反馈编号
     *
     * 格式：FDB + 毫秒级时间戳(13位) + 4位随机数
     */
    public static function generateCode(): string
    {
        $timestamp = (int) round(microtime(true) * 1000);

        return 'FDB'.$timestamp.Str::padLeft((string) random_int(0, 9999), 4, '0');
    }
}
