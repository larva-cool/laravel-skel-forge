<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Stats;

use App\Enums\AdminStatus;
use App\Enums\UserStatus;
use App\Models\Admin\Admin;
use App\Models\System\LoginHistory;
use App\Models\System\MailCode;
use App\Models\System\PhoneCode;
use App\Models\User;
use App\Models\User\UserStat;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 后台数据概览工具。
 *
 * 汇总用户、管理员、登录、验证码等关键业务指标，供管理员快速了解平台运行状况。
 */
class GetDashboardStats implements Tool
{
    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '获取后台数据概览，包括前台用户总数/今日新增/活跃数、积分与金币存量及每日增减、管理员总数、近期登录记录、短信/邮件验证码发送量等关键指标。当管理员询问平台运行状况、用户规模、数据统计等概览问题时使用。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()
                ->min(1)
                ->max(90)
                ->default(7)
                ->description('统计近 N 天的数据，默认 7 天，最大 90 天'),
        ];
    }

    /**
     * 执行统计。
     */
    public function handle(Request $request): Stringable|string
    {
        $days = $request->integer('days', 7) ?: 7;
        $days = min(max($days, 1), 90);
        $since = Carbon::now()->subDays($days - 1)->startOfDay();

        $userStats = $this->userStats($since, $days);
        $adminStats = $this->adminStats();
        $verificationStats = $this->verificationStats($since);
        $recentLogins = $this->recentLogins();

        return json_encode([
            'range' => [
                'days' => $days,
                'from' => $since->toDateString(),
                'to' => Carbon::now()->toDateString(),
            ],
            'users' => $userStats,
            'admins' => $adminStats,
            'verifications' => $verificationStats,
            'recent_logins' => $recentLogins,
            'generated_at' => Carbon::now()->toDateTimeString(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 用户相关统计。
     *
     * @return array<string, mixed>
     */
    protected function userStats(Carbon $since, int $days): array
    {
        $total = User::query()->count();
        $active = User::query()->where('status', UserStatus::ACTIVE)->count();
        $frozen = User::query()->where('status', UserStatus::FROZEN)->count();
        $newInRange = User::query()->where('created_at', '>=', $since)->count();
        $todayNew = User::query()->whereDate('created_at', Carbon::today())->count();
        $vip = User::query()->whereNotNull('vip_expires_at')
            ->where('vip_expires_at', '>', Carbon::now())
            ->count();
        $todayActive = User::query()->whereDate('last_active_at', Carbon::today())->count();

        // 从 user_stats 表取近 N 天趋势
        $trend = UserStat::query()
            ->where('stat_date', '>=', $since->toDateString())
            ->orderBy('stat_date')
            ->get()
            ->map(fn (UserStat $stat) => [
                'date' => $stat->stat_date?->toDateString(),
                'total' => $stat->total_user_count,
                'new' => $stat->new_user_count,
                'active' => $stat->active_user_count,
                'point_total' => $stat->total_point_count,
                'point_incr' => $stat->incr_point_count,
                'point_decr' => $stat->decr_point_count,
                'coin_total' => $stat->total_coin_count,
                'coin_incr' => $stat->incr_coin_count,
                'coin_decr' => $stat->decr_coin_count,
            ])->all();

        return [
            'total' => $total,
            'active' => $active,
            'frozen' => $frozen,
            'vip' => $vip,
            'new_in_range' => $newInRange,
            'today_new' => $todayNew,
            'today_active' => $todayActive,
            'total_points' => (int) User::query()->sum('available_points'),
            'total_coins' => (int) User::query()->sum('available_coins'),
            'daily_trend' => $trend,
        ];
    }

    /**
     * 管理员相关统计。
     *
     * @return array<string, mixed>
     */
    protected function adminStats(): array
    {
        return [
            'total' => Admin::query()->count(),
            'active' => Admin::query()->where('status', AdminStatus::ACTIVE)->count(),
            'disabled' => Admin::query()->where('status', AdminStatus::DISABLED)->count(),
        ];
    }

    /**
     * 验证码发送量统计。
     *
     * @return array<string, mixed>
     */
    protected function verificationStats(Carbon $since): array
    {
        return [
            'sms_sent' => PhoneCode::query()->where('send_at', '>=', $since)->count(),
            'mail_sent' => MailCode::query()->where('send_at', '>=', $since)->count(),
        ];
    }

    /**
     * 最近登录记录（前台用户 + 管理员，合并取最新 10 条）。
     *
     * @return array<int, array<string, mixed>>
     */
    protected function recentLogins(): array
    {
        return LoginHistory::query()
            ->latest('login_at')
            ->limit(10)
            ->get(['user_type', 'user_id', 'ip', 'address', 'platform', 'device', 'login_at'])
            ->map(fn (LoginHistory $history) => [
                'guard' => class_basename($history->user_type),
                'user_id' => $history->user_id,
                'ip' => $history->ip,
                'address' => $history->address,
                'platform' => $history->platform,
                'device' => $history->device,
                'login_at' => $history->login_at?->toDateTimeString(),
            ])->all();
    }
}
