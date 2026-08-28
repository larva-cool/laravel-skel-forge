<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Segment;

use App\Enums\SegmentType;
use App\Models\Admin\Admin;
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
 * UserSegmentController 端到端测试
 *
 * 覆盖 14 个端点的核心路径 + 鉴权。
 */
#[CoversClass(\App\Http\Controllers\Admin\UserSegmentController::class)]
#[Group('admin')]
#[Group('segment')]
class UserSegmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::query()->where('username', 'admin')->firstOrFail();
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function service(): \App\Services\Segment\SegmentService
    {
        return new \App\Services\Segment\SegmentService(new \App\Services\Segment\SegmentRuleEvaluator);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => null,
            'name' => '测试分群',
            'label_type' => 'primary',
            'type' => SegmentType::MANUAL->value,
            'group' => '运营分群',
            'description' => '描述',
            'sort' => 10,
            'status' => true,
        ], $overrides);
    }

    // ============ Auth ============

    #[Test]
    #[TestDox('未登录访问分群列表返回 401')]
    public function guest_cannot_list_segments(): void
    {
        $this->getJson('/admin/user-segments')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('未登录 POST /user-segments 返回 401')]
    public function guest_cannot_create_segment(): void
    {
        $this->postJson('/admin/user-segments', $this->validPayload())->assertUnauthorized();
    }

    // ============ index ============

    #[Test]
    #[TestDox('管理员可获取分群列表分页数据')]
    public function admin_can_list_segments(): void
    {
        Segment::factory()->manual()->create();
        Segment::factory()->rule()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/user-segments');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    #[TestDox('按 type 筛选分群')]
    public function admin_can_filter_by_type(): void
    {
        Segment::factory()->manual()->create(['name' => '手动A']);
        Segment::factory()->manual()->create(['name' => '手动B']);
        Segment::factory()->rule()->create(['name' => '规则A']);

        $r = $this->actingAsAdmin()->getJson('/admin/user-segments?type=1');
        $r->assertOk()->assertJsonPath('meta.total', 2);

        $r = $this->actingAsAdmin()->getJson('/admin/user-segments?type=2');
        $r->assertOk()->assertJsonPath('meta.total', 1);
    }

    // ============ store / show / update / destroy ============

    #[Test]
    #[TestDox('创建手动分群返回 201 + 数据')]
    public function admin_can_create_manual_segment(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/user-segments', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('name', '测试分群')
            ->assertJsonPath('type', 1)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('user_segments', ['name' => '测试分群']);
    }

    #[Test]
    #[TestDox('创建规则分群 code 必填，否则 422')]
    public function rule_segment_requires_code(): void
    {
        $payload = $this->validPayload([
            'type' => SegmentType::RULE->value,
            'rule' => ['version' => 1, 'logic' => 'and', 'conditions' => []],
        ]);

        $this->actingAsAdmin()
            ->postJson('/admin/user-segments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    #[Test]
    #[TestDox('创建规则分群 + 合法 rule 通过')]
    public function rule_segment_with_valid_rule_passes(): void
    {
        $payload = $this->validPayload([
            'type' => SegmentType::RULE->value,
            'code' => 'vip_users',
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'available_points', 'op' => '>=', 'value' => 100],
                ],
            ],
        ]);

        $response = $this->actingAsAdmin()->postJson('/admin/user-segments', $payload);
        $response->assertCreated()
            ->assertJsonPath('code', 'vip_users')
            ->assertJsonPath('type', 2);
    }

    #[Test]
    #[TestDox('创建分群时 rule 字段非法操作符返回 422')]
    public function rule_with_invalid_operator_fails(): void
    {
        $payload = $this->validPayload([
            'type' => SegmentType::RULE->value,
            'code' => 'bad_rule',
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'available_points', 'op' => '~~', 'value' => 1],
                ],
            ],
        ]);

        $this->actingAsAdmin()
            ->postJson('/admin/user-segments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rule']);
    }

    #[Test]
    #[TestDox('获取分群详情返回 users_count')]
    public function admin_can_show_segment_with_users_count(): void
    {
        $seg = Segment::factory()->manual()->create();
        $u = User::factory()->create();
        $this->service()->attachUsers($seg, [$u->id]);

        $response = $this->actingAsAdmin()->getJson("/admin/user-segments/{$seg->id}");

        $response->assertOk()
            ->assertJsonPath('id', $seg->id)
            ->assertJsonPath('users_count', 1);
    }

    #[Test]
    #[TestDox('更新手动分群：name / status 可改，code / rule 不受影响')]
    public function admin_can_update_manual_segment(): void
    {
        $seg = Segment::factory()->manual()->create([
            'code' => null,
            'name' => '原名',
            'status' => true,
        ]);

        $r = $this->actingAsAdmin()->putJson("/admin/user-segments/{$seg->id}", [
            'name' => '新名',
            'type' => SegmentType::MANUAL->value,
            'status' => false,
        ]);

        $r->assertOk()
            ->assertJsonPath('name', '新名')
            ->assertJsonPath('status', false);

        $seg->refresh();
        $this->assertSame('新名', $seg->name);
        $this->assertNull($seg->code);
    }

    #[Test]
    #[TestDox('更新规则分群：code / rule 被服务端忽略，保留原值')]
    public function rule_segment_update_preserves_code_and_rule(): void
    {
        $originalRule = [
            'version' => 1,
            'logic' => 'and',
            'conditions' => [['field' => 'available_points', 'op' => '>=', 'value' => 100]],
        ];
        $seg = Segment::factory()->rule()->create([
            'code' => 'original_code',
            'rule' => $originalRule,
        ]);

        $r = $this->actingAsAdmin()->putJson("/admin/user-segments/{$seg->id}", [
            'name' => '改名',
            'type' => SegmentType::RULE->value,
            'code' => 'changed_code',
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [['field' => 'status', 'op' => '=', 'value' => 1]],
            ],
        ]);

        $r->assertOk()->assertJsonPath('name', '改名');

        $seg->refresh();
        $this->assertSame('original_code', $seg->code, '规则分群 code 禁止修改');
        $this->assertSame($originalRule, $seg->rule, '规则分群 rule 禁止修改');
    }

    #[Test]
    #[TestDox('删除分群返回 204 且级联清理 memberships')]
    public function admin_can_delete_segment(): void
    {
        $seg = Segment::factory()->manual()->create();
        $u = User::factory()->create();
        $this->service()->attachUsers($seg, [$u->id]);
        $this->assertSame(1, Membership::where('segment_id', $seg->id)->count());

        $this->actingAsAdmin()->deleteJson("/admin/user-segments/{$seg->id}")->assertNoContent();

        $this->assertDatabaseMissing('user_segments', ['id' => $seg->id]);
        $this->assertSame(0, Membership::where('segment_id', $seg->id)->count());
    }

    // ============ toggleStatus / sync / preview / options ============

    #[Test]
    #[TestDox('切换分群启用状态')]
    public function admin_can_toggle_segment_status(): void
    {
        $seg = Segment::factory()->manual()->create(['status' => true]);

        $r = $this->actingAsAdmin()->putJson("/admin/user-segments/{$seg->id}/toggle-status");

        $r->assertOk()->assertJsonPath('status', false);

        $r2 = $this->actingAsAdmin()->putJson("/admin/user-segments/{$seg->id}/toggle-status");
        $r2->assertOk()->assertJsonPath('status', true);
    }

    #[Test]
    #[TestDox('手动同步规则分群：返回 added/removed/total')]
    public function admin_can_sync_rule_segment(): void
    {
        $seg = Segment::factory()->rule()->create([
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [['field' => 'available_points', 'op' => '>=', 'value' => 100]],
            ],
        ]);
        $u1 = User::factory()->create(['available_points' => 200]);
        $u2 = User::factory()->create(['available_points' => 50]);

        $r = $this->actingAsAdmin()->postJson("/admin/user-segments/{$seg->id}/sync");

        $r->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('added', 1)
            ->assertJsonPath('removed', 0);

        $this->assertSame(1, Membership::where('segment_id', $seg->id)
            ->where('source', Membership::SOURCE_RULE)->count());
    }

    #[Test]
    #[TestDox('同步手动分群返回 422（仅规则分群允许）')]
    public function sync_rejects_manual_segment(): void
    {
        $seg = Segment::factory()->manual()->create();
        $this->actingAsAdmin()
            ->postJson("/admin/user-segments/{$seg->id}/sync")
            ->assertStatus(422);
    }

    #[Test]
    #[TestDox('预览规则分群：返回 total 与 sample 用户列表')]
    public function admin_can_preview_rule_segment(): void
    {
        $seg = Segment::factory()->rule()->create([
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [['field' => 'available_points', 'op' => '>=', 'value' => 100]],
            ],
        ]);
        User::factory()->count(3)->create(['available_points' => 200]);

        $r = $this->actingAsAdmin()->getJson("/admin/user-segments/{$seg->id}/preview");

        $r->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonCount(3, 'sample');
    }

    #[Test]
    #[TestDox('options 仅返回启用 + 手动分群')]
    public function options_returns_active_manual_segments(): void
    {
        Segment::factory()->manual()->create(['name' => 'M1', 'status' => true]);
        Segment::factory()->manual()->create(['name' => 'M2', 'status' => false]);
        Segment::factory()->rule()->create(['name' => 'R1', 'status' => true]);

        $r = $this->actingAsAdmin()->getJson('/admin/user-segments/options');

        $r->assertOk()->assertJsonCount(1);
        $r->assertJsonPath('0.name', 'M1');
    }

    // ============ users ============

    #[Test]
    #[TestDox('获取分群下用户列表')]
    public function admin_can_list_segment_users(): void
    {
        $seg = Segment::factory()->manual()->create();
        $u1 = User::factory()->create(['username' => 'alice']);
        $u2 = User::factory()->create(['username' => 'bob']);
        $this->service()->attachUsers($seg, [$u1->id]);

        $r = $this->actingAsAdmin()->getJson("/admin/user-segments/{$seg->id}/users");

        $r->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $u1->id);
    }

    // ============ attach / detach ============

    #[Test]
    #[TestDox('单分群 attach 用户：返回 added / requested')]
    public function admin_can_attach_users(): void
    {
        $seg = Segment::factory()->manual()->create();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $r = $this->actingAsAdmin()->postJson("/admin/user-segments/{$seg->id}/attach", [
            'user_ids' => [$u1->id, $u2->id],
        ]);

        $r->assertOk()
            ->assertJsonPath('added', 2)
            ->assertJsonPath('requested', 2);

        $this->assertSame(2, Membership::where('segment_id', $seg->id)->count());
    }

    #[Test]
    #[TestDox('单分群 detach 用户：返回 removed / requested')]
    public function admin_can_detach_users(): void
    {
        $seg = Segment::factory()->manual()->create();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $this->service()->attachUsers($seg, [$u1->id, $u2->id]);

        $r = $this->actingAsAdmin()->postJson("/admin/user-segments/{$seg->id}/detach", [
            'user_ids' => [$u1->id],
        ]);

        $r->assertOk()
            ->assertJsonPath('removed', 1)
            ->assertJsonPath('requested', 1);

        $this->assertSame(1, Membership::where('segment_id', $seg->id)->count());
    }

    #[Test]
    #[TestDox('attach user_ids 为空返回 422')]
    public function attach_requires_user_ids(): void
    {
        $seg = Segment::factory()->manual()->create();
        $this->actingAsAdmin()
            ->postJson("/admin/user-segments/{$seg->id}/attach", ['user_ids' => []])
            ->assertStatus(422);
    }

    // ============ batchAttach / batchDetach ============

    #[Test]
    #[TestDox('batchAttach 笛卡尔打标')]
    public function admin_can_batch_attach(): void
    {
        $s1 = Segment::factory()->manual()->create();
        $s2 = Segment::factory()->manual()->create();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $r = $this->actingAsAdmin()->postJson('/admin/user-segments/batch-attach', [
            'segment_ids' => [$s1->id, $s2->id],
            'user_ids' => [$u1->id, $u2->id],
        ]);

        $r->assertOk()
            ->assertJsonPath('added', 4)
            ->assertJsonPath('requested', 4);
    }

    #[Test]
    #[TestDox('batchDetach 笛卡尔解标')]
    public function admin_can_batch_detach(): void
    {
        $s1 = Segment::factory()->manual()->create();
        $s2 = Segment::factory()->manual()->create();
        $u1 = User::factory()->create();
        $this->service()->batchAttach([$s1->id, $s2->id], [$u1->id]);

        $r = $this->actingAsAdmin()->postJson('/admin/user-segments/batch-detach', [
            'segment_ids' => [$s1->id, $s2->id],
            'user_ids' => [$u1->id],
        ]);

        $r->assertOk()
            ->assertJsonPath('removed', 2)
            ->assertJsonPath('requested', 2);
    }

    #[Test]
    #[TestDox('批量操作超限返回 422')]
    public function batch_size_limit_returns_422(): void
    {
        $seg = Segment::factory()->manual()->create();
        $ids = range(1, \App\Services\Segment\SegmentService::BATCH_LIMIT + 1);

        $this->actingAsAdmin()
            ->postJson("/admin/user-segments/{$seg->id}/attach", ['user_ids' => $ids])
            ->assertStatus(422);
    }
}
