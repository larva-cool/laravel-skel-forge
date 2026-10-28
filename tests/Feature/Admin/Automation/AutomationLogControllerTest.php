<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Automation;

use App\Enums\AutomationLogStatus;
use App\Http\Controllers\Admin\AutomationLogController;
use App\Models\Admin\Admin;
use App\Models\Automation\AutomationLog;
use App\Models\Automation\AutomationRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台自动化运营触发日志控制器功能测试
 */
#[CoversClass(AutomationLogController::class)]
#[Group('admin')]
#[Group('automation')]
class AutomationLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // seed_initial_admin_data 迁移已预置超级管理员
        $this->admin = Admin::query()->where('username', 'admin')->firstOrFail();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 创建一条触发日志
     */
    private function makeLog(array $overrides = []): AutomationLog
    {
        $rule = AutomationRule::factory()->create();
        $user = User::factory()->create(['status' => 1]);

        return AutomationLog::factory()->create(array_merge([
            'rule_id' => $rule->id,
            'user_id' => $user->id,
        ], $overrides));
    }

    #[Test]
    #[TestDox('未登录访问日志列表返回 401')]
    public function guest_cannot_list_logs(): void
    {
        $this->getJson('/admin/automation-logs')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可获取日志列表分页数据')]
    public function admin_can_list_logs(): void
    {
        $this->makeLog();
        $this->makeLog();

        $response = $this->actingAsAdmin()->getJson('/admin/automation-logs');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'rule_id', 'user_id', 'trigger_date', 'status', 'status_label', 'rule', 'user']]]);
    }

    #[Test]
    #[TestDox('日志列表支持规则/状态/日期范围筛选')]
    public function list_supports_filters(): void
    {
        $log = $this->makeLog();
        $this->makeLog(['status' => AutomationLogStatus::SUCCESS]);

        $this->actingAsAdmin()->getJson("/admin/automation-logs?rule_id={$log->rule_id}")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAsAdmin()->getJson('/admin/automation-logs?status='.AutomationLogStatus::FAILED->value)
            ->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    #[TestDox('管理员可查看日志详情')]
    public function admin_can_show_log(): void
    {
        $log = $this->makeLog();

        $this->actingAsAdmin()->getJson("/admin/automation-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('id', $log->id);
    }

    #[Test]
    #[TestDox('查看不存在的日志返回 404')]
    public function show_missing_log_returns_404(): void
    {
        $this->actingAsAdmin()->getJson('/admin/automation-logs/999999')->assertNotFound();
    }
}
