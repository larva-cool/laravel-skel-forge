<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Users;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\User;
use App\Notifications\User\AdminMessageNotification;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 用户站内通知发送工具。
 *
 * 向指定前台用户发送一条站内通知（database 频道）。
 * 敏感写操作，执行前需要管理员二次确认。
 */
class SendUserNotification implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'users.edit';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '向指定前台用户发送一条站内通知（用户端消息中心可见）。需明确接收用户 ID、通知标题与内容。该操作为敏感写操作，执行前会展示通知摘要并请求二次确认。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()
                ->required()
                ->description('接收通知的前台用户 ID'),
            'title' => $schema->string()
                ->required()
                ->max(50)
                ->description('通知标题，不超过 50 字'),
            'body' => $schema->string()
                ->required()
                ->max(500)
                ->description('通知正文内容，不超过 500 字'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $user = User::query()->find($request->integer('user_id'));
        if (! $user) {
            return false;
        }

        $message = "即将向用户 [{$user->id}] {$user->name}（{$user->username}）发送站内通知。标题：「{$request->string('title')->toString()}」。";

        return Approval::required($message);
    }

    /**
     * 执行发送。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $user = User::query()->find($request->integer('user_id'));
        if (! $user) {
            return '未找到目标用户，操作已取消。';
        }

        $title = $request->string('title')->toString();
        $body = $request->string('body')->toString();

        if (trim($title) === '' || trim($body) === '') {
            return '通知标题与正文均不能为空，操作已取消。';
        }

        $user->notify(new AdminMessageNotification($title, $body));

        return "已向用户 [{$user->id}] {$user->name}（{$user->username}）发送站内通知「{$title}」。";
    }
}
