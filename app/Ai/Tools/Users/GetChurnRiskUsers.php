<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Users;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 流失风险用户查询工具（流失预警）。
 *
 * 按「超过 N 天未活跃 + 历史累计登录达到阈值」规则识别曾有活跃度、
 * 近期沉默的流失风险用户，供管理员做召回干预。只读操作。
 */
class GetChurnRiskUsers implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'users.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询流失风险用户名单：筛选状态正常、历史累计登录达到阈值（曾经活跃）但已超过 N 天未活跃的用户，按沉默时长排序返回，并可仅看有资产沉淀（金币/余额）的用户。返回风险用户总数、资产沉淀汇总与分页名单。当管理员询问流失预警、沉默用户召回、用户活跃度下降时使用。只读操作，不能对用户执行冻结或通知。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'inactive_days' => $schema->integer()
                ->min(1)
                ->max(90)
                ->default(7)
                ->description('超过 N 天未活跃视为流失风险，默认 7 天，最大 90 天'),
            'min_login_count' => $schema->integer()
                ->min(0)
                ->default(5)
                ->description('历史累计登录次数下限（过滤注册即沉默的用户），默认 5 次'),
            'with_assets' => $schema->boolean()
                ->default(false)
                ->description('是否仅返回有资产沉淀（金币或余额大于 0）的用户，默认 false'),
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
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $inactiveDays = min(max($request->integer('inactive_days', 7) ?: 7, 1), 90);
        $minLoginCount = max($request->integer('min_login_count', 5) ?: 5, 0);
        $withAssets = $request->boolean('with_assets');
        $page = $request->integer('page', 1) ?: 1;
        $perPage = min(max($request->integer('per_page', 20) ?: 20, 1), 50);
        $cutoff = Carbon::now()->subDays($inactiveDays);

        $paginator = $this->riskQuery($cutoff, $minLoginCount, $withAssets)
            ->orderBy('last_active_at')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);

        $items = $paginator->getCollection()->map(fn (User $user) => [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'login_count' => (int) ($user->login_count ?? 0),
            'inactive_days' => $user->last_active_at !== null
                ? (int) $user->last_active_at->diffInDays(Carbon::now())
                : null,
            'last_active_at' => $user->last_active_at?->toDateTimeString(),
            'is_vip' => $user->vip_expires_at !== null && $user->vip_expires_at->isFuture(),
            'available_coins' => (int) ($user->available_coins ?? 0),
            'balance' => (float) $user->balance,
            'created_at' => $user->created_at?->toDateTimeString(),
        ]);

        return json_encode([
            'criteria' => [
                'inactive_days' => $inactiveDays,
                'min_login_count' => $minLoginCount,
                'with_assets' => $withAssets,
            ],
            'summary' => $this->summary($cutoff, $minLoginCount),
            'data' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'generated_at' => Carbon::now()->toDateTimeString(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * 流失风险用户基础查询：状态正常、历史活跃达标、已沉默超期。
     */
    protected function riskQuery(Carbon $cutoff, int $minLoginCount, bool $withAssets): Builder
    {
        return User::query()
            ->where('status', UserStatus::ACTIVE)
            ->where('login_count', '>=', $minLoginCount)
            ->where(function (Builder $q) use ($cutoff): void {
                $q->where('last_active_at', '<', $cutoff)
                    ->orWhere(function (Builder $q2) use ($cutoff): void {
                        $q2->whereNull('last_active_at')->where('created_at', '<', $cutoff);
                    });
            })
            ->when($withAssets, fn (Builder $q) => $q->where(function (Builder $q2): void {
                $q2->where('available_coins', '>', 0)
                    ->orWhere('balance', '>', 0)
                    ->orWhere('frozen_balance', '>', 0);
            }));
    }

    /**
     * 全量风险集合汇总（与分页无关）：总数、有资产用户数、资产沉淀总额。
     *
     * @return array<string, mixed>
     */
    protected function summary(Carbon $cutoff, int $minLoginCount): array
    {
        $base = $this->riskQuery($cutoff, $minLoginCount, false);
        $withAssets = (clone $base)->where(function (Builder $q): void {
            $q->where('available_coins', '>', 0)
                ->orWhere('balance', '>', 0)
                ->orWhere('frozen_balance', '>', 0);
        });

        return [
            'total_at_risk' => (clone $base)->count(),
            'with_assets_count' => (clone $withAssets)->count(),
            'sunk_coins' => (int) (clone $withAssets)->sum('available_coins'),
            'sunk_balance' => (float) ((clone $withAssets)->sum('balance') + (clone $withAssets)->sum('frozen_balance')),
        ];
    }
}
