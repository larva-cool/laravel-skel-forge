<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Content;

use App\Ai\Tools\Content\HandleReport;
use App\Enums\AdminStatus;
use App\Enums\ReportStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\Content\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 举报处理工具测试
 */
#[CoversClass(HandleReport::class)]
#[Group('ai')]
#[Group('report')]
class HandleReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 创建一个仅拥有指定权限的管理员
     *
     * @param  array<int, string>  $permissions
     */
    private function createAdminWithPermissions(array $permissions): Admin
    {
        $suffix = uniqid();

        $admin = Admin::query()->create([
            'username' => 'limited_'.$suffix,
            'name' => '受限管理员',
            'email' => "limited_{$suffix}@example.com",
            'password' => 'password',
            'status' => AdminStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'limited_'.$suffix,
            'display_name' => '受限角色',
            'guard_name' => AdminMenu::GUARD_NAME,
        ]);

        $role->syncPermissions($permissions);
        $admin->assignRole($role);

        return $admin->refresh();
    }

    /**
     * 创建一条举报
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createReport(array $overrides = []): Report
    {
        return Report::query()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'reportable_id' => 1,
            'reportable_type' => 'comment',
            'category' => 'spam',
            'reason' => '垃圾广告信息',
        ], $overrides));
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new HandleReport)->handle(new Request(['id' => 1, 'status' => 'resolved']));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 reports.edit 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['reports.index']);
        $report = $this->createReport();

        $result = (new HandleReport)->forAdmin($admin)->handle(new Request([
            'id' => $report->id,
            'status' => 'resolved',
        ]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertSame(ReportStatus::PENDING, $report->fresh()->status);
    }

    #[Test]
    #[TestDox('处理成立后记录处理人、备注与处理时间')]
    public function handle_resolves_report(): void
    {
        $admin = $this->createAdminWithPermissions(['reports.edit']);
        $report = $this->createReport();

        $result = (new HandleReport)->forAdmin($admin)->handle(new Request([
            'id' => $report->id,
            'status' => 'resolved',
            'remark' => '已核实为垃圾信息',
        ]));

        $fresh = $report->fresh();
        $this->assertSame(ReportStatus::RESOLVED, $fresh->status);
        $this->assertSame($admin->id, $fresh->handler_id);
        $this->assertSame('已核实为垃圾信息', $fresh->process_remark);
        $this->assertNotNull($fresh->processed_at);
        $this->assertStringContainsString('已处理', (string) $result);
    }

    #[Test]
    #[TestDox('驳回举报后不执行任何处置动作')]
    public function handle_rejects_report_without_actions(): void
    {
        $admin = $this->createAdminWithPermissions(['reports.edit']);
        $report = $this->createReport();

        $result = (new HandleReport)->forAdmin($admin)->handle(new Request([
            'id' => $report->id,
            'status' => 'rejected',
            'actions' => ['delete_comment'],
        ]));

        $fresh = $report->fresh();
        $this->assertSame(ReportStatus::REJECTED, $fresh->status);
        $this->assertSame([], $fresh->process_actions ?? []);
        $this->assertStringContainsString('已驳回', (string) $result);
    }

    #[Test]
    #[TestDox('已结案的举报不可重复处理')]
    public function handle_cancels_when_report_closed(): void
    {
        $admin = $this->createAdminWithPermissions(['reports.edit']);
        $report = $this->createReport(['status' => ReportStatus::RESOLVED]);

        $result = (new HandleReport)->forAdmin($admin)->handle(new Request([
            'id' => $report->id,
            'status' => 'rejected',
        ]));

        $this->assertStringContainsString('已结案', (string) $result);
        $this->assertSame(ReportStatus::RESOLVED, $report->fresh()->status);
    }
}
