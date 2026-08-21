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
 * 前台用户联系方式重置工具（手机号 / 邮箱）。
 *
 * 写操作，默认需要管理员二次确认。通过 User 模型的 resetPhone()/resetEmail()
 * 完成字段更新，同时派发 PhoneReset / EmailReset 事件。
 */
class ResetUserContact implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '重置前台用户的手机号或邮箱。执行前会校验新的联系方式是否已被其他用户占用。该操作会改变用户的登录凭证，属于高敏感写操作，执行前会展示原值与新值并请求二次确认。';
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
            'type' => $schema->string()
                ->enum(['phone', 'email'])
                ->required()
                ->description('要重置的联系方式类型：phone=手机号，email=邮箱'),
            'value' => $schema->string()
                ->required()
                ->max(100)
                ->description('新的手机号或邮箱地址，必须符合对应格式'),
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

        $type = $request->string('type')->toString();
        $newValue = $request->string('value')->toString();
        $reason = $request->string('reason')->toString();

        if ($type === 'phone') {
            if (! $this->isValidPhone($newValue)) {
                return false;
            }
            $oldValue = $user->phone ?: '未绑定';
            $label = '手机号';
        } else {
            if (! filter_var($newValue, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
            $oldValue = $user->email ?: '未绑定';
            $label = '邮箱';
        }

        $message = "即将重置用户 [{$user->id}] {$user->name}（{$user->username}）的{$label}：\n".
            "  - 原{$label}：{$oldValue}\n".
            "  - 新{$label}：{$newValue}";
        if ($reason !== '') {
            $message .= "\n  - 原因：{$reason}";
        }

        return Approval::required($message);
    }

    /**
     * 执行重置。
     */
    public function handle(Request $request): Stringable|string
    {
        $user = User::query()->find($request->integer('id'));
        if (! $user) {
            return '未找到目标用户，操作已取消。';
        }

        $type = $request->string('type')->toString();
        $newValue = trim($request->string('value')->toString());

        if ($type === 'phone') {
            if (! $this->isValidPhone($newValue)) {
                return '新手机号格式不正确（应为 1 开头的 11 位数字），操作已取消。';
            }

            $exists = User::query()
                ->where('phone', $newValue)
                ->where('id', '!=', $user->id)
                ->exists();
            if ($exists) {
                return "手机号 {$newValue} 已被其他用户占用，操作已取消。";
            }

            $oldValue = $user->phone;
            $user->resetPhone($newValue);

            return "已重置用户 [{$user->id}] {$user->name} 的手机号。\n".
                '  - 原手机号：'.($oldValue ?: '未绑定')."\n".
                "  - 新手机号：{$newValue}";
        }

        if (! filter_var($newValue, FILTER_VALIDATE_EMAIL)) {
            return '新邮箱格式不正确，操作已取消。';
        }

        $exists = User::query()
            ->where('email', $newValue)
            ->where('id', '!=', $user->id)
            ->exists();
        if ($exists) {
            return "邮箱 {$newValue} 已被其他用户占用，操作已取消。";
        }

        $oldValue = $user->email;
        $user->resetEmail($newValue);

        return "已重置用户 [{$user->id}] {$user->name} 的邮箱。\n".
            '  - 原邮箱：'.($oldValue ?: '未绑定')."\n".
            "  - 新邮箱：{$newValue}\n".
            '  - 邮箱已标记为已验证';
    }

    /**
     * 校验中国大陆手机号格式。
     */
    protected function isValidPhone(string $phone): bool
    {
        return preg_match('/^1[2-9]\d{9}$/', $phone) === 1;
    }
}
