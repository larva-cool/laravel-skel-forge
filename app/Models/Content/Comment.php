<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Content;

use App\Enums\ReviewStatus;
use App\Models\Model;
use App\Models\Traits\HasLikeable;
use App\Models\Traits\HasUser;
use App\Models\User;
use App\Policies\CommentPolicy;
use Database\Factories\Content\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * 评论模型
 *
 * @property int $id 评论ID
 * @property int $user_id 用户ID
 * @property int $source_id 源ID
 * @property string $source_type 源类型
 * @property bool $is_top 是否置顶
 * @property int $like_count 点赞次数
 * @property int $comment_count 评论回复次数
 * @property ReviewStatus $status 评论状态
 * @property array $mentioned_users 艾特 / 提及
 * @property string $content 评论内容
 * @property string $ip_address 评论IP
 * @property Carbon $created_at 评论时间
 * @property Model $source 源模型
 * @property User $user 用户模型
 * @property Comment[] $comments 回复模型
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('comments')]
#[Fillable(['user_id', 'source_id', 'source_type', 'is_top', 'like_count', 'comment_count', 'status', 'content',
    'ip_address', 'mentioned_users'])]
#[Hidden(['user_id'])]
#[UsePolicy(CommentPolicy::class)]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasLikeable, HasUser;

    // 时间定义
    public const UPDATED_AT = null;

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
            'source_id' => 'integer',
            'source_type' => 'string',
            'is_top' => 'boolean',
            'like_count' => 'integer',
            'comment_count' => 'integer',
            'status' => ReviewStatus::class,
            'content' => 'string',
            'mentioned_users' => 'array',
            'ip_address' => 'string',
            'created_at' => 'datetime',
        ];
    }

    /**
     * 评论回复
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'source');
    }

    /**
     * 多态关联
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
