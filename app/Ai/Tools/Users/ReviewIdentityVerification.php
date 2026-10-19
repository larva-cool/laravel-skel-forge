<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Users;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\IdentityStatus;
use App\Models\User\IdentityVerification;
use App\Services\IdentityVerificationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 实名认证审核工具（通过 / 驳回）。
 *
 * 仅「待审核」状态的记录可审核；通过后同步用户认证状态并设置有效期，
 * 驳回需填写驳回原因。敏感写操作，执行前需要管理员二次确认。
 */
class ReviewIdentityVerification implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'identity-verifications.edit';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '审核用户的实名认证记录。action=approve 表示审核通过（同步用户认证状态并设置 3 年有效期）；action=reject 表示审核驳回（必须填写驳回原因 reason）。仅「待审核」状态的记录可审核。审核前建议先用 ListIdentityVerifications 查询记录详情核对证件信息。该操作为敏感写操作，执行前会展示审核摘要并请求二次确认。';
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
                ->description('实名认证记录 ID'),
            'action' => $schema->string()
                ->enum(['approve', 'reject'])
                ->required()
                ->description('审核动作：approve=通过，reject=驳回'),
            'reason' => $schema->string()
                ->max(200)
                ->description('驳回原因（字典 identity_reject_reason 的 value），action=reject 时必填，将同步通知用户'),
            'remark' => $schema->string()
                ->max(500)
                ->description('驳回备注（可选）'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $verification = IdentityVerification::query()->find($request->integer('id'));
        if (! $verification) {
            return false;
        }

        $action = $request->string('action')->toString() === 'reject' ? '驳回' : '通过';
        $subject = $verification->type->isPersonal()
            ? "个人认证（{$verification->real_name}）"
            : "企业认证（{$verification->company_name}）";

        $message = "即将{$action}实名认证记录 [{$verification->id}]：{$subject}，当前状态「{$verification->status->label()}」。";
        if ($action === '驳回') {
            $message .= ' 驳回原因：'.$request->string('reason')->toString().'。';
        }

        return Approval::required($message);
    }

    /**
     * 执行审核。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $verification = IdentityVerification::query()->find($request->integer('id'));
        if (! $verification) {
            return '未找到目标实名认证记录，操作已取消。';
        }

        if ($verification->status !== IdentityStatus::PENDING) {
            return "该记录当前状态为「{$verification->status->label()}」，仅待审核记录可执行审核，操作已取消。";
        }

        $isReject = $request->string('action')->toString() === 'reject';
        $reason = $request->string('reason')->toString();
        $remark = $request->string('remark')->toString() ?: null;

        if ($isReject && trim($reason) === '') {
            return '驳回实名认证时必须填写驳回原因（reason），操作已取消。';
        }

        $service = app(IdentityVerificationService::class);
        $isReject
            ? $service->reject($verification, $this->admin, $reason, $remark)
            : $service->approve($verification, $this->admin);

        $fresh = $verification->refresh();
        $subject = $fresh->type->isPersonal() ? $fresh->real_name : $fresh->company_name;

        return $isReject
            ? "已驳回实名认证记录 [{$fresh->id}]（{$subject}），驳回原因：{$fresh->reject_reason}。"
            : "已通过实名认证记录 [{$fresh->id}]（{$subject}），认证有效期至 {$fresh->expires_at?->toDateString()}。";
    }
}
