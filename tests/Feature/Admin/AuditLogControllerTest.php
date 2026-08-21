<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AuditLogController;
use App\Models\Admin\Admin;
use App\Models\System\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台操作审计日志控制器功能测试
 */
#[CoversClass(AuditLogController::class)]
#[Group('admin')]
#[Group('audit-log')]
class AuditLogControllerTest extends TestCase
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
    #[TestDox('未登录访问审计日志列表返回 401')]
    public function guest_cannot_list_audit_logs(): void
    {
        $this->getJson('/admin/audit-logs')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取审计日志列表返回 200 与分页数据')]
    public function admin_can_list_audit_logs(): void
    {
        AuditLog::factory()->count(3)->create();

        $this->actingAsAdmin()->getJson('/admin/audit-logs')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按操作人ID过滤审计日志')]
    public function admin_can_filter_audit_logs_by_operator_id(): void
    {
        AuditLog::factory()->create(['operator_id' => $this->admin->id, 'title' => '编辑用户']);
        AuditLog::factory()->create(['operator_id' => null, 'title' => '删除菜单']);

        $this->actingAsAdmin()->getJson('/admin/audit-logs?operator_id='.$this->admin->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', '编辑用户');
    }

    #[Test]
    #[TestDox('按路由名称搜索审计日志')]
    public function admin_can_search_audit_logs_by_route(): void
    {
        AuditLog::factory()->create(['route' => 'admin.users.update']);
        AuditLog::factory()->create(['route' => 'admin.roles.store']);

        $this->actingAsAdmin()->getJson('/admin/audit-logs?route=users')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.route', 'admin.users.update');
    }

    #[Test]
    #[TestDox('按请求方法过滤审计日志')]
    public function admin_can_filter_audit_logs_by_method(): void
    {
        AuditLog::factory()->create(['method' => 'DELETE', 'route' => 'admin.users.destroy']);
        AuditLog::factory()->create(['method' => 'POST', 'route' => 'admin.roles.store']);

        $this->actingAsAdmin()->getJson('/admin/audit-logs?method=delete')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.route', 'admin.users.destroy');
    }

    #[Test]
    #[TestDox('仅查看失败操作')]
    public function admin_can_filter_failed_audit_logs(): void
    {
        AuditLog::factory()->create(['route' => 'admin.ok']);
        AuditLog::factory()->failed()->create(['route' => 'admin.bad']);

        $this->actingAsAdmin()->getJson('/admin/audit-logs?is_failed=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.route', 'admin.bad')
            ->assertJsonPath('data.0.is_failed', true);
    }

    #[Test]
    #[TestDox('按时间范围过滤审计日志')]
    public function admin_can_filter_audit_logs_by_date_range(): void
    {
        AuditLog::factory()->create(['route' => 'admin.old', 'created_at' => Carbon::now()->subDays(10)]);
        AuditLog::factory()->create(['route' => 'admin.new', 'created_at' => Carbon::now()]);

        $this->actingAsAdmin()->getJson('/admin/audit-logs?start_date='.Carbon::now()->subDay()->toDateString())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.route', 'admin.new');
    }

    #[Test]
    #[TestDox('非法请求方法参数返回 422')]
    public function invalid_method_returns_422(): void
    {
        $this->actingAsAdmin()->getJson('/admin/audit-logs?method=GET')
            ->assertUnprocessable();
    }

    #[Test]
    #[TestDox('审计日志按操作时间倒序排列')]
    public function audit_logs_ordered_by_created_at_desc(): void
    {
        AuditLog::factory()->create(['route' => 'admin.old', 'created_at' => Carbon::now()->subDay()]);
        AuditLog::factory()->create(['route' => 'admin.new', 'created_at' => Carbon::now()]);

        $this->actingAsAdmin()->getJson('/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.route', 'admin.new')
            ->assertJsonPath('data.1.route', 'admin.old');
    }

    #[Test]
    #[TestDox('获取审计日志详情')]
    public function admin_can_view_audit_log(): void
    {
        $log = AuditLog::factory()->failed()->create(['route' => 'admin.detail', 'title' => '详情操作']);

        $this->actingAsAdmin()->getJson("/admin/audit-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('id', $log->id)
            ->assertJsonPath('route', 'admin.detail')
            ->assertJsonPath('title', '详情操作')
            ->assertJsonPath('status_code', 422)
            ->assertJsonPath('is_failed', true);
    }

    #[Test]
    #[TestDox('获取不存在的审计日志返回 404')]
    public function view_nonexistent_audit_log_returns_404(): void
    {
        $this->actingAsAdmin()->getJson('/admin/audit-logs/99999999')->assertNotFound();
    }
}
