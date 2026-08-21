<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\RecordAuditLog;
use App\Models\Admin\Admin;
use App\Models\System\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 操作审计日志中间件功能测试
 */
#[CoversClass(RecordAuditLog::class)]
#[Group('admin')]
#[Group('audit-log')]
class RecordAuditLogTest extends TestCase
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
    #[TestDox('GET 请求不产生审计记录')]
    public function get_request_is_not_recorded(): void
    {
        $this->actingAsAdmin()->getJson('/admin/users')->assertOk();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[Test]
    #[TestDox('写操作记录操作人、路由、方法与状态码')]
    public function write_request_is_recorded_with_operator(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin()
            ->putJson("/admin/users/{$user->id}/toggle-status")
            ->assertSuccessful();

        $log = AuditLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($this->admin->id, $log->operator_id);
        $this->assertSame($this->admin->name, $log->operator_name);
        $this->assertSame('admin.users.toggle-status', $log->route);
        $this->assertSame('PUT', $log->method);
        $this->assertSame(200, $log->status_code);
        $this->assertNotNull($log->runtime);
    }

    #[Test]
    #[TestDox('审计记录写入配置的操作描述')]
    public function recorded_log_uses_configured_title(): void
    {
        $this->actingAsAdmin()->postJson('/admin/roles', [
            'name' => 'editor',
            'display_name' => '编辑',
        ])->assertSuccessful();

        $log = AuditLog::query()->where('route', 'admin.roles.store')->first();

        $this->assertNotNull($log);
        $this->assertSame('新增角色', $log->title);
    }

    #[Test]
    #[TestDox('敏感字段在入库前被脱敏')]
    public function sensitive_fields_are_masked(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/reset-password", [
            'password' => 'secret-plain-text',
            'password_confirmation' => 'secret-plain-text',
        ]);

        $log = AuditLog::query()->where('route', 'admin.users.reset-password')->first();

        $this->assertNotNull($log);
        $this->assertSame(RecordAuditLog::MASK, $log->payload['password']);
        $this->assertSame(RecordAuditLog::MASK, $log->payload['password_confirmation']);
        $this->assertStringNotContainsString('secret-plain-text', json_encode($log->payload));
    }

    #[Test]
    #[TestDox('失败请求记录状态码与错误消息')]
    public function failed_request_records_status_and_error(): void
    {
        $this->actingAsAdmin()->postJson('/admin/roles', [])->assertUnprocessable();

        $log = AuditLog::query()->where('route', 'admin.roles.store')->first();

        $this->assertNotNull($log);
        $this->assertSame(422, $log->status_code);
        $this->assertTrue($log->isFailed());
        $this->assertNotNull($log->error);
    }

    #[Test]
    #[TestDox('排除名单中的路由不产生审计记录')]
    public function excluded_route_is_not_recorded(): void
    {
        config()->set('audit.except', ['admin.roles.*']);

        $this->actingAsAdmin()->postJson('/admin/roles', [
            'name' => 'viewer',
            'display_name' => '查看者',
        ])->assertSuccessful();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[Test]
    #[TestDox('审计功能关闭时不产生记录')]
    public function disabled_audit_records_nothing(): void
    {
        config()->set('audit.enabled', false);

        $user = User::factory()->create();

        $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/toggle-status");

        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[Test]
    #[TestDox('未认证的写请求仍记录但操作人为空')]
    public function unauthenticated_write_request_records_null_operator(): void
    {
        $this->postJson('/admin/roles', [])->assertUnauthorized();

        $log = AuditLog::query()->where('route', 'admin.roles.store')->first();

        $this->assertNotNull($log);
        $this->assertNull($log->operator_id);
        $this->assertSame(401, $log->status_code);
    }
}
