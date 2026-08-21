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
 * 前台用户 VIP 开通/延长工具。
 *
 * 写操作，默认需要管理员二次确认。
 */
class ExtendUserVip implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '为前台用户开通 VIP 或在现有 VIP 基础上延长有效期。若用户当前已是 VIP，则在现有到期时间上累加天数；否则从当前时间开始计算。该操作为敏感写操作，执行前会展示用户当前 VIP 状态及变更后的到期时间并请求二次确认。';
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
            'days' => $schema->integer()
                ->required()
                ->min(1)
                ->max(3650)
                ->description('开通/延长的天数，正整数，最大 3650 天（10 年）'),
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

        $days = $request->integer('days');
        $currentExpiry = $user->vip_expires_at;
        $isVip = $user->isVip();
        $newExpiry = $isVip
            ? $currentExpiry->copy()->addDays($days)
            : now()->addDays($days);

        $currentText = $isVip
            ? $currentExpiry->toDateTimeString()
            : '非 VIP';

        $reason = $request->string('reason')->toString();
        $message = "即将为用户 [{$user->id}] {$user->name}（{$user->username}）".
            ($isVip ? '延长' : '开通').
            " VIP {$days} 天。\n".
            "  - 当前状态：{$currentText}\n".
            "  - 变更后到期时间：{$newExpiry->toDateTimeString()}";
        if ($reason !== '') {
            $message .= "\n  - 原因：{$reason}";
        }

        return Approval::required($message);
    }

    /**
     * 执行 VIP 开通/延长。
     */
    public function handle(Request $request): Stringable|string
    {
        $user = User::query()->find($request->integer('id'));
        if (! $user) {
            return '未找到目标用户，操作已取消。';
        }

        $days = $request->integer('days');
        $wasVip = $user->isVip();
        $oldExpiry = $user->vip_expires_at?->toDateTimeString();

        $user->addVipDays($days);

        $newExpiry = $user->fresh()->vip_expires_at?->toDateTimeString();

        return "已为用户 [{$user->id}] {$user->name} ".
            ($wasVip ? '延长' : '开通').
            " VIP {$days} 天。\n".
            '  - 变更前到期时间：'.($oldExpiry ?: '非 VIP')."\n".
            "  - 变更后到期时间：{$newExpiry}";
    }
}
