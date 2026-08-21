<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 前台用户状态变更工具（冻结/激活）。
 *
 * 写操作，默认需要管理员二次确认。
 */
class SetUserStatus implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '冻结或解冻前台用户账号。冻结后用户将无法登录。该操作为敏感写操作，执行前会向管理员展示变更摘要并请求二次确认。';
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
                ->description('目标用户 ID'),
            'status' => $schema->string()
                ->enum(['active', 'frozen'])
                ->required()
                ->description('目标状态：active=激活/解冻，frozen=冻结'),
            'reason' => $schema->string()
                ->max(200)
                ->description('操作原因，将展示在二次确认信息中'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $user = User::query()->find($request->integer('id'));
        if (! $user) {
            return false;
        }

        $targetStatus = $request->string('status')->toString() === 'frozen' ? '冻结' : '激活';
        $reason = $request->string('reason')->toString();
        $message = "即将把用户 [{$user->id}] {$user->name}（{$user->username}）的状态变更为：{$targetStatus}。";
        if ($reason !== '') {
            $message .= " 原因：{$reason}。";
        }

        return Approval::required($message);
    }

    /**
     * 执行变更。
     */
    public function handle(Request $request): Stringable|string
    {
        $user = User::query()->find($request->integer('id'));
        if (! $user) {
            return '未找到目标用户，操作已取消。';
        }

        $target = $request->string('status')->toString() === 'frozen'
            ? UserStatus::FROZEN
            : UserStatus::ACTIVE;

        if ($user->status === $target) {
            return "用户 [{$user->id}] 当前状态已是「{$target->label()}」，无需变更。";
        }

        $target === UserStatus::FROZEN ? $user->markFrozen() : $user->markActive();

        return "已将用户 [{$user->id}] {$user->name} 的状态更新为「{$target->label()}」。";
    }
}
