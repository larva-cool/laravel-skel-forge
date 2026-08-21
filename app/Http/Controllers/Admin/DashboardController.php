<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\System\LoginHistory;
use App\Models\User;
use App\Models\User\UserStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * 后台数据概览控制器
 *
 * 提供工作台 Dashboard 所需的统计数据接口。
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class DashboardController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * 数据概览（统计卡片 + 趋势图 + 最近登录）
     */
    public function stats(Request $request): JsonResponse
    {
        $days = (int) ($request->query('days', 7) ?: 7);
        $days = min(max($days, 1), 90);
        $since = Carbon::now()->subDays($days - 1)->startOfDay();

        return response()->json([
            'cards' => $this->cardStats($since),
            'user_trend' => $this->userTrend($since),
            'login_trend' => $this->loginTrend($since),
            'new_users' => $this->newUsers(),
            'recent_logins' => $this->recentLogins(),
        ]);
    }

    /**
     * 统计卡片数据
     *
     * @return array<string, mixed>
     */
    protected function cardStats(Carbon $since): array
    {
        $totalUsers = User::query()->count();
        $todayNew = User::query()->whereDate('created_at', Carbon::today())->count();
        $todayActive = User::query()->whereDate('last_active_at', Carbon::today())->count();
        $rangeNew = User::query()->where('created_at', '>=', $since)->count();

        $prevSince = $since->copy()->subDays($since->diffInDays(Carbon::now()) + 1);
        $prevRangeNew = User::query()->whereBetween('created_at', [$prevSince, $since])->count();

        $totalAdmins = Admin::query()->count();
        $activeAdmins = Admin::query()->where('status', AdminStatus::ACTIVE)->count();

        $totalLogins = LoginHistory::query()->where('login_at', '>=', $since)->count();
        $todayLogins = LoginHistory::query()->whereDate('login_at', Carbon::today())->count();

        return [
            'total_users' => $totalUsers,
            'today_new_users' => $todayNew,
            'today_active_users' => $todayActive,
            'range_new_users' => $rangeNew,
            'new_users_change' => $this->percentChange($rangeNew, $prevRangeNew),
            'total_admins' => $totalAdmins,
            'active_admins' => $activeAdmins,
            'range_logins' => $totalLogins,
            'today_logins' => $todayLogins,
        ];
    }

    /**
     * 用户增长趋势（近 N 天每日新增 + 累计）
     *
     * @return array<int, array<string, mixed>>
     */
    protected function userTrend(Carbon $since): array
    {
        $stats = UserStat::query()
            ->where('stat_date', '>=', $since->toDateString())
            ->orderBy('stat_date')
            ->get(['stat_date', 'new_user_count', 'total_user_count', 'active_user_count']);

        if ($stats->isNotEmpty()) {
            return $stats->map(fn (UserStat $s) => [
                'date' => $s->stat_date?->toDateString(),
                'new' => $s->new_user_count,
                'total' => $s->total_user_count,
                'active' => $s->active_user_count,
            ])->all();
        }

        // 无统计表数据时，实时聚合
        return $this->fallbackUserTrend($since);
    }

    /**
     * 登录趋势（近 N 天每日登录次数）
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loginTrend(Carbon $since): array
    {
        return LoginHistory::query()
            ->selectRaw('DATE(login_at) as date, COUNT(*) as count')
            ->where('login_at', '>=', $since)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => ['date' => $item->date, 'count' => (int) $item->count])
            ->all();
    }

    /**
     * 最近注册的用户（取 6 条）
     *
     * @return array<int, array<string, mixed>>
     */
    protected function newUsers(): array
    {
        return User::query()
            ->latest('created_at')
            ->limit(6)
            ->get(['id', 'username', 'name', 'avatar', 'created_at'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'username' => $user->username ?? $user->name ?? '用户'.$user->id,
                'avatar' => $user->avatar,
                'created_at' => $user->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * 最近登录记录（取 8 条）
     *
     * @return array<int, array<string, mixed>>
     */
    protected function recentLogins(): array
    {
        return LoginHistory::query()
            ->latest('login_at')
            ->limit(8)
            ->get(['user_type', 'user_id', 'ip', 'address', 'platform', 'device', 'login_at'])
            ->map(fn (LoginHistory $history) => [
                'guard' => class_basename($history->user_type),
                'user_id' => $history->user_id,
                'ip' => $history->ip,
                'address' => $history->address,
                'platform' => $history->platform,
                'device' => $history->device,
                'login_at' => $history->login_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * 实时聚合用户趋势（无统计表数据时的降级方案）
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fallbackUserTrend(Carbon $since): array
    {
        $days = (int) $since->diffInDays(Carbon::now()) + 1;
        $result = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $new = User::query()->whereDate('created_at', $date)->count();
            $active = User::query()->whereDate('last_active_at', $date)->count();

            $result[] = [
                'date' => $date,
                'new' => $new,
                'total' => User::query()->where('created_at', '<=', $date.' 23:59:59')->count(),
                'active' => $active,
            ];
        }

        return $result;
    }

    /**
     * 计算环比变化百分比
     */
    protected function percentChange(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
