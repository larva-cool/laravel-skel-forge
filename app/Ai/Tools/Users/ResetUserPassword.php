<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Users;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 前台用户密码重置工具。
 *
 * 将指定用户密码重置为新的明文密码；写操作，默认需要二次确认。
 */
class ResetUserPassword implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '重置前台用户的登录密码。执行后用户原密码立即失效，所有已登录会话将被吊销。该操作为高敏感写操作，执行前会展示目标用户并请求二次确认。';
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
            'new_password' => $schema->string()
                ->required()
                ->min(8)
                ->max(64)
                ->description('新密码，明文传入，服务端会进行哈希存储。长度 8-64 位'),
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

        $reason = $request->string('reason')->toString();
        $message = "即将重置用户 [{$user->id}] {$user->name}（{$user->username}）的登录密码。重置后原密码立即失效，用户需使用新密码重新登录。";
        if ($reason !== '') {
            $message .= " 原因：{$reason}。";
        }

        return Approval::required($message);
    }

    /**
     * 执行密码重置。
     */
    public function handle(Request $request): Stringable|string
    {
        $user = User::query()->find($request->integer('id'));
        if (! $user) {
            return '未找到目标用户，操作已取消。';
        }

        $newPassword = $request->string('new_password')->toString();

        if (strlen($newPassword) < 8) {
            return '密码长度不能少于 8 位，操作已取消。';
        }

        $user->resetPassword($newPassword);

        // 吊销该用户已颁发的 Sanctum Token
        $user->tokens()->delete();

        return "已成功重置用户 [{$user->id}] {$user->name} 的密码，并吊销其全部登录会话。请通过安全渠道将新密码告知用户。";
    }
}
