<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Content;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\ReviewStatus;
use App\Models\Content\Comment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 评论审核工具。
 *
 * 将评论状态变更为待审核/需复审/已通过/已驳回；驳回后评论对用户不可见。
 * 敏感写操作，执行前需要管理员二次确认。
 */
class ModerateComment implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'comments.edit';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '审核用户评论，将评论状态变更为待审核（pending）、需复审（review）、已通过（approved）或已驳回（rejected）。驳回后评论对用户不可见。审核前建议先用 ListComments 查询评论内容。该操作为敏感写操作，执行前会展示审核摘要并请求二次确认。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->required()
                ->description('评论 ID'),
            'status' => $schema->string()
                ->enum(['pending', 'review', 'approved', 'rejected'])
                ->required()
                ->description('目标审核状态：pending=待审核，review=需复审，approved=已通过，rejected=已驳回'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $comment = Comment::query()->find($request->integer('id'));
        if (! $comment) {
            return false;
        }

        $target = ReviewStatus::from($request->string('status')->toString());
        $content = mb_strlen($comment->content) > 50
            ? mb_substr($comment->content, 0, 50).'…'
            : $comment->content;

        return Approval::required("即将把评论 [{$comment->id}]「{$content}」的审核状态由「{$comment->status->label()}」变更为「{$target->label()}」。");
    }

    /**
     * 执行审核。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $comment = Comment::query()->find($request->integer('id'));
        if (! $comment) {
            return '未找到目标评论，操作已取消。';
        }

        $target = ReviewStatus::from($request->string('status')->toString());

        if ($comment->status === $target) {
            return "评论 [{$comment->id}] 当前状态已是「{$target->label()}」，无需变更。";
        }

        $original = $comment->status;
        $comment->update(['status' => $target]);

        return "已将评论 [{$comment->id}] 的审核状态由「{$original->label()}」变更为「{$target->label()}」。";
    }
}
