<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 前台用户列表查询工具。
 *
 * 支持按关键字、状态、VIP、注册时间范围筛选，返回分页用户摘要数据。
 */
class ListUsers implements Tool
{
    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询前台用户列表。支持按关键字（用户名/昵称/邮箱/手机号）、状态、是否 VIP、注册时间范围筛选，以分页形式返回用户摘要信息。当管理员需要查找、筛选或批量查看用户时使用。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()
                ->description('模糊匹配关键字，作用于用户名、昵称、邮箱、手机号')
                ->max(100),
            'status' => $schema->string()
                ->enum(['active', 'frozen'])
                ->description('用户状态筛选：active=正常，frozen=冻结'),
            'vip' => $schema->boolean()
                ->description('是否仅查询 VIP 用户。true=仅 VIP，false=仅非 VIP，不传=全部'),
            'registered_after' => $schema->string()
                ->format('date')
                ->description('注册起始日期（YYYY-MM-DD），含当天'),
            'registered_before' => $schema->string()
                ->format('date')
                ->description('注册截止日期（YYYY-MM-DD），含当天'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('页码，从 1 开始'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(15)
                ->description('每页条数，最大 50'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        $query = User::query();

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $enum = $status === 'frozen' ? UserStatus::FROZEN : UserStatus::ACTIVE;
            $query->where('status', $enum);
        }

        if ($request->has('vip')) {
            $isVip = $request->boolean('vip');
            if ($isVip) {
                $query->whereNotNull('vip_expires_at')->where('vip_expires_at', '>', now());
            } else {
                $query->where(function ($q) {
                    $q->whereNull('vip_expires_at')->orWhere('vip_expires_at', '<=', now());
                });
            }
        }

        if ($after = $request->string('registered_after')->toString()) {
            $query->whereDate('created_at', '>=', $after);
        }
        if ($before = $request->string('registered_before')->toString()) {
            $query->whereDate('created_at', '<=', $before);
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 15) ?: 15;

        $paginator = $query->orderByDesc('id')->paginate(
            perPage: min(max($perPage, 1), 50),
            page: $page,
        );

        $users = $paginator->getCollection()->map(fn (User $user) => [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone_text,
            'status' => $user->status->label(),
            'available_points' => $user->available_points,
            'available_coins' => $user->available_coins,
            'is_vip' => $user->isVip(),
            'vip_expires_at' => $user->vip_expires_at?->toDateTimeString(),
            'login_count' => $user->login_count,
            'last_login_at' => $user->last_login_at?->toDateTimeString(),
            'created_at' => $user->created_at?->toDateTimeString(),
        ]);

        return json_encode([
            'data' => $users,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
