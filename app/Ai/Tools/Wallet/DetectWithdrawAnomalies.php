<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Wallet;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\User;
use App\Models\Wallet\Withdraw;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 提现异常扫描工具（反作弊/风控）。
 *
 * 对近 N 天的提现申请执行规则化异常扫描：大额聚集、短时密集申请、
 * 新注册即提现、同收款账户多账号、同 IP 多账号。只读操作。
 */
class DetectWithdrawAnomalies implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'wallet.withdraws';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '扫描近 N 天提现申请中的异常模式，用于反作弊与风控排查：大额提现聚集、同一用户短时密集申请、新注册用户即提现、同一收款账户被多个账号使用、同一 IP 被多个账号使用。返回各类异常的命中数量与样本单据（收款账户脱敏展示）。当管理员询问提现风险、异常提现、欺诈排查时使用。只读操作。';
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
                ->max(30)
                ->default(7)
                ->description('扫描近 N 天的提现申请，默认 7 天，最大 30 天'),
            'large_amount' => $schema->number()
                ->min(0)
                ->default(1000)
                ->description('大额提现判定阈值（元），默认 1000'),
            'burst_count' => $schema->integer()
                ->min(2)
                ->max(20)
                ->default(3)
                ->description('短时密集申请判定笔数，默认 3 笔'),
            'burst_hours' => $schema->integer()
                ->min(1)
                ->max(72)
                ->default(24)
                ->description('短时密集申请时间窗（小时），默认 24 小时'),
            'new_user_days' => $schema->integer()
                ->min(1)
                ->max(30)
                ->default(7)
                ->description('新注册判定天数（注册后 N 天内提现视为异常），默认 7 天'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(10)
                ->description('每类异常返回的样本条数上限，默认 10，最大 50'),
        ];
    }

    /**
     * 执行扫描。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $days = min(max($request->integer('days', 7) ?: 7, 1), 30);
        $largeAmount = max((float) $request->float('large_amount', 1000), 0.0);
        $burstCount = min(max($request->integer('burst_count', 3) ?: 3, 2), 20);
        $burstHours = min(max($request->integer('burst_hours', 24) ?: 24, 1), 72);
        $newUserDays = min(max($request->integer('new_user_days', 7) ?: 7, 1), 30);
        $limit = min(max($request->integer('limit', 10) ?: 10, 1), 50);
        $since = Carbon::now()->subDays($days - 1)->startOfDay();

        return json_encode([
            'range' => [
                'days' => $days,
                'from' => $since->toDateString(),
                'to' => Carbon::now()->toDateString(),
            ],
            'thresholds' => [
                'large_amount' => $largeAmount,
                'burst_count' => $burstCount,
                'burst_hours' => $burstHours,
                'new_user_days' => $newUserDays,
            ],
            'large_amount_withdraws' => $this->largeAmountWithdraws($since, $largeAmount, $limit),
            'burst_applications' => $this->burstApplications($since, $burstCount, $burstHours, $limit),
            'new_user_withdraws' => $this->newUserWithdraws($since, $newUserDays, $limit),
            'shared_accounts' => $this->sharedAccounts($since, $limit),
            'shared_ips' => $this->sharedIps($since, $limit),
            'generated_at' => Carbon::now()->toDateTimeString(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * 大额提现聚集。
     *
     * @return array<string, mixed>
     */
    protected function largeAmountWithdraws(Carbon $since, float $threshold, int $limit): array
    {
        $base = Withdraw::query()
            ->where('created_at', '>=', $since)
            ->where('amount', '>=', $threshold);

        $rows = (clone $base)
            ->with('owner')
            ->orderByDesc('amount')
            ->limit($limit)
            ->get()
            ->map(fn (Withdraw $w) => $this->withdrawRow($w))
            ->all();

        return ['total' => (clone $base)->count(), 'samples' => $rows];
    }

    /**
     * 同一用户短时密集申请（时间窗内达到阈值笔数）。
     *
     * @return array<string, mixed>
     */
    protected function burstApplications(Carbon $since, int $burstCount, int $burstHours, int $limit): array
    {
        $candidates = Withdraw::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('owner_type, owner_id, COUNT(*) as c')
            ->groupBy('owner_type', 'owner_id')
            ->having('c', '>=', $burstCount)
            ->limit(50)
            ->get();

        $flagged = [];
        foreach ($candidates as $candidate) {
            $withdraws = Withdraw::query()
                ->where('created_at', '>=', $since)
                ->where('owner_type', $candidate->getAttribute('owner_type'))
                ->where('owner_id', (int) $candidate->getAttribute('owner_id'))
                ->with('owner')
                ->orderBy('created_at')
                ->get();

            $times = $withdraws->map(fn (Withdraw $w) => $w->created_at->getTimestamp())->all();
            if (! $this->hasBurstWindow($times, $burstHours, $burstCount)) {
                continue;
            }

            $flagged[] = [
                'user' => $this->ownerRow($withdraws->first()),
                'application_count' => $withdraws->count(),
                'total_amount' => (float) $withdraws->sum('amount'),
                'samples' => $withdraws->take($limit)->map(fn (Withdraw $w) => $this->withdrawRow($w))->values()->all(),
            ];
        }

        return [
            'total' => count($flagged),
            'truncated' => $candidates->count() >= 50,
            'samples' => array_slice($flagged, 0, $limit),
        ];
    }

    /**
     * 新注册用户即提现（注册后 N 天内发起）。
     *
     * @return array<string, mixed>
     */
    protected function newUserWithdraws(Carbon $since, int $newUserDays, int $limit): array
    {
        // 命中条件的用户注册时间必然不早于 $since - N 天，先圈定候选用户
        $userCreatedMap = User::query()
            ->where('created_at', '>=', $since->copy()->subDays($newUserDays))
            ->pluck('created_at', 'id');

        if ($userCreatedMap->isEmpty()) {
            return ['total' => 0, 'samples' => []];
        }

        $matched = Withdraw::query()
            ->where('created_at', '>=', $since)
            ->where('owner_type', (new User)->getMorphClass())
            ->whereIn('owner_id', $userCreatedMap->keys()->all())
            ->with('owner')
            ->orderByDesc('created_at')
            ->get()
            ->filter(function (Withdraw $w) use ($userCreatedMap, $newUserDays): bool {
                $registeredAt = $userCreatedMap->get($w->owner_id);

                return $registeredAt !== null
                    && Carbon::parse($registeredAt)->greaterThanOrEqualTo($w->created_at->copy()->subDays($newUserDays));
            })
            ->values();

        return [
            'total' => $matched->count(),
            'samples' => $matched->take($limit)->map(fn (Withdraw $w) => $this->withdrawRow($w, true))->all(),
        ];
    }

    /**
     * 同一收款账户被多个账号使用（收款账户脱敏）。
     *
     * @return array<string, mixed>
     */
    protected function sharedAccounts(Carbon $since, int $limit): array
    {
        $groups = Withdraw::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('account_info->account')
            ->select('account_type')
            ->addSelect('account_info->account as account')
            ->selectRaw('COUNT(DISTINCT owner_id) as user_count, COUNT(*) as total_count, SUM(amount) as total_amount')
            ->groupBy('account_type', 'account_info->account')
            ->having('user_count', '>', 1)
            ->orderByDesc('user_count')
            ->limit($limit)
            ->get();

        $samples = $groups->map(function (Withdraw $group) use ($since): array {
            $account = (string) $group->getAttribute('account');
            $userIds = Withdraw::query()
                ->where('created_at', '>=', $since)
                ->where('account_type', $group->account_type->value)
                ->where('account_info->account', $account)
                ->distinct()
                ->pluck('owner_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return [
                'account_type' => $group->account_type->value,
                'account_type_label' => $group->account_type->label(),
                'account_masked' => $this->maskAccount($account),
                'user_count' => (int) $group->getAttribute('user_count'),
                'total_count' => (int) $group->getAttribute('total_count'),
                'total_amount' => (float) $group->getAttribute('total_amount'),
                'user_ids' => $userIds,
            ];
        })->all();

        return ['total' => count($samples), 'samples' => $samples];
    }

    /**
     * 同一下单 IP 被多个账号使用。
     *
     * @return array<string, mixed>
     */
    protected function sharedIps(Carbon $since, int $limit): array
    {
        $groups = Withdraw::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('client_ip')
            ->select('client_ip')
            ->selectRaw('COUNT(DISTINCT owner_id) as user_count, COUNT(*) as total_count, SUM(amount) as total_amount')
            ->groupBy('client_ip')
            ->having('user_count', '>', 1)
            ->orderByDesc('user_count')
            ->limit($limit)
            ->get();

        $samples = $groups->map(function (Withdraw $group) use ($since): array {
            $userIds = Withdraw::query()
                ->where('created_at', '>=', $since)
                ->where('client_ip', $group->getAttribute('client_ip'))
                ->distinct()
                ->pluck('owner_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return [
                'client_ip' => $group->getAttribute('client_ip'),
                'user_count' => (int) $group->getAttribute('user_count'),
                'total_count' => (int) $group->getAttribute('total_count'),
                'total_amount' => (float) $group->getAttribute('total_amount'),
                'user_ids' => $userIds,
            ];
        })->all();

        return ['total' => count($samples), 'samples' => $samples];
    }

    /**
     * 判断时间戳序列中是否存在指定时间窗内达到阈值笔数的密集段。
     *
     * @param  array<int, int>  $times  升序时间戳
     */
    protected function hasBurstWindow(array $times, int $burstHours, int $burstCount): bool
    {
        $windowSeconds = $burstHours * 3600;
        $j = 0;
        $n = count($times);

        for ($i = 0; $i < $n; $i++) {
            while ($j < $n && $windowSeconds >= $times[$j] - $times[$i]) {
                $j++;
            }
            if ($j - $i >= $burstCount) {
                return true;
            }
        }

        return false;
    }

    /**
     * 提现单据输出行。
     *
     * @return array<string, mixed>
     */
    protected function withdrawRow(Withdraw $withdraw, bool $withRegisteredAt = false): array
    {
        $row = [
            'id' => $withdraw->id,
            'order_no' => $withdraw->order_no,
            'user' => $this->ownerRow($withdraw),
            'amount' => (float) $withdraw->amount,
            'status' => $withdraw->status->value,
            'status_label' => $withdraw->status->label(),
            'created_at' => $withdraw->created_at?->toDateTimeString(),
        ];

        if ($withRegisteredAt && $withdraw->owner instanceof User) {
            $row['user_registered_at'] = $withdraw->owner->created_at?->toDateTimeString();
        }

        return $row;
    }

    /**
     * 持有者（用户）输出行。
     *
     * @return array<string, mixed>|null
     */
    protected function ownerRow(Withdraw $withdraw): ?array
    {
        return $withdraw->owner instanceof User ? [
            'id' => $withdraw->owner->id,
            'username' => $withdraw->owner->username,
            'name' => $withdraw->owner->name,
        ] : null;
    }

    /**
     * 收款账户脱敏（保留首尾各 2 位）。
     */
    protected function maskAccount(string $account): string
    {
        $length = mb_strlen($account);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return mb_substr($account, 0, 2).str_repeat('*', max($length - 4, 3)).mb_substr($account, -2);
    }
}
