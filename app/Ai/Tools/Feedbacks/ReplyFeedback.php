<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Feedbacks;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\FeedbackStatus;
use App\Models\System\Feedback;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 用户反馈处理工具。
 *
 * 更新反馈的处理状态和/或处理备注；首次处理时自动记录当前管理员为处理人。
 * 敏感写操作，执行前需要管理员二次确认。
 */
class ReplyFeedback implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'feedbacks.edit';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '处理用户意见反馈：更新处理状态（status）和/或填写处理备注（admin_remark），两者至少提供一项。首次处理时会自动将当前管理员记录为处理人；状态置为已解决/已关闭时系统自动记录对应时间。该操作为敏感写操作，执行前会展示处理摘要并请求二次确认。';
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
                ->description('反馈 ID'),
            'status' => $schema->integer()
                ->enum([0, 1, 2, 3, 4, 5])
                ->description('目标处理状态：0=待处理，1=分析中，2=已规划，3=处理中，4=已解决，5=已关闭'),
            'admin_remark' => $schema->string()
                ->max(2000)
                ->description('处理备注/回复内容（内部可见）'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $feedback = Feedback::query()->find($request->integer('id'));
        if (! $feedback) {
            return false;
        }

        $changes = [];
        if ($request->has('status')) {
            $changes[] = '状态变更为「'.FeedbackStatus::from($request->integer('status'))->label().'」';
        }
        if ($request->string('admin_remark')->toString() !== '') {
            $changes[] = '填写处理备注';
        }

        $message = "即将处理反馈「{$feedback->title}」（编号 {$feedback->code}）：".implode('，', $changes).'。';

        return Approval::required($message);
    }

    /**
     * 执行处理。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $feedback = Feedback::query()->find($request->integer('id'));
        if (! $feedback) {
            return '未找到目标反馈，操作已取消。';
        }

        $hasStatus = $request->has('status');
        $remark = $request->string('admin_remark')->toString();

        if (! $hasStatus && trim($remark) === '') {
            return '请至少提供目标处理状态（status）或处理备注（admin_remark）其中一项，操作已取消。';
        }

        $data = [];
        $changes = [];

        if ($hasStatus) {
            $status = FeedbackStatus::from($request->integer('status'));
            $data['status'] = $status;
            $changes[] = "状态：「{$feedback->status->label()}」→「{$status->label()}」";

            // 首次处理时记录当前管理员为处理人
            if ($feedback->admin_id === null) {
                $data['admin_id'] = $this->admin->id;
            }
        }

        if (trim($remark) !== '') {
            $data['admin_remark'] = $remark;
            $changes[] = '已填写处理备注';
        }

        $feedback->update($data);

        return "已处理反馈「{$feedback->title}」（编号 {$feedback->code}）：".implode('；', $changes).'。';
    }
}
