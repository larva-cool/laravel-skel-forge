<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Content;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\ReportStatus;
use App\Models\Content\Report;
use App\Services\ReportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 举报处理工具。
 *
 * 将举报结案为「已处理」（可选联动处置被举报对象）或「已驳回」。
 * 敏感写操作，执行前需要管理员二次确认。
 */
class HandleReport implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'reports.edit';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '处理用户举报并结案。status=resolved 表示举报成立（可附加联动处置动作 actions：hide_comment=隐藏评论、delete_comment=删除评论、freeze_user=冻结被举报用户）；status=rejected 表示举报不成立驳回（不执行任何处置）。已结案的举报不可重复处理。处理前建议先用 ListReports 查询举报详情。该操作为敏感写操作，执行前会展示处理摘要并请求二次确认。';
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
                ->description('举报 ID'),
            'status' => $schema->string()
                ->enum(['resolved', 'rejected'])
                ->required()
                ->description('处理结果：resolved=举报成立（已处理），rejected=举报不成立（已驳回）'),
            'remark' => $schema->string()
                ->max(500)
                ->description('处理备注（内部可见，可选）'),
            'actions' => $schema->array()
                ->items($schema->string()->enum(['hide_comment', 'delete_comment', 'freeze_user']))
                ->description('联动处置动作（仅 status=resolved 时生效）：hide_comment=隐藏被举报评论，delete_comment=删除被举报评论，freeze_user=冻结被举报用户'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $report = Report::query()->find($request->integer('id'));
        if (! $report) {
            return false;
        }

        $result = $request->string('status')->toString() === 'rejected' ? '驳回' : '处理成立';
        $message = "即将将举报 [{$report->id}]（分类：{$report->category}，当前状态：{$report->status->label()}）结案为「{$result}」。";

        $actions = $request->array('actions');
        if ($result === '处理成立' && $actions !== []) {
            $labels = ['hide_comment' => '隐藏评论', 'delete_comment' => '删除评论', 'freeze_user' => '冻结用户'];
            $message .= ' 联动处置：'.implode('、', array_map(fn ($a) => $labels[$a] ?? $a, $actions)).'。';
        }

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

        $report = Report::query()->find($request->integer('id'));
        if (! $report) {
            return '未找到目标举报，操作已取消。';
        }

        if ($report->status->isClosed()) {
            return "该举报已结案（当前状态：{$report->status->label()}），不能重复处理，操作已取消。";
        }

        $newStatus = $request->string('status')->toString();
        $remark = $request->string('remark')->toString() ?: null;
        $actions = $request->array('actions');

        // 与后台控制器一致：先推进到 processing，避免并发重复操作
        if ($report->status === ReportStatus::PENDING) {
            $report->forceFill(['status' => ReportStatus::PROCESSING])->save();
        }

        $report = app(ReportService::class)->process($report, $this->admin, [
            'status' => $newStatus,
            'remark' => $remark,
            'actions' => $newStatus === 'resolved' ? $actions : [],
        ]);

        return $newStatus === 'rejected'
            ? "已将举报 [{$report->id}] 结案为「已驳回」，未执行任何处置动作。"
            : "已将举报 [{$report->id}] 结案为「已处理」".($actions !== [] ? '，并执行联动处置：'.implode('、', $actions) : '').'。';
    }
}
