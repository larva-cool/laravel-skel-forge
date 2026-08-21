<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Users;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 前台用户详情查询工具。
 *
 * 通过用户 ID 或账号（用户名/邮箱/手机号）获取单个用户的完整信息。
 */
class GetUser implements Tool
{
    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '根据用户 ID 或账号（用户名/邮箱/手机号）查询单个前台用户的详细信息，包含基本资料、账户状态、积分/金币、VIP、登录历史等。当管理员需要查看某个具体用户时使用。';
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
                ->description('用户 ID。与 account 二选一，优先使用 id'),
            'account' => $schema->string()
                ->max(100)
                ->description('用户账号，可为用户名、邮箱或手机号。若未提供 id 则使用本字段'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        $id = $request->integer('id');
        $account = $request->string('account')->toString();

        if (! $id && $account === '') {
            return '错误：必须提供 id 或 account 中的至少一个参数。';
        }

        /** @var User|null $user */
        $user = $id
            ? User::with(['extra', 'loginHistories' => fn ($q) => $q->limit(10)])->find($id)
            : User::with(['extra', 'loginHistories' => fn ($q) => $q->limit(10)])
                ->where(fn ($q) => $q->where('username', $account)
                    ->orWhere('email', $account)
                    ->orWhere('phone', $account))
                ->first();

        if (! $user) {
            return '未找到匹配的用户。';
        }

        return json_encode([
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone_text,
            'avatar' => $user->avatar,
            'status' => [
                'value' => $user->status->value,
                'label' => $user->status->label(),
            ],
            'available_points' => $user->available_points,
            'available_coins' => $user->available_coins,
            'is_vip' => $user->isVip(),
            'vip_expires_at' => $user->vip_expires_at?->toDateTimeString(),
            'email_verified_at' => $user->email_verified_at?->toDateTimeString(),
            'login_count' => $user->login_count,
            'last_login_ip' => $user->last_login_ip,
            'last_login_at' => $user->last_login_at?->toDateTimeString(),
            'last_active_at' => $user->last_active_at?->toDateTimeString(),
            'created_at' => $user->created_at?->toDateTimeString(),
            'extra' => $user->extra ? [
                'username_change_count' => $user->extra->username_change_count,
                'email_verified_at' => $user->extra->email_verified_at?->toDateTimeString(),
            ] : null,
            'recent_login_histories' => $user->loginHistories->map(fn ($history) => [
                'ip' => $history->ip,
                'address' => $history->address,
                'platform' => $history->platform,
                'device' => $history->device,
                'browser' => $history->browser,
                'login_at' => $history->login_at?->toDateTimeString(),
            ]),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
