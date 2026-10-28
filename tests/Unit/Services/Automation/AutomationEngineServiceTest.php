<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Automation;

use App\Enums\AutomationLogStatus;
use App\Enums\AutomationRewardType;
use App\Enums\FestivalDateType;
use App\Enums\PointType;
use App\Enums\UserStatus;
use App\Models\Automation\AutomationFestival;
use App\Models\Automation\AutomationLog;
use App\Models\Automation\AutomationRule;
use App\Models\Point\PointTrade;
use App\Models\User;
use App\Models\UserSegment\Segment;
use App\Notifications\Automation\AutomationReminderNotification;
use App\Services\Automation\AutomationEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Overtrue\ChineseCalendar\Calendar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * AutomationEngineService 单元测试
 */
#[CoversClass(AutomationEngineService::class)]
#[Group('automation')]
class AutomationEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private AutomationEngineService $engine;

    protected function setUp(): void
    {
        parent::setUp();

        // 清除种子迁移预置的节日，保证测试数据隔离
        AutomationFestival::query()->delete();

        $this->engine = new AutomationEngineService;
        Notification::fake();
    }

    private function makeUser(): User
    {
        return User::factory()->create(['status' => UserStatus::ACTIVE]);
    }

    /**
     * 创建生日规则（send_time 置 00:00 保证任何时刻都可触发）
     */
    private function makeBirthdayRule(array $config = []): AutomationRule
    {
        return AutomationRule::factory()->create([
            'trigger_config' => array_merge(['advance_days' => 0, 'send_time' => '00:00'], $config),
        ]);
    }

    #[Test]
    #[TestDox('生日规则：事件日期月日匹配的用户被触达并写入日志')]
    public function birthday_rule_notifies_matching_user(): void
    {
        $user = $this->makeUser();
        $user->profile()->update(['birthday' => '1995-06-15']);
        $other = $this->makeUser();
        $other->profile()->update(['birthday' => '1990-01-01']);

        $rule = $this->makeBirthdayRule();

        $summaries = $this->engine->run(Carbon::parse('2026-06-15'), $rule->id, true);

        $this->assertSame(1, $summaries[0]['sent']);
        $this->assertSame('2026-06-15', $summaries[0]['event_date']);
        Notification::assertSentTo($user, AutomationReminderNotification::class);
        Notification::assertNotSentTo($other, AutomationReminderNotification::class);
        $this->assertDatabaseHas('automation_logs', [
            'rule_id' => $rule->id,
            'user_id' => $user->id,
            'status' => AutomationLogStatus::SUCCESS->value,
        ]);
        $this->assertTrue(
            AutomationLog::query()->where('rule_id', $rule->id)->whereDate('trigger_date', '2026-06-15')->exists()
        );
    }

    #[Test]
    #[TestDox('生日规则幂等：同一事件日期重复执行仅触达一次')]
    public function birthday_rule_is_idempotent(): void
    {
        $user = $this->makeUser();
        $user->profile()->update(['birthday' => '1995-06-15']);
        $rule = $this->makeBirthdayRule();

        $first = $this->engine->run(Carbon::parse('2026-06-15'), $rule->id, true);
        $second = $this->engine->run(Carbon::parse('2026-06-15'), $rule->id, true);

        $this->assertSame(1, $first[0]['sent']);
        $this->assertSame(0, $second[0]['sent']);
        $this->assertSame(1, $second[0]['skipped']);
        $this->assertSame(1, AutomationLog::query()->where('rule_id', $rule->id)->count());
    }

    #[Test]
    #[TestDox('闰年 2-29 出生的用户在平年 3-01 被触达，闰年 2-29 当天被触达')]
    public function leap_year_birthday_falls_back_to_march_first(): void
    {
        $user = $this->makeUser();
        $user->profile()->update(['birthday' => '1996-02-29']); // 闰年出生
        $rule = $this->makeBirthdayRule();

        // 2027 为平年：3 月 1 日触发
        $plain = $this->engine->run(Carbon::parse('2027-03-01'), $rule->id, true);
        $this->assertSame(1, $plain[0]['sent']);

        // 2028 为闰年：2 月 29 日当天触发（3-01 不再对 2-29 出生用户兜底）
        $leap = $this->engine->run(Carbon::parse('2028-02-29'), $rule->id, true);
        $this->assertSame(1, $leap[0]['sent']);

        $leapFallback = $this->engine->run(Carbon::parse('2028-03-01'), $rule->id, true);
        $this->assertSame(0, $leapFallback[0]['sent']);
    }

    #[Test]
    #[TestDox('提前天数：advance_days=1 时提前一天触达，事件日期为生日当天')]
    public function advance_days_shifts_trigger_before_event(): void
    {
        $user = $this->makeUser();
        $user->profile()->update(['birthday' => '1995-06-15']);
        $rule = $this->makeBirthdayRule(['advance_days' => 1]);

        $summaries = $this->engine->run(Carbon::parse('2026-06-14'), $rule->id, true);

        $this->assertSame(1, $summaries[0]['sent']);
        $this->assertSame('2026-06-15', $summaries[0]['event_date']);
        $this->assertTrue(
            AutomationLog::query()->where('rule_id', $rule->id)->whereDate('trigger_date', '2026-06-15')->exists()
        );
    }

    #[Test]
    #[TestDox('公历节日规则：事件日期命中节日时触达全部正常用户')]
    public function solar_festival_rule_notifies_all_active_users(): void
    {
        $festival = AutomationFestival::factory()->create([
            'name' => '国庆节',
            'date_type' => FestivalDateType::SOLAR,
            'month' => 10,
            'day' => 1,
        ]);
        $active = $this->makeUser();
        $frozen = $this->makeUser();
        $frozen->update(['status' => UserStatus::FROZEN]);

        $rule = AutomationRule::factory()->festival()->create([
            'trigger_config' => ['advance_days' => 0, 'send_time' => '00:00'],
            'content' => '祝您{festival}快乐！',
        ]);

        $summaries = $this->engine->run(Carbon::parse('2026-10-01'), $rule->id, true);

        $this->assertSame(1, $summaries[0]['sent']);
        Notification::assertSentTo($active, AutomationReminderNotification::class, function ($notification) use ($festival) {
            return str_contains($notification->content, $festival->name);
        });
        Notification::assertNotSentTo($frozen, AutomationReminderNotification::class);
        $this->assertDatabaseHas('automation_logs', ['festival_id' => $festival->id]);
    }

    #[Test]
    #[TestDox('农历节日规则：中秋节（农历八月十五）按农历转换后匹配')]
    public function lunar_festival_rule_matches_via_lunar_conversion(): void
    {
        AutomationFestival::factory()->lunar()->create([
            'name' => '中秋节',
            'month' => 8,
            'day' => 15,
        ]);
        $user = $this->makeUser();

        $rule = AutomationRule::factory()->festival()->create([
            'trigger_config' => ['advance_days' => 0, 'send_time' => '00:00'],
        ]);

        // 动态推算 2026 年中秋公历日期，避免硬编码
        $solar = (new Calendar)->lunar2solar(2026, 8, 15);
        $eventDate = Carbon::parse("{$solar['solar_year']}-{$solar['solar_month']}-{$solar['solar_day']}");

        $summaries = $this->engine->run($eventDate, $rule->id, true);

        $this->assertSame(1, $summaries[0]['sent']);
        Notification::assertSentTo($user, AutomationReminderNotification::class);
    }

    #[Test]
    #[TestDox('节日规则限定 festival_ids 且事件日期未命中时跳过并备注')]
    public function festival_rule_skips_when_no_festival_matched(): void
    {
        $festival = AutomationFestival::factory()->create([
            'date_type' => FestivalDateType::SOLAR,
            'month' => 10,
            'day' => 1,
        ]);
        $this->makeUser();

        $rule = AutomationRule::factory()->festival()->create([
            'trigger_config' => ['advance_days' => 0, 'send_time' => '00:00', 'festival_ids' => [$festival->id]],
        ]);

        $summaries = $this->engine->run(Carbon::parse('2026-05-01'), $rule->id, true);

        $this->assertSame(0, $summaries[0]['sent']);
        $this->assertSame('事件日期未命中任何节日', $summaries[0]['note']);
    }

    #[Test]
    #[TestDox('注册周年规则：仅触达注册满一年且月日匹配的用户')]
    public function register_anniversary_requires_full_year(): void
    {
        $oldUser = $this->makeUser();
        $oldUser->forceFill(['created_at' => '2024-06-15 10:00:00'])->saveQuietly();
        $newUser = $this->makeUser();
        $newUser->forceFill(['created_at' => '2026-06-15 10:00:00'])->saveQuietly(); // 当天注册不算周年

        $rule = AutomationRule::factory()->registerAnniversary()->create([
            'trigger_config' => ['advance_days' => 0, 'send_time' => '00:00'],
        ]);

        $summaries = $this->engine->run(Carbon::parse('2026-06-15'), $rule->id, true);

        $this->assertSame(1, $summaries[0]['sent']);
        Notification::assertSentTo($oldUser, AutomationReminderNotification::class);
        Notification::assertNotSentTo($newUser, AutomationReminderNotification::class);
    }

    #[Test]
    #[TestDox('绑定分群：仅触达分群成员')]
    public function segment_bound_rule_only_reaches_members(): void
    {
        $member = $this->makeUser();
        $member->profile()->update(['birthday' => '1995-06-15']);
        $outsider = $this->makeUser();
        $outsider->profile()->update(['birthday' => '1995-06-15']);

        $segment = Segment::factory()->manual()->create();
        $segment->users()->attach($member->id, ['source' => 1, 'created_at' => now()]);

        $rule = AutomationRule::factory()->create([
            'segment_id' => $segment->id,
            'trigger_config' => ['advance_days' => 0, 'send_time' => '00:00'],
        ]);

        $summaries = $this->engine->run(Carbon::parse('2026-06-15'), $rule->id, true);

        $this->assertSame(1, $summaries[0]['sent']);
        Notification::assertSentTo($member, AutomationReminderNotification::class);
        Notification::assertNotSentTo($outsider, AutomationReminderNotification::class);
    }

    #[Test]
    #[TestDox('积分奖励：触达后发放积分并记录 automation 类型流水')]
    public function point_reward_is_granted(): void
    {
        $user = $this->makeUser();
        $user->profile()->update(['birthday' => '1995-06-15']);

        $rule = AutomationRule::factory()->withPointReward(100)->create([
            'trigger_config' => ['advance_days' => 0, 'send_time' => '00:00'],
        ]);

        $this->engine->run(Carbon::parse('2026-06-15'), $rule->id, true);

        $this->assertSame(100, $user->fresh()->available_points);
        $this->assertDatabaseHas('point_trades', [
            'user_id' => $user->id,
            'points' => 100,
            'type' => PointType::TYPE_AUTOMATION->value,
        ]);
        $trade = PointTrade::query()->where('user_id', $user->id)->first();
        $this->assertSame($rule->id, $trade->source_id);
    }

    #[Test]
    #[TestDox('无奖励规则不产生积分流水')]
    public function none_reward_skips_grant(): void
    {
        $user = $this->makeUser();
        $user->profile()->update(['birthday' => '1995-06-15']);
        $rule = $this->makeBirthdayRule();

        $this->engine->run(Carbon::parse('2026-06-15'), $rule->id, true);

        $this->assertSame(0, PointTrade::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    #[TestDox('未到 send_time 的规则在非强制模式下跳过')]
    public function rule_before_send_time_is_skipped(): void
    {
        $user = $this->makeUser();
        $user->profile()->update(['birthday' => '1995-06-15']);
        $rule = $this->makeBirthdayRule(['send_time' => '23:59']);

        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00'));
        $summaries = $this->engine->run(Carbon::parse('2026-06-15'), $rule->id, false);

        $this->assertSame([], $summaries);

        // 强制模式忽略时间窗
        $forced = $this->engine->run(Carbon::parse('2026-06-15'), $rule->id, true);
        $this->assertSame(1, $forced[0]['sent']);
        Carbon::setTestNow();
    }

    #[Test]
    #[TestDox('禁用规则不执行')]
    public function disabled_rule_is_not_executed(): void
    {
        $user = $this->makeUser();
        $user->profile()->update(['birthday' => '1995-06-15']);
        AutomationRule::factory()->disabled()->create([
            'trigger_config' => ['advance_days' => 0, 'send_time' => '00:00'],
        ]);

        $summaries = $this->engine->run(Carbon::parse('2026-06-15'));

        $this->assertSame([], $summaries);
        Notification::assertNothingSent();
    }

    #[Test]
    #[TestDox('模板变量 {nickname} 被替换为用户昵称')]
    public function template_variables_are_rendered(): void
    {
        $user = $this->makeUser();
        $user->update(['name' => '小明']);
        $user->profile()->update(['birthday' => '1995-06-15']);

        $rule = AutomationRule::factory()->create([
            'title' => '生日快乐，{nickname}！',
            'content' => '{nickname}，今天是 {date}，祝你生日快乐！',
            'trigger_config' => ['advance_days' => 0, 'send_time' => '00:00'],
        ]);

        $this->engine->run(Carbon::parse('2026-06-15'), $rule->id, true);

        Notification::assertSentTo($user, AutomationReminderNotification::class, function ($notification) {
            return $notification->title === '生日快乐，小明！'
                && str_contains($notification->content, '小明，今天是 2026-06-15');
        });
    }

    #[Test]
    #[TestDox('触达失败记入失败日志，重跑时失败记录可重试')]
    public function failed_log_can_be_retried(): void
    {
        $user = $this->makeUser();
        $user->profile()->update(['birthday' => '1995-06-15']);
        $rule = AutomationRule::factory()->withPointReward(0)->create([
            'reward_type' => AutomationRewardType::NONE,
            'trigger_config' => ['advance_days' => 0, 'send_time' => '00:00'],
        ]);

        // 预置一条失败日志（模拟上一轮触达失败）
        AutomationLog::factory()->failed('微信接口超时')->create([
            'rule_id' => $rule->id,
            'user_id' => $user->id,
            'trigger_date' => '2026-06-15',
        ]);

        $summaries = $this->engine->run(Carbon::parse('2026-06-15'), $rule->id, true);

        $this->assertSame(1, $summaries[0]['sent']);
        $log = AutomationLog::query()->where('rule_id', $rule->id)->where('user_id', $user->id)->sole();
        $this->assertSame(AutomationLogStatus::SUCCESS, $log->status);
    }
}
