<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Audit;

use App\Models\Admin\Admin;
use App\Models\System\LoginHistory;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 全局登录历史查询工具。
 *
 * 支持按用户类型、用户 ID、IP/地址/设备/平台关键字、时间范围筛选，
 * 用于安全审计与异常登录排查。只读操作。
 */
class ListLoginHistories implements Tool
{
    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询全局登录历史记录（含前台用户和后台管理员），支持按用户类型、用户 ID、登录地址/设备/平台关键字、时间范围筛选。用于排查异常登录、审计登录行为。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'guard' => $schema->string()
                ->enum(['user', 'admin'])
                ->description('用户类型筛选：user=前台用户，admin=后台管理员。不传则查询全部'),
            'user_id' => $schema->integer()
                ->description('精确按用户 ID 筛选，需与 guard 配合使用'),
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配登录地址、设备、平台或浏览器'),
            'login_after' => $schema->string()
                ->format('date')
                ->description('登录起始日期（YYYY-MM-DD），含当天'),
            'login_before' => $schema->string()
                ->format('date')
                ->description('登录截止日期（YYYY-MM-DD），含当天'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('页码，从 1 开始'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(20)
                ->description('每页条数，最大 50'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        $query = LoginHistory::query();

        $guard = $request->string('guard')->toString();
        if ($guard === 'user') {
            $query->where('user_type', User::class);
        } elseif ($guard === 'admin') {
            $query->where('user_type', Admin::class);
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where(function ($q) use ($keyword) {
                $q->where('address', 'like', "%{$keyword}%")
                    ->orWhere('device', 'like', "%{$keyword}%")
                    ->orWhere('platform', 'like', "%{$keyword}%")
                    ->orWhere('browser', 'like', "%{$keyword}%");
            });
        }

        if ($after = $request->string('login_after')->toString()) {
            $query->whereDate('login_at', '>=', $after);
        }
        if ($before = $request->string('login_before')->toString()) {
            $query->whereDate('login_at', '<=', $before);
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = $query->orderByDesc('login_at')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (LoginHistory $history) => [
            'id' => $history->id,
            'guard' => $history->user_type === Admin::class ? 'admin' : 'user',
            'user_id' => $history->user_id,
            'ip' => is_string($history->ip) ? $history->ip : inet_ntop($history->ip),
            'address' => $history->address,
            'platform' => $history->platform,
            'device' => $history->device,
            'browser' => $history->browser,
            'login_at' => $history->login_at?->toDateTimeString(),
        ]);

        return json_encode([
            'data' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
