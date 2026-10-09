<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ScheduleTaskStatus;
use App\Http\Controllers\Admin\ScheduleController;
use App\Models\Admin\Admin;
use Database\Factories\System\ScheduleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台定时任务管理功能测试
 */
#[CoversClass(ScheduleController::class)]
#[Group('admin')]
#[Group('schedule')]
class ScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    #[TestDox('未登录访问定时任务列表返回 401')]
    public function guest_cannot_list_schedules(): void
    {
        $this->getJson('/admin/schedules')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('定时任务列表分页返回')]
    public function admin_can_list_schedules(): void
    {
        ScheduleFactory::new()->count(3)->create();

        $this->actingAsAdmin()
            ->getJson('/admin/schedules')
            ->assertOk()
            ->assertJsonPath('meta.total', 11)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'command', 'expression', 'status', 'status_text', 'command_string']],
            ]);
    }

    #[Test]
    #[TestDox('列表按名称筛选')]
    public function admin_can_filter_by_name(): void
    {
        $schedule = ScheduleFactory::new()->create(['name' => 'Daily Stats']);
        ScheduleFactory::new()->create(['name' => 'Cache Cleanup']);

        $this->actingAsAdmin()
            ->getJson('/admin/schedules?name=Daily')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Daily Stats');
    }

    #[Test]
    #[TestDox('列表按状态筛选')]
    public function admin_can_filter_by_status(): void
    {
        ScheduleFactory::new()->count(2)->create(['status' => ScheduleTaskStatus::ACTIVE]);
        ScheduleFactory::new()->create(['status' => ScheduleTaskStatus::INACTIVE]);

        $this->actingAsAdmin()
            ->getJson('/admin/schedules?status=0')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('创建定时任务')]
    public function admin_can_create_schedule(): void
    {
        $this->actingAsAdmin()
            ->postJson('/admin/schedules', [
                'name' => '每日统计',
                'command' => 'stats:user',
                'expression' => '0 1 * * *',
                'description' => '每天凌晨1点统计用户数据',
                'on_one_server' => true,
                'status' => ScheduleTaskStatus::ACTIVE->value,
            ])
            ->assertStatus(201)
            ->assertJsonPath('name', '每日统计')
            ->assertJsonPath('command', 'stats:user')
            ->assertJsonPath('expression', '0 1 * * *');

        $this->assertDatabaseHas('schedules', ['name' => '每日统计']);
    }

    #[Test]
    #[TestDox('创建自定义 Shell 命令任务')]
    public function admin_can_create_custom_command_schedule(): void
    {
        $this->actingAsAdmin()
            ->postJson('/admin/schedules', [
                'name' => '备份脚本',
                'command' => 'custom',
                'command_custom' => 'echo "hello" > /tmp/backup.log',
                'expression' => '0 2 * * *',
                'status' => ScheduleTaskStatus::ACTIVE->value,
            ])
            ->assertStatus(201)
            ->assertJsonPath('command', 'custom')
            ->assertJsonPath('command_custom', 'echo "hello" > /tmp/backup.log');
    }

    #[Test]
    #[TestDox('创建任务缺少名称返回 422')]
    public function create_without_name_returns_422(): void
    {
        $this->actingAsAdmin()
            ->postJson('/admin/schedules', [
                'command' => 'stats:user',
                'expression' => '0 1 * * *',
                'status' => ScheduleTaskStatus::ACTIVE->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    #[TestDox('更新定时任务')]
    public function admin_can_update_schedule(): void
    {
        $schedule = ScheduleFactory::new()->create(['name' => '原名称']);

        $this->actingAsAdmin()
            ->putJson("/admin/schedules/{$schedule->id}", [
                'name' => '新名称',
                'command' => 'stats:user',
                'expression' => '*/10 * * * *',
                'status' => ScheduleTaskStatus::ACTIVE->value,
            ])
            ->assertOk()
            ->assertJsonPath('name', '新名称')
            ->assertJsonPath('expression', '*/10 * * * *');
    }

    #[Test]
    #[TestDox('删除定时任务（软删除）')]
    public function admin_can_delete_schedule(): void
    {
        $schedule = ScheduleFactory::new()->create();

        $this->actingAsAdmin()
            ->deleteJson("/admin/schedules/{$schedule->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('schedules', ['id' => $schedule->id]);
    }

    #[Test]
    #[TestDox('切换启用/禁用状态')]
    public function admin_can_toggle_schedule_status(): void
    {
        $schedule = ScheduleFactory::new()->create(['status' => ScheduleTaskStatus::ACTIVE]);

        $this->actingAsAdmin()
            ->patchJson("/admin/schedules/{$schedule->id}/toggle")
            ->assertOk()
            ->assertJsonPath('status', ScheduleTaskStatus::INACTIVE->value);

        $this->actingAsAdmin()
            ->patchJson("/admin/schedules/{$schedule->id}/toggle")
            ->assertOk()
            ->assertJsonPath('status', ScheduleTaskStatus::ACTIVE->value);
    }

    #[Test]
    #[TestDox('手动执行 Artisan 命令任务')]
    public function admin_can_execute_artisan_command(): void
    {
        $schedule = ScheduleFactory::new()->create([
            'command' => 'list',
            'params' => [],
            'options' => [],
            'options_with_value' => [],
        ]);

        $this->actingAsAdmin()
            ->postJson("/admin/schedules/{$schedule->id}/execute")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('exit_code', 0)
            ->assertJsonStructure(['exit_code', 'output', 'success']);
    }

    #[Test]
    #[TestDox('获取可用命令列表')]
    public function admin_can_get_available_commands(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/schedules/commands')
            ->assertOk()
            ->assertJsonStructure([['name', 'description']]);
    }

    #[Test]
    #[TestDox('详情返回任务完整信息')]
    public function admin_can_view_schedule_detail(): void
    {
        $schedule = ScheduleFactory::new()->create([
            'name' => '测试任务',
            'command' => 'stats:user',
            'expression' => '0 1 * * *',
        ]);

        $this->actingAsAdmin()
            ->getJson("/admin/schedules/{$schedule->id}")
            ->assertOk()
            ->assertJsonPath('id', $schedule->id)
            ->assertJsonPath('name', '测试任务')
            ->assertJsonPath('command', 'stats:user');
    }

    #[Test]
    #[TestDox('带参数和选项的命令字符串正确生成')]
    public function command_string_includes_params_and_options(): void
    {
        $schedule = ScheduleFactory::new()->create([
            'command' => 'telescope:prune',
            'params' => [],
            'options' => [],
            'options_with_value' => [['name' => 'hours', 'value' => '48']],
        ]);

        $this->actingAsAdmin()
            ->getJson("/admin/schedules/{$schedule->id}")
            ->assertOk()
            ->assertJsonPath('command_string', 'artisan telescope:prune --hours=48');
    }
}
