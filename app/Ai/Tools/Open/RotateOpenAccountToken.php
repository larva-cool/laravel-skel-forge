<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Open;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\Open\OpenAccount;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 开放平台账户令牌轮换工具。
 *
 * 作废账户全部历史令牌并签发新令牌；明文 Token 仅在本次返回中出现一次。
 * 敏感写操作，执行前需要管理员二次确认。
 */
class RotateOpenAccountToken implements Approvable, RequiresPermission, Tool
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
        return '轮换开放平台账户的访问令牌：作废该账户全部历史令牌并签发新令牌。新明文 Token 仅在本次执行结果中返回一次，之后不可再查询，需提醒管理员立即妥善保存并转交合作方。该操作为敏感写操作，执行前会展示轮换摘要并请求二次确认。';
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

        return Approval::required("即将轮换开放平台账户 [{$account->id}] {$account->name} 的访问令牌，其全部历史令牌将立即失效。");
    }

    /**
     * 执行轮换。
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

        $token = $account->rotateToken();

        return "已为开放平台账户 [{$account->id}] {$account->name} 轮换访问令牌，历史令牌已全部失效。\n新明文 Token（仅此一次展示，请立即妥善保存并转交合作方）：\n{$token->plainTextToken}";
    }
}
