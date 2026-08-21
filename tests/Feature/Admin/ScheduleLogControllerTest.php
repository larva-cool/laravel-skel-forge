<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ScheduleStatus;
use App\Http\Controllers\Admin\ScheduleLogController;
use App\Models\Admin\Admin;
use App\Models\System\ScheduleLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台调度任务日志控制器功能测试
 */
#[CoversClass(ScheduleLogController::class)]
#[Group('admin')]
#[Group('schedule-log')]
class ScheduleLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    #[TestDox('未登录访问调度日志列表返回 401')]
    public function guest_cannot_list_schedule_logs(): void
    {
        $this->getJson('/admin/schedule-logs')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取调度日志列表返回 200 与分页数据')]
    public function admin_can_list_schedule_logs(): void
    {
        ScheduleLog::factory()->count(3)->create();

        $this->actingAsAdmin()->getJson('/admin/schedule-logs')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按任务名称搜索调度日志')]
    public function admin_can_search_schedule_logs_by_name(): void
    {
        ScheduleLog::factory()->create(['name' => 'model:prune']);
        ScheduleLog::factory()->create(['name' => 'stats:user']);

        $this->actingAsAdmin()->getJson('/admin/schedule-logs?name=prune')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'model:prune');
    }

    #[Test]
    #[TestDox('按状态过滤调度日志')]
    public function admin_can_filter_schedule_logs_by_status(): void
    {
        ScheduleLog::factory()->create(['name' => 'stats:user']);
        ScheduleLog::factory()->failed()->create(['name' => 'model:prune']);

        $this->actingAsAdmin()->getJson('/admin/schedule-logs?status='.ScheduleStatus::FAILED->value)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'model:prune');
    }

    #[Test]
    #[TestDox('非法状态参数返回 422')]
    public function invalid_status_returns_422(): void
    {
        $this->actingAsAdmin()->getJson('/admin/schedule-logs?status=99')
            ->assertUnprocessable();
    }

    #[Test]
    #[TestDox('调度日志按开始时间倒序排列')]
    public function schedule_logs_ordered_by_started_at_desc(): void
    {
        ScheduleLog::factory()->create(['name' => 'old:task', 'started_at' => Carbon::now()->subDay()]);
        ScheduleLog::factory()->create(['name' => 'new:task', 'started_at' => Carbon::now()]);

        $this->actingAsAdmin()->getJson('/admin/schedule-logs')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'new:task')
            ->assertJsonPath('data.1.name', 'old:task');
    }

    #[Test]
    #[TestDox('获取调度日志详情')]
    public function admin_can_view_schedule_log(): void
    {
        $log = ScheduleLog::factory()->failed()->create(['name' => 'detail:task']);

        $this->actingAsAdmin()->getJson("/admin/schedule-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('id', $log->id)
            ->assertJsonPath('name', 'detail:task')
            ->assertJsonPath('status', ScheduleStatus::FAILED->value)
            ->assertJsonPath('status_text', ScheduleStatus::FAILED->label());
    }

    #[Test]
    #[TestDox('获取不存在的调度日志返回 404')]
    public function view_nonexistent_schedule_log_returns_404(): void
    {
        $this->actingAsAdmin()->getJson('/admin/schedule-logs/99999999')->assertNotFound();
    }
}
