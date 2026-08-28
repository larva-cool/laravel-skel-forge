<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Segment;

use App\Console\Commands\SyncSegmentRulesCommand;
use App\Models\User;
use App\Models\UserSegment\Membership;
use App\Models\UserSegment\Segment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * SyncSegmentRulesCommand 命令测试
 */
#[CoversClass(SyncSegmentRulesCommand::class)]
#[Group('admin')]
#[Group('segment')]
class SyncSegmentRulesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(int $points = 0): User
    {
        return User::factory()->create([
            'status' => 1,
            'available_points' => $points,
        ]);
    }

    private function ruleSegment(array $rule, bool $active = true, ?string $code = null): Segment
    {
        return Segment::factory()->rule()->create(array_merge([
            'rule' => $rule,
            'status' => $active,
        ], $code !== null ? ['code' => $code] : []));
    }

    #[Test]
    #[TestDox('空集时输出未找到提示并返回 SUCCESS')]
    public function empty_set_reports_nothing_to_do(): void
    {
        $this->artisan('segments:sync-rules')
            ->expectsOutputToContain('未找到需要同步的规则分群')
            ->assertExitCode(0);
    }

    #[Test]
    #[TestDox('全量模式：同步所有启用规则分群，跳过手动 / 禁用的规则分群')]
    public function full_mode_syncs_all_active_rule_segments(): void
    {
        $segA = $this->ruleSegment([
            'version' => 1,
            'logic' => 'and',
            'conditions' => [['field' => 'available_points', 'op' => '>=', 'value' => 100]],
        ], active: true);
        $segDisabled = $this->ruleSegment([
            'version' => 1,
            'logic' => 'and',
            'conditions' => [['field' => 'available_points', 'op' => '>=', 'value' => 100]],
        ], active: false);
        $manual = Segment::factory()->manual()->create();

        $u = $this->makeUser(200);
        Membership::query()->create([
            'segment_id' => $segA->id,
            'user_id' => $u->id,
            'source' => Membership::SOURCE_RULE,
        ]);
        Membership::query()->create([
            'segment_id' => $segDisabled->id,
            'user_id' => $u->id,
            'source' => Membership::SOURCE_RULE,
        ]);

        $this->artisan('segments:sync-rules')
            ->expectsOutputToContain('开始同步 1 个规则分群')
            ->assertExitCode(0);

        // 启用分群被同步：u 仍命中，保留 1 条
        $this->assertSame(1, Membership::where('segment_id', $segA->id)->count());
        // 禁用分群不被同步：原始数据保留
        $this->assertSame(1, Membership::where('segment_id', $segDisabled->id)->count(), '禁用分群应被跳过，原始数据不动');
        // 手动分群不被同步
        $this->assertSame(0, Membership::where('segment_id', $manual->id)->count());
    }

    #[Test]
    #[TestDox('--segment=ID 模式：仅同步指定分群')]
    public function segment_id_option_filters(): void
    {
        $segA = $this->ruleSegment([
            'version' => 1,
            'logic' => 'and',
            'conditions' => [['field' => 'available_points', 'op' => '>=', 'value' => 100]],
        ]);
        $segB = $this->ruleSegment([
            'version' => 1,
            'logic' => 'and',
            'conditions' => [['field' => 'available_points', 'op' => '>=', 'value' => 500]],
        ]);
        $u = $this->makeUser(600);

        $this->artisan('segments:sync-rules', ['--segment' => [$segA->id]])
            ->expectsOutputToContain('开始同步 1 个规则分群')
            ->assertExitCode(0);

        // segA 同步：u 不满足其规则（>=100 时 600 满足，预期 1 条）
        $this->assertSame(1, Membership::where('segment_id', $segA->id)->count());
        // segB 未被同步
        $this->assertSame(0, Membership::where('segment_id', $segB->id)->count());
    }

    #[Test]
    #[TestDox('--code=CODE 模式：按 code 过滤')]
    public function code_option_filters(): void
    {
        $segA = $this->ruleSegment(
            ['version' => 1, 'logic' => 'and', 'conditions' => [['field' => 'available_points', 'op' => '>=', 'value' => 100]]],
            code: 'vip_users'
        );
        $segB = $this->ruleSegment(
            ['version' => 1, 'logic' => 'and', 'conditions' => [['field' => 'available_points', 'op' => '>=', 'value' => 200]]],
            code: 'high_value'
        );

        $this->artisan('segments:sync-rules', ['--code' => ['vip_users']])
            ->expectsOutputToContain('开始同步 1 个规则分群')
            ->assertExitCode(0);
    }

    #[Test]
    #[TestDox('失败分群不影响其他分群，最终返回 FAILURE')]
    public function per_segment_failure_isolated(): void
    {
        $segOk = $this->ruleSegment([
            'version' => 1,
            'logic' => 'and',
            'conditions' => [['field' => 'available_points', 'op' => '>=', 'value' => 100]],
        ]);
        $segBad = $this->ruleSegment([
            'version' => 1,
            'logic' => 'and',
            'conditions' => [
                ['field' => 'available_points', 'op' => '>=', 'value' => 50],
                // 第二条非法字段
                ['field' => 'nonexistent', 'op' => '=', 'value' => 1],
            ],
        ]);

        $this->makeUser(200); // 命中 segOk

        // 这里只断言：单条坏分群失败，其他仍能完成
        $exit = $this->artisan('segments:sync-rules')
            ->run();
        $this->assertSame(1, $exit, '存在失败分群时整体应返回 FAILURE');
        $this->assertSame(1, Membership::where('segment_id', $segOk->id)->count());
    }

    #[Test]
    #[TestDox('调度注册：schedule:list 能看到 segments:sync-rules')]
    public function schedule_registered(): void
    {
        $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
        $events = $schedule->events();
        $found = false;
        foreach ($events as $event) {
            if (str_contains($event->command, 'segments:sync-rules')) {
                $found = true;
                $this->assertSame('0 2 * * *', $event->expression);
                break;
            }
        }
        $this->assertTrue($found, 'segments:sync-rules 未注册到 schedule');
    }
}
