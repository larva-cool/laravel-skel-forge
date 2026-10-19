<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Wallet;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\User;
use App\Models\Wallet\Withdraw;
use App\Services\WithdrawService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 提现申请审核工具（通过 / 驳回）。
 *
 * 通过：资金保持冻结，等待后续打款；驳回：冻结金额解冻退回用户可用余额。
 * 敏感写操作，执行前需要管理员二次确认。
 */
class ReviewWithdraw implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'wallet.withdraws';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '审核用户的提现申请。action=approve 表示审核通过（资金保持冻结，等待打款）；action=reject 表示审核驳回（冻结金额解冻退回用户余额，必须填写驳回原因 reason）。仅「待审核」状态的申请可审核。该操作为敏感写操作，执行前会展示审核摘要并请求二次确认。';
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
                ->description('提现申请 ID'),
            'action' => $schema->string()
                ->enum(['approve', 'reject'])
                ->required()
                ->description('审核动作：approve=通过，reject=驳回'),
            'reason' => $schema->string()
                ->max(200)
                ->description('驳回原因，action=reject 时必填，将同步通知用户'),
            'remark' => $schema->string()
                ->max(500)
                ->description('审核备注（内部可见，可选）'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $withdraw = Withdraw::query()->find($request->integer('id'));
        if (! $withdraw) {
            return false;
        }

        $action = $request->string('action')->toString() === 'reject' ? '驳回' : '通过';
        $owner = $withdraw->owner;
        $ownerName = $owner instanceof User ? "{$owner->name}（{$owner->username}）" : "用户ID {$withdraw->owner_id}";

        $message = "即将{$action}提现单「{$withdraw->order_no}」：申请人 {$ownerName}，申请金额 {$withdraw->amount} 元，实际到账 {$withdraw->actual_amount} 元。";
        if ($action === '驳回') {
            $message .= ' 驳回原因：'.$request->string('reason')->toString().'。冻结金额将退回用户可用余额。';
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

        $withdraw = Withdraw::query()->find($request->integer('id'));
        if (! $withdraw) {
            return '未找到目标提现申请，操作已取消。';
        }

        $isReject = $request->string('action')->toString() === 'reject';
        $reason = $request->string('reason')->toString();
        $remark = $request->string('remark')->toString() ?: null;

        if ($isReject && trim($reason) === '') {
            return '驳回提现申请时必须填写驳回原因（reason），操作已取消。';
        }

        try {
            $service = app(WithdrawService::class);
            $isReject
                ? $service->reject($withdraw, $this->admin, $reason, $remark)
                : $service->approve($withdraw, $this->admin, $remark);
        } catch (ValidationException $e) {
            return '操作已取消：'.collect($e->errors())->flatten()->first();
        }

        $fresh = $withdraw->refresh();

        return $isReject
            ? "已驳回提现单「{$fresh->order_no}」，申请金额 {$fresh->amount} 元已解冻退回用户可用余额。"
            : "已通过提现单「{$fresh->order_no}」，实际到账金额 {$fresh->actual_amount} 元，当前状态为「{$fresh->status->label()}」，等待打款。";
    }
}
