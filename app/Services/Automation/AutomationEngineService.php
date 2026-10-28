<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services\Automation;

use App\Enums\AutomationChannel;
use App\Enums\AutomationLogStatus;
use App\Enums\AutomationRewardType;
use App\Enums\AutomationTriggerType;
use App\Enums\CoinType;
use App\Enums\FestivalDateType;
use App\Enums\PointType;
use App\Enums\UserStatus;
use App\Models\Automation\AutomationFestival;
use App\Models\Automation\AutomationLog;
use App\Models\Automation\AutomationRule;
use App\Models\User;
use App\Notifications\Automation\AutomationReminderNotification;
use App\Support\CoinHelper;
use App\Support\LunarCalendarHelper;
use App\Support\PointHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 自动化运营引擎服务
 *
 * 负责扫描启用的自动化规则，匹配目标用户并触发通知与奖励：
 *  - 生日提醒：按 user_profiles.birthday 月日匹配，闰年 2-29 出生在平年顺延 3-01
 *  - 节日提醒：按节日表匹配（公历直比、农历经 LunarCalendarHelper 转换）
 *  - 注册周年：按 users.created_at 月日匹配（仅注册满一年后的周年）
 *
 * 幂等：automation_logs 表 (rule_id, user_id, trigger_date) 唯一索引保证同一事件
 * 对同一用户仅触发一次，重复执行/手动重推不会产生重复触达与重复奖励。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationEngineService
{
    /**
     * 每批处理的用户数
     */
    protected const CHUNK_SIZE = 200;

    /**
     * 执行一轮自动化规则扫描
     *
     * @param  Carbon|null  $runDate  运行日期（默认今天），事件日期 = 运行日期 + 规则提前天数
     * @param  int|null  $ruleId  仅执行指定规则（手动触发场景）
     * @param  bool  $force  是否忽略 send_time 时间窗限制（手动触发时为 true）
     * @return array<int, array<string, mixed>> 每条规则的执行摘要
     *
     * @throws Throwable
     */
    public function run(?Carbon $runDate = null, ?int $ruleId = null, bool $force = false): array
    {
        $runDate = ($runDate ?: now())->copy()->startOfDay();
        $now = now();

        $rules = AutomationRule::query()
            ->active()
            ->when($ruleId !== null, fn (Builder $q) => $q->where('id', $ruleId))
            ->orderBy('id')
            ->get();

        $summaries = [];

        foreach ($rules as $rule) {
            // 非强制模式下，未到规则配置的推送时间则跳过（等待下一轮调度）
            if (! $force && $now->format('H:i') < $rule->sendTime()) {
                continue;
            }

            $summaries[] = $this->runRule($rule, $runDate);
        }

        return $summaries;
    }

    /**
     * 执行单条规则
     *
     * @return array<string, mixed> 执行摘要
     */
    public function runRule(AutomationRule $rule, Carbon $runDate): array
    {
        $eventDate = $runDate->copy()->addDays($rule->advanceDays());

        // 节日类规则：先解析事件日期命中的节日，未命中则直接跳过
        $festival = null;
        if ($rule->trigger_type === AutomationTriggerType::FESTIVAL) {
            $festival = $this->matchFestival($rule, $eventDate);

            if ($festival === null) {
                return $this->summary($rule, $eventDate, 0, 0, 0, '事件日期未命中任何节日');
            }
        }

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        $this->buildUserQuery($rule, $eventDate)
            ->chunkById(self::CHUNK_SIZE, function (Collection $users) use ($rule, $eventDate, $festival, &$sent, &$failed, &$skipped): void {
                foreach ($users as $user) {
                    $result = $this->triggerUser($rule, $user, $eventDate, $festival);
                    match ($result) {
                        'sent' => $sent++,
                        'failed' => $failed++,
                        default => $skipped++,
                    };
                }
            });

        // 记录最近一次触发概况
        $rule->forceFill([
            'last_triggered_at' => now(),
            'last_triggered_count' => $sent,
        ])->saveQuietly();

        return $this->summary($rule, $eventDate, $sent, $failed, $skipped);
    }

    /**
     * 对单个用户触发通知与奖励（幂等）
     *
     * @return string sent=已触达 failed=失败 skipped=已触发过跳过
     */
    protected function triggerUser(AutomationRule $rule, User $user, Carbon $eventDate, ?AutomationFestival $festival): string
    {
        // 允许重试失败记录：清除同一事件的 FAILED 日志后重新占位
        AutomationLog::query()
            ->where('rule_id', $rule->id)
            ->where('user_id', $user->id)
            ->whereDate('trigger_date', $eventDate->toDateString())
            ->where('status', AutomationLogStatus::FAILED)
            ->delete();

        try {
            // 先写日志占位，利用唯一索引保证并发/重入幂等
            $log = AutomationLog::query()->create([
                'rule_id' => $rule->id,
                'user_id' => $user->id,
                'trigger_date' => $eventDate->toDateString(),
                'festival_id' => $festival?->id,
                'channels' => $rule->channels,
                'status' => AutomationLogStatus::SUCCESS,
            ]);
        } catch (UniqueConstraintViolationException) {
            return 'skipped';
        } catch (Throwable $e) {
            Log::error('自动化规则触发日志写入失败', [
                'rule_id' => $rule->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }

        try {
            [$title, $content] = $this->renderTemplate($rule, $user, $eventDate, $festival);

            $user->notify(new AutomationReminderNotification(
                title: $title,
                content: $content,
                channels: $this->resolveChannels($rule),
            ));

            $this->grantReward($rule, $user, $festival);

            return 'sent';
        } catch (Throwable $e) {
            $log->forceFill([
                'status' => AutomationLogStatus::FAILED,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ])->saveQuietly();

            Log::error('自动化规则触达失败', [
                'rule_id' => $rule->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }

    /**
     * 构建目标用户查询
     */
    protected function buildUserQuery(AutomationRule $rule, Carbon $eventDate): Builder
    {
        $query = User::query()->where('status', UserStatus::ACTIVE);

        match ($rule->trigger_type) {
            AutomationTriggerType::BIRTHDAY => $this->applyBirthdayMatch($query, $eventDate),
            AutomationTriggerType::REGISTER_ANNIVERSARY => $this->applyAnniversaryMatch($query, $eventDate),
            AutomationTriggerType::FESTIVAL => null, // 节日类面向全量（或分群）用户，无月日过滤
        };

        // 绑定分群时，仅触达分群成员
        if ($rule->segment_id !== null) {
            $query->whereIn('id', function ($q) use ($rule): void {
                $q->select('user_id')
                    ->from('user_segment_memberships')
                    ->where('segment_id', $rule->segment_id);
            });
        }

        return $query;
    }

    /**
     * 生日匹配：按事件日期的月日匹配 user_profiles.birthday
     *
     * 闰年策略：平年 3 月 1 日时同时匹配 2 月 29 日出生的用户。
     */
    protected function applyBirthdayMatch(Builder $query, Carbon $eventDate): void
    {
        $month = (int) $eventDate->format('n');
        $day = (int) $eventDate->format('j');
        $includeLeapBirthday = $month === 3 && $day === 1 && ! $eventDate->isLeapYear();

        $query->whereHas('profile', function (Builder $q) use ($month, $day, $includeLeapBirthday): void {
            $q->where(function (Builder $q2) use ($month, $day, $includeLeapBirthday): void {
                $q2->whereMonth('birthday', $month)->whereDay('birthday', $day);

                if ($includeLeapBirthday) {
                    $q2->orWhere(function (Builder $q3): void {
                        $q3->whereMonth('birthday', 2)->whereDay('birthday', 29);
                    });
                }
            });
        });
    }

    /**
     * 注册周年匹配：按事件日期的月日匹配 users.created_at，且注册年份早于事件年份
     */
    protected function applyAnniversaryMatch(Builder $query, Carbon $eventDate): void
    {
        $query->whereMonth('created_at', (int) $eventDate->format('n'))
            ->whereDay('created_at', (int) $eventDate->format('j'))
            ->whereYear('created_at', '<', (int) $eventDate->format('Y'));
    }

    /**
     * 解析事件日期命中的节日
     *
     * 规则配置 festival_ids 非空时仅在其范围内匹配；公历节日直比月日，农历节日经转换比对。
     */
    protected function matchFestival(AutomationRule $rule, Carbon $eventDate): ?AutomationFestival
    {
        $festivals = AutomationFestival::query()
            ->active()
            ->when($rule->festivalIds() !== [], fn (Builder $q) => $q->whereIn('id', $rule->festivalIds()))
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $lunar = null;

        foreach ($festivals as $festival) {
            if ($festival->date_type === FestivalDateType::SOLAR) {
                if ($festival->month === (int) $eventDate->format('n') && $festival->day === (int) $eventDate->format('j')) {
                    return $festival;
                }

                continue;
            }

            // 农历节日：整个循环只做一次公历→农历转换
            $lunar ??= LunarCalendarHelper::solarToLunar($eventDate);

            if (! $lunar['is_leap'] && $lunar['month'] === $festival->month && $lunar['day'] === $festival->day) {
                return $festival;
            }
        }

        return null;
    }

    /**
     * 渲染通知模板
     *
     * 支持变量：{nickname} 用户昵称、{festival} 节日名称、{date} 事件日期
     *
     * @return array{0: string, 1: string} [标题, 内容]
     */
    protected function renderTemplate(AutomationRule $rule, User $user, Carbon $eventDate, ?AutomationFestival $festival): array
    {
        $variables = [
            '{nickname}' => $user->name ?? $user->username,
            '{festival}' => $festival?->name ?? '',
            '{date}' => $eventDate->toDateString(),
        ];

        return [
            strtr($rule->title, $variables),
            strtr($rule->content, $variables),
        ];
    }

    /**
     * 解析规则配置的触达渠道为 Laravel 通知通道名
     *
     * @return array<int, string>
     */
    protected function resolveChannels(AutomationRule $rule): array
    {
        return collect($rule->channels ?? [])
            ->map(fn (string $channel) => AutomationChannel::tryFrom($channel)?->toChannel())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * 发放奖励（积分/金币），来源模型为规则本身
     */
    protected function grantReward(AutomationRule $rule, User $user, ?AutomationFestival $festival): void
    {
        if ($rule->reward_type === AutomationRewardType::NONE || $rule->reward_amount <= 0) {
            return;
        }

        $scene = match ($rule->trigger_type) {
            AutomationTriggerType::BIRTHDAY => '生日',
            AutomationTriggerType::FESTIVAL => ($festival?->name ?? '节日').'问候',
            AutomationTriggerType::REGISTER_ANNIVERSARY => '注册周年',
        };

        if ($rule->reward_type === AutomationRewardType::POINT) {
            PointHelper::incr($user->id, $rule->reward_amount, $rule, PointType::TYPE_AUTOMATION, "{$scene}奖励");
        } elseif ($rule->reward_type === AutomationRewardType::COIN) {
            CoinHelper::incr($user->id, $rule->reward_amount, $rule, CoinType::TYPE_AUTOMATION, "{$scene}奖励");
        }
    }

    /**
     * 组装执行摘要
     *
     * @return array<string, mixed>
     */
    protected function summary(AutomationRule $rule, Carbon $eventDate, int $sent, int $failed, int $skipped, ?string $note = null): array
    {
        return [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'trigger_type' => $rule->trigger_type->value,
            'event_date' => $eventDate->toDateString(),
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'note' => $note,
        ];
    }
}
