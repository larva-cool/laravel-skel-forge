<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Automation;

use App\Enums\AutomationRewardType;
use App\Enums\AutomationTriggerType;
use App\Http\Controllers\Admin\AutomationRuleController;
use App\Models\Admin\Admin;
use App\Models\Automation\AutomationFestival;
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
 * 后台自动化运营规则管理控制器功能测试
 */
#[CoversClass(AutomationRuleController::class)]
#[Group('admin')]
#[Group('automation')]
class AutomationRuleControllerTest extends TestCase
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
     * 默认有效 payload
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => '生日祝福',
            'trigger_type' => AutomationTriggerType::BIRTHDAY->value,
            'trigger_config' => ['advance_days' => 0, 'send_time' => '09:00'],
            'segment_id' => null,
            'title' => '生日快乐，{nickname}！',
            'content' => '亲爱的 {nickname}，祝你生日快乐！',
            'channels' => ['database'],
            'reward_type' => AutomationRewardType::NONE->value,
            'reward_amount' => 0,
        ], $overrides);
    }

    #[Test]
    #[TestDox('未登录访问规则列表返回 401')]
    public function guest_cannot_list_rules(): void
    {
        $this->getJson('/admin/automation-rules')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可获取规则列表分页数据')]
    public function admin_can_list_rules(): void
    {
        AutomationRule::factory()->count(3)->create();

        $response = $this->actingAsAdmin()->getJson('/admin/automation-rules');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'trigger_type', 'trigger_type_label', 'channels', 'status']]]);
    }

    #[Test]
    #[TestDox('规则列表支持触发类型筛选')]
    public function list_can_filter_by_trigger_type(): void
    {
        AutomationRule::factory()->create();
        AutomationRule::factory()->festival()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/automation-rules?trigger_type='.AutomationTriggerType::FESTIVAL->value);

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    #[TestDox('创建规则缺少必填字段时返回 422')]
    public function create_rule_requires_fields(): void
    {
        $this->actingAsAdmin()->postJson('/admin/automation-rules', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'trigger_type', 'trigger_config', 'title', 'content', 'channels']);
    }

    #[Test]
    #[TestDox('创建规则：send_time 晚于 23:00 被拒绝')]
    public function create_rule_rejects_send_time_after_2310_window(): void
    {
        $this->actingAsAdmin()->postJson('/admin/automation-rules', $this->validPayload([
            'trigger_config' => ['advance_days' => 0, 'send_time' => '23:59'],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['trigger_config.send_time']);
    }

    #[Test]
    #[TestDox('创建规则：奖励类型为积分时奖励数量必须大于 0')]
    public function create_rule_requires_positive_reward_amount_when_rewarded(): void
    {
        $this->actingAsAdmin()->postJson('/admin/automation-rules', $this->validPayload([
            'reward_type' => AutomationRewardType::POINT->value,
            'reward_amount' => 0,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['reward_amount']);
    }

    #[Test]
    #[TestDox('管理员可创建规则')]
    public function admin_can_create_rule(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/automation-rules', $this->validPayload([
            'reward_type' => AutomationRewardType::POINT->value,
            'reward_amount' => 100,
        ]));

        $response->assertCreated();
        $this->assertDatabaseHas('automation_rules', [
            'name' => '生日祝福',
            'trigger_type' => AutomationTriggerType::BIRTHDAY->value,
            'reward_amount' => 100,
            'admin_id' => $this->admin->id,
        ]);
    }

    #[Test]
    #[TestDox('管理员可查看规则详情')]
    public function admin_can_show_rule(): void
    {
        $rule = AutomationRule::factory()->create();

        $this->actingAsAdmin()->getJson("/admin/automation-rules/{$rule->id}")
            ->assertOk()
            ->assertJsonPath('id', $rule->id);
    }

    #[Test]
    #[TestDox('管理员可更新规则')]
    public function admin_can_update_rule(): void
    {
        $rule = AutomationRule::factory()->create();

        $this->actingAsAdmin()->putJson("/admin/automation-rules/{$rule->id}", $this->validPayload([
            'name' => '生日提醒（更新）',
        ]))->assertOk();

        $this->assertDatabaseHas('automation_rules', ['id' => $rule->id, 'name' => '生日提醒（更新）']);
    }

    #[Test]
    #[TestDox('切换规则状态')]
    public function admin_can_toggle_status(): void
    {
        $rule = AutomationRule::factory()->create();

        $response = $this->actingAsAdmin()->putJson("/admin/automation-rules/{$rule->id}/toggle-status");

        $response->assertOk();
        $this->assertFalse($rule->fresh()->status);
    }

    #[Test]
    #[TestDox('删除规则')]
    public function admin_can_delete_rule(): void
    {
        $rule = AutomationRule::factory()->create();

        $this->actingAsAdmin()->deleteJson("/admin/automation-rules/{$rule->id}")->assertNoContent();

        $this->assertSoftDeleted('automation_rules', ['id' => $rule->id]);
    }

    #[Test]
    #[TestDox('手动触发：立即执行规则并返回执行摘要')]
    public function admin_can_trigger_rule_manually(): void
    {
        Notification::fake();

        $user = User::factory()->create(['status' => 1]);
        $user->profile()->update(['birthday' => '1995-06-15']);

        $rule = AutomationRule::factory()->create([
            'trigger_config' => ['advance_days' => 0, 'send_time' => '23:00'],
        ]);

        $response = $this->actingAsAdmin()->postJson("/admin/automation-rules/{$rule->id}/trigger", [
            'date' => '2026-06-15',
        ]);

        $response->assertOk()->assertJsonPath('summary.sent', 1);
    }

    #[Test]
    #[TestDox('手动触发：非法日期返回 422')]
    public function trigger_rejects_invalid_date(): void
    {
        $rule = AutomationRule::factory()->create();

        $this->actingAsAdmin()->postJson("/admin/automation-rules/{$rule->id}/trigger", [
            'date' => 'not-a-date',
        ])->assertUnprocessable();
    }

    #[Test]
    #[TestDox('禁用的规则不能手动触发')]
    public function disabled_rule_cannot_be_triggered(): void
    {
        $rule = AutomationRule::factory()->disabled()->create();

        $this->actingAsAdmin()->postJson("/admin/automation-rules/{$rule->id}/trigger")->assertNotFound();
    }

    #[Test]
    #[TestDox('元数据接口返回触发类型/奖励类型/渠道选项')]
    public function meta_returns_options(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/automation-rules/meta');

        $response->assertOk()
            ->assertJsonStructure(['trigger_types', 'reward_types', 'channels'])
            ->assertJsonCount(3, 'trigger_types')
            ->assertJsonCount(3, 'reward_types')
            ->assertJsonCount(2, 'channels');
    }

    #[Test]
    #[TestDox('节日规则可引用存在的节日 ID')]
    public function festival_rule_accepts_existing_festival_ids(): void
    {
        $festival = AutomationFestival::factory()->create();

        $this->actingAsAdmin()->postJson('/admin/automation-rules', $this->validPayload([
            'trigger_type' => AutomationTriggerType::FESTIVAL->value,
            'trigger_config' => ['advance_days' => 0, 'send_time' => '09:00', 'festival_ids' => [$festival->id]],
        ]))->assertCreated();
    }

    #[Test]
    #[TestDox('节日规则引用不存在的节日 ID 返回 422')]
    public function festival_rule_rejects_nonexistent_festival_ids(): void
    {
        $this->actingAsAdmin()->postJson('/admin/automation-rules', $this->validPayload([
            'trigger_type' => AutomationTriggerType::FESTIVAL->value,
            'trigger_config' => ['advance_days' => 0, 'send_time' => '09:00', 'festival_ids' => [99999]],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['trigger_config.festival_ids.0']);
    }
}
