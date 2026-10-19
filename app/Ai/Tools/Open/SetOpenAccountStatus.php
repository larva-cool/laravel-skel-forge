<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Open;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\UserStatus;
use App\Models\Open\OpenAccount;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 开放平台账户状态变更工具（启用 / 冻结）。
 *
 * 冻结后账户无法调用开放接口，并同步吊销其全部访问令牌。
 * 敏感写操作，执行前需要管理员二次确认。
 */
class SetOpenAccountStatus implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'open_accounts.edit';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '启用或冻结开放平台账户。冻结后该账户无法调用开放接口，并会同步吊销其全部访问令牌（需重新启用并轮换令牌后才能恢复调用）。该操作为敏感写操作，执行前会展示变更摘要并请求二次确认。';
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
                ->description('开放平台账户 ID'),
            'status' => $schema->string()
                ->enum(['active', 'frozen'])
                ->required()
                ->description('目标状态：active=启用，frozen=冻结'),
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
        $account = OpenAccount::query()->find($request->integer('id'));
        if (! $account) {
            return false;
        }

        $action = $request->string('status')->toString() === 'frozen' ? '冻结' : '启用';
        $message = "即将{$action}开放平台账户 [{$account->id}] {$account->name}（当前状态：{$account->status->label()}）。";
        if ($action === '冻结') {
            $message .= ' 冻结后其全部访问令牌将被吊销。';
        }
        if ($reason = $request->string('reason')->toString()) {
            $message .= " 原因：{$reason}。";
        }

        return Approval::required($message);
    }

    /**
     * 执行状态变更。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $account = OpenAccount::query()->find($request->integer('id'));
        if (! $account) {
            return '未找到目标开放平台账户，操作已取消。';
        }

        $target = $request->string('status')->toString() === 'frozen'
            ? UserStatus::FROZEN
            : UserStatus::ACTIVE;

        if ($account->status === $target) {
            return "开放平台账户 [{$account->id}] {$account->name} 当前已是「{$target->label()}」状态，无需变更。";
        }

        if ($target === UserStatus::FROZEN) {
            $account->markFrozen();
            // 与后台控制器一致：冻结时吊销所有令牌
            $account->tokens()->delete();

            return "已冻结开放平台账户 [{$account->id}] {$account->name}，其全部访问令牌已吊销。";
        }

        $account->markActive();

        return "已启用开放平台账户 [{$account->id}] {$account->name}。如此前被冻结，需轮换令牌后方可恢复接口调用。";
    }
}
