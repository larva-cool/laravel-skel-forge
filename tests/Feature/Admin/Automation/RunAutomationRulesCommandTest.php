<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Automation;

use App\Console\Commands\RunAutomationRulesCommand;
use App\Models\Automation\AutomationRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 执行自动化运营规则命令功能测试
 */
#[CoversClass(RunAutomationRulesCommand::class)]
#[Group('automation')]
class RunAutomationRulesCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('命令执行全部启用规则并输出摘要表格')]
    public function command_runs_all_active_rules(): void
    {
        Notification::fake();

        $user = User::factory()->create(['status' => 1]);
        $user->profile()->update(['birthday' => '1995-06-15']);

        AutomationRule::factory()->create([
            'name' => '生日祝福',
            'trigger_config' => ['advance_days' => 0, 'send_time' => '00:00'],
        ]);

        $this->artisan('automation:run', ['--date' => '2026-06-15'])
            ->expectsOutputToContain('执行完成')
            ->assertSuccessful();

        Notification::assertSentTo($user, \App\Notifications\Automation\AutomationReminderNotification::class);
    }

    #[Test]
    #[TestDox('命令支持 --rule 仅执行指定规则')]
    public function command_supports_rule_option(): void
    {
        Notification::fake();

        $user = User::factory()->create(['status' => 1]);
        $user->profile()->update(['birthday' => '1995-06-15']);

        $rule = AutomationRule::factory()->create([
            'trigger_config' => ['advance_days' => 0, 'send_time' => '00:00'],
        ]);
        AutomationRule::factory()->festival()->create([
            'trigger_config' => ['advance_days' => 0, 'send_time' => '00:00'],
        ]);

        $this->artisan('automation:run', ['--rule' => $rule->id, '--date' => '2026-06-15'])
            ->assertSuccessful();

        $this->assertDatabaseCount('automation_logs', 1);
    }

    #[Test]
    #[TestDox('命令对非法日期格式返回失败')]
    public function command_rejects_invalid_date(): void
    {
        $this->artisan('automation:run', ['--date' => 'abc'])
            ->assertFailed();
    }

    #[Test]
    #[TestDox('没有匹配规则时输出提示并成功退出')]
    public function command_warns_when_nothing_to_run(): void
    {
        $this->artisan('automation:run', ['--rule' => 99999])
            ->expectsOutputToContain('没有需要执行的规则')
            ->assertSuccessful();
    }
}
