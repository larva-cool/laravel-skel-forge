<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Automation;

use App\Enums\FestivalDateType;
use App\Http\Controllers\Admin\AutomationFestivalController;
use App\Models\Admin\Admin;
use App\Models\Automation\AutomationFestival;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台自动化运营节日管理控制器功能测试
 */
#[CoversClass(AutomationFestivalController::class)]
#[Group('admin')]
#[Group('automation')]
class AutomationFestivalControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // 清除种子迁移预置的节日，保证测试数据隔离
        AutomationFestival::query()->delete();

        // seed_initial_admin_data 迁移已预置超级管理员
        $this->admin = Admin::query()->where('username', 'admin')->firstOrFail();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 默认有效 payload
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => '七夕节',
            'date_type' => FestivalDateType::LUNAR->value,
            'month' => 7,
            'day' => 7,
            'status' => true,
            'sort' => 100,
        ], $overrides);
    }

    #[Test]
    #[TestDox('未登录访问节日列表返回 401')]
    public function guest_cannot_list_festivals(): void
    {
        $this->getJson('/admin/automation-festivals')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可获取节日列表分页数据')]
    public function admin_can_list_festivals(): void
    {
        AutomationFestival::factory()->count(3)->create();

        $response = $this->actingAsAdmin()->getJson('/admin/automation-festivals');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'date_type', 'date_type_label', 'month', 'day', 'status']]]);
    }

    #[Test]
    #[TestDox('节日列表支持日期类型与关键词筛选')]
    public function list_supports_filters(): void
    {
        AutomationFestival::factory()->create(['name' => '国庆节', 'date_type' => FestivalDateType::SOLAR]);
        AutomationFestival::factory()->lunar()->create(['name' => '中秋节']);

        $this->actingAsAdmin()->getJson('/admin/automation-festivals?date_type='.FestivalDateType::LUNAR->value)
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAsAdmin()->getJson('/admin/automation-festivals?keyword='.rawurlencode('国庆'))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    #[TestDox('创建节日缺少必填字段时返回 422')]
    public function create_festival_requires_fields(): void
    {
        $this->actingAsAdmin()->postJson('/admin/automation-festivals', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'date_type', 'month', 'day']);
    }

    #[Test]
    #[TestDox('农历节日日期最大 30 天')]
    public function lunar_festival_day_max_30(): void
    {
        $this->actingAsAdmin()->postJson('/admin/automation-festivals', $this->validPayload(['day' => 31]))
            ->assertUnprocessable()->assertJsonValidationErrors(['day']);
    }

    #[Test]
    #[TestDox('管理员可创建节日')]
    public function admin_can_create_festival(): void
    {
        $this->actingAsAdmin()->postJson('/admin/automation-festivals', $this->validPayload())->assertCreated();

        $this->assertDatabaseHas('automation_festivals', [
            'name' => '七夕节',
            'date_type' => FestivalDateType::LUNAR->value,
            'month' => 7,
            'day' => 7,
        ]);
    }

    #[Test]
    #[TestDox('管理员可更新节日')]
    public function admin_can_update_festival(): void
    {
        $festival = AutomationFestival::factory()->create();

        $this->actingAsAdmin()->putJson("/admin/automation-festivals/{$festival->id}", $this->validPayload([
            'name' => '七夕（更新）',
        ]))->assertOk();

        $this->assertDatabaseHas('automation_festivals', ['id' => $festival->id, 'name' => '七夕（更新）']);
    }

    #[Test]
    #[TestDox('切换节日状态')]
    public function admin_can_toggle_status(): void
    {
        $festival = AutomationFestival::factory()->create();

        $this->actingAsAdmin()->putJson("/admin/automation-festivals/{$festival->id}/toggle-status")->assertOk();

        $this->assertFalse($festival->fresh()->status);
    }

    #[Test]
    #[TestDox('删除节日')]
    public function admin_can_delete_festival(): void
    {
        $festival = AutomationFestival::factory()->create();

        $this->actingAsAdmin()->deleteJson("/admin/automation-festivals/{$festival->id}")->assertNoContent();

        $this->assertSoftDeleted('automation_festivals', ['id' => $festival->id]);
    }

    #[Test]
    #[TestDox('节日选项仅返回启用节日')]
    public function options_only_returns_active_festivals(): void
    {
        AutomationFestival::factory()->create();
        AutomationFestival::factory()->disabled()->create();

        $this->actingAsAdmin()->getJson('/admin/automation-festivals/options')
            ->assertOk()->assertJsonCount(1);
    }
}
