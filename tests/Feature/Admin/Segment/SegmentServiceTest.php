<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Segment;

use App\Enums\IdentityType;
use App\Models\User;
use App\Models\UserSegment\Membership;
use App\Models\UserSegment\Segment;
use App\Services\Segment\SegmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * SegmentService 单元测试
 */
#[CoversClass(SegmentService::class)]
#[Group('admin')]
#[Group('segment')]
class SegmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private SegmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SegmentService(new \App\Services\Segment\SegmentRuleEvaluator);
    }

    private function makeUser(int $points = 0): User
    {
        return User::factory()->create([
            'status' => 1,
            'available_points' => $points,
        ]);
    }

    #[Test]
    #[TestDox('attachUsers 幂等：重复 attach 不会产生重复行')]
    public function attach_is_idempotent(): void
    {
        $seg = Segment::factory()->manual()->create();
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();

        $first = $this->service->attachUsers($seg, [$u1->id, $u2->id]);
        $second = $this->service->attachUsers($seg, [$u1->id, $u2->id]);

        $this->assertSame(2, $first);
        $this->assertSame(0, $second);
        $this->assertSame(2, Membership::where('segment_id', $seg->id)->count());
    }

    #[Test]
    #[TestDox('attachUsers 过滤不存在的 user_id')]
    public function attach_filters_nonexistent_users(): void
    {
        $seg = Segment::factory()->manual()->create();
        $u1 = $this->makeUser();

        $added = $this->service->attachUsers($seg, [$u1->id, 99999]);
        $this->assertSame(1, $added);
        $this->assertSame(1, Membership::where('segment_id', $seg->id)->count());
    }

    #[Test]
    #[TestDox('detachUsers 仅删除指定 user_id 的成员')]
    public function detach_removes_only_specified(): void
    {
        $seg = Segment::factory()->manual()->create();
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        $u3 = $this->makeUser();

        $this->service->attachUsers($seg, [$u1->id, $u2->id, $u3->id]);
        $deleted = $this->service->detachUsers($seg, [$u1->id, $u3->id]);

        $this->assertSame(2, $deleted);
        $this->assertSame(1, Membership::where('segment_id', $seg->id)->count());
        $this->assertDatabaseHas('user_segment_memberships', ['segment_id' => $seg->id, 'user_id' => $u2->id]);
    }

    #[Test]
    #[TestDox('batchAttach 笛卡尔：2 分群 × 3 用户 = 6 行')]
    public function batch_attach_cartesian(): void
    {
        $s1 = Segment::factory()->manual()->create();
        $s2 = Segment::factory()->manual()->create();
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        $u3 = $this->makeUser();

        $added = $this->service->batchAttach([$s1->id, $s2->id], [$u1->id, $u2->id, $u3->id]);

        $this->assertSame(6, $added);
        $this->assertSame(6, Membership::count());
    }

    #[Test]
    #[TestDox('batchDetach 删除笛卡尔交集')]
    public function batch_detach_removes_cartesian_intersection(): void
    {
        $s1 = Segment::factory()->manual()->create();
        $s2 = Segment::factory()->manual()->create();
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();

        $this->service->batchAttach([$s1->id, $s2->id], [$u1->id, $u2->id]);
        // s1 还多绑一个 u3
        $u3 = $this->makeUser();
        $this->service->attachUsers($s1, [$u3->id]);

        $deleted = $this->service->batchDetach([$s1->id, $s2->id], [$u1->id, $u2->id]);

        // s1 删 2 条（u1,u2），s2 删 2 条（u1,u2）
        $this->assertSame(4, $deleted);
        // 仅剩 s1-u3
        $this->assertSame(1, Membership::count());
    }

    #[Test]
    #[TestDox('批量操作超过上限抛 InvalidArgumentException')]
    public function batch_size_limit_throws(): void
    {
        $seg = Segment::factory()->manual()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->attachUsers($seg, range(1, SegmentService::BATCH_LIMIT + 1));
    }

    #[Test]
    #[TestDox('syncRuleSegment 仅对 RULE 分群生效：MANUAL 抛异常')]
    public function sync_rejects_manual_segment(): void
    {
        $seg = Segment::factory()->manual()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->syncRuleSegment($seg);
    }

    #[Test]
    #[TestDox('syncRuleSegment 禁用分群抛异常')]
    public function sync_rejects_disabled_segment(): void
    {
        $seg = Segment::factory()->rule()->disabled()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->syncRuleSegment($seg);
    }

    #[Test]
    #[TestDox('syncRuleSegment 增量 diff：新增匹配、移除已不匹配、更新 last_synced_*')]
    public function sync_rule_segment_incremental_diff(): void
    {
        // 规则：status=1 且 available_points >= 100
        $seg = Segment::factory()->rule()->create([
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'status', 'op' => '=', 'value' => 1],
                    ['field' => 'available_points', 'op' => '>=', 'value' => 100],
                ],
            ],
        ]);

        $a = $this->makeUser(200); // 匹配
        $b = $this->makeUser(50);  // 不匹配
        $c = $this->makeUser(150); // 匹配

        // 第一次同步：应新增 a、c
        $result = $this->service->syncRuleSegment($seg);
        $this->assertSame(2, $result['added']);
        $this->assertSame(0, $result['removed']);
        $this->assertSame(2, $result['total']);
        $this->assertSame(2, Membership::where('segment_id', $seg->id)
            ->where('source', Membership::SOURCE_RULE)->count());
        $seg->refresh();
        $this->assertNotNull($seg->last_synced_at);
        $this->assertSame(2, $seg->last_synced_user_count);

        // 修改数据：a 调成不匹配，b 调成匹配，再同步
        $a->update(['available_points' => 10]);
        $b->update(['available_points' => 500]);

        $result = $this->service->syncRuleSegment($seg);
        $this->assertSame(1, $result['added']);   // b
        $this->assertSame(1, $result['removed']); // a
        $this->assertSame(2, $result['total']);   // b, c
        $matched = Membership::where('segment_id', $seg->id)
            ->where('source', Membership::SOURCE_RULE)
            ->pluck('user_id')->all();
        sort($matched);
        $this->assertSame([$b->id, $c->id], $matched);
    }

    #[Test]
    #[TestDox('syncRuleSegment 不影响手动打标的成员（限定 source=RULE）')]
    public function sync_does_not_touch_manual_memberships(): void
    {
        $seg = Segment::factory()->rule()->create([
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'available_points', 'op' => '>=', 'value' => 1000],
                ],
            ],
        ]);

        $manual = $this->makeUser(0); // 不匹配规则
        $this->service->attachUsers($seg, [$manual->id], Membership::SOURCE_MANUAL);

        $this->service->syncRuleSegment($seg);

        $this->assertDatabaseHas('user_segment_memberships', [
            'segment_id' => $seg->id,
            'user_id' => $manual->id,
            'source' => Membership::SOURCE_MANUAL,
        ]);
    }

    #[Test]
    #[TestDox('previewRuleSegment 不写库，仅返回 total 与 sample')]
    public function preview_does_not_persist(): void
    {
        $seg = Segment::factory()->rule()->create([
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'available_points', 'op' => '>=', 'value' => 100],
                ],
            ],
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->makeUser($i < 7 ? 200 : 0);
        }

        $preview = $this->service->previewRuleSegment($seg, 3);
        $this->assertSame(7, $preview['total']);
        $this->assertCount(3, $preview['sample']);
        $this->assertSame(0, Membership::where('segment_id', $seg->id)->count());
    }

    #[Test]
    #[TestDox('previewRuleSegment 拒绝 MANUAL 分群')]
    public function preview_rejects_manual(): void
    {
        $seg = Segment::factory()->manual()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->previewRuleSegment($seg);
    }

    #[Test]
    #[TestDox('attachUsers 接受自定义 source（规则同步也可手动指定）')]
    public function attach_supports_custom_source(): void
    {
        $seg = Segment::factory()->rule()->create();
        $u1 = $this->makeUser();
        $this->service->attachUsers($seg, [$u1->id], Membership::SOURCE_RULE);

        $this->assertDatabaseHas('user_segment_memberships', [
            'segment_id' => $seg->id,
            'user_id' => $u1->id,
            'source' => Membership::SOURCE_RULE,
        ]);
    }

    #[Test]
    #[TestDox('attachUsers 空数组返回 0 不报错')]
    public function attach_empty_returns_zero(): void
    {
        $seg = Segment::factory()->manual()->create();
        $this->assertSame(0, $this->service->attachUsers($seg, []));
        $this->assertSame(0, Membership::count());
    }

    #[Test]
    #[TestDox('syncRuleSegment 按 identity_verified=true 过滤：仅已实名用户被纳入分群')]
    public function sync_rule_segment_filters_by_identity_verified_true(): void
    {
        $seg = Segment::factory()->rule()->create([
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'identity_verified', 'op' => '=', 'value' => true],
                ],
            ],
        ]);

        $verified = $this->makeUser();
        $verified->forceFill([
            'identity_verified' => true,
            'identity_type' => IdentityType::PERSONAL,
        ])->saveQuietly();

        $unverified = $this->makeUser(); // 默认 identity_verified=false
        $result = $this->service->syncRuleSegment($seg);

        $this->assertSame(1, $result['added']);
        $this->assertSame(0, $result['removed']);
        $this->assertSame(1, $result['total']);
        $this->assertTrue(
            Membership::where('segment_id', $seg->id)
                ->where('user_id', $verified->id)
                ->exists()
        );
        $this->assertFalse(
            Membership::where('segment_id', $seg->id)
                ->where('user_id', $unverified->id)
                ->exists()
        );
    }

    #[Test]
    #[TestDox('syncRuleSegment 按 identity_verified!=true 过滤：仅未实名用户被纳入分群')]
    public function sync_rule_segment_filters_by_identity_verified_false(): void
    {
        $seg = Segment::factory()->rule()->create([
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'identity_verified', 'op' => '!=', 'value' => true],
                ],
            ],
        ]);

        $verified = $this->makeUser();
        $verified->forceFill([
            'identity_verified' => true,
            'identity_type' => IdentityType::PERSONAL,
        ])->saveQuietly();

        $unverifiedA = $this->makeUser();
        $unverifiedB = $this->makeUser();

        $result = $this->service->syncRuleSegment($seg);

        $this->assertSame(2, $result['added']);
        $this->assertSame(0, $result['removed']);
        $this->assertSame(2, $result['total']);
        $matched = Membership::where('segment_id', $seg->id)
            ->pluck('user_id')
            ->all();
        sort($matched);
        $this->assertSame([$unverifiedA->id, $unverifiedB->id], $matched);
        $this->assertFalse(in_array($verified->id, $matched, true));
    }

    #[Test]
    #[TestDox('syncRuleSegment 按 identity_type=enterprise 过滤：仅企业认证用户被纳入分群')]
    public function sync_rule_segment_filters_by_identity_type_eq(): void
    {
        $seg = Segment::factory()->rule()->create([
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'identity_type', 'op' => '=', 'value' => IdentityType::ENTERPRISE->value],
                ],
            ],
        ]);

        $enterprise = $this->makeUser();
        $enterprise->forceFill([
            'identity_verified' => true,
            'identity_type' => IdentityType::ENTERPRISE,
        ])->saveQuietly();

        $personal = $this->makeUser();
        $personal->forceFill([
            'identity_verified' => true,
            'identity_type' => IdentityType::PERSONAL,
        ])->saveQuietly();

        $unverified = $this->makeUser(); // identity_type=null

        $result = $this->service->syncRuleSegment($seg);

        $this->assertSame(1, $result['added']);
        $this->assertSame(0, $result['removed']);
        $this->assertSame(1, $result['total']);
        $this->assertTrue(
            Membership::where('segment_id', $seg->id)->where('user_id', $enterprise->id)->exists()
        );
        $this->assertFalse(
            Membership::where('segment_id', $seg->id)->where('user_id', $personal->id)->exists()
        );
        $this->assertFalse(
            Membership::where('segment_id', $seg->id)->where('user_id', $unverified->id)->exists()
        );
    }

    #[Test]
    #[TestDox('syncRuleSegment 按 identity_type in [personal,enterprise] 过滤：含所有已实名用户')]
    public function sync_rule_segment_filters_by_identity_type_in(): void
    {
        $seg = Segment::factory()->rule()->create([
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [
                    [
                        'field' => 'identity_type',
                        'op' => 'in',
                        'value' => [IdentityType::PERSONAL->value, IdentityType::ENTERPRISE->value],
                    ],
                ],
            ],
        ]);

        $personal = $this->makeUser();
        $personal->forceFill([
            'identity_verified' => true,
            'identity_type' => IdentityType::PERSONAL,
        ])->saveQuietly();

        $enterprise = $this->makeUser();
        $enterprise->forceFill([
            'identity_verified' => true,
            'identity_type' => IdentityType::ENTERPRISE,
        ])->saveQuietly();

        $unverified = $this->makeUser(); // identity_type=null，不在 in 列表

        $result = $this->service->syncRuleSegment($seg);

        $this->assertSame(2, $result['added']);
        $this->assertSame(0, $result['removed']);
        $this->assertSame(2, $result['total']);
        $matched = Membership::where('segment_id', $seg->id)->pluck('user_id')->all();
        sort($matched);
        $expected = [$enterprise->id, $personal->id];
        sort($expected);
        $this->assertSame($expected, $matched);
        $this->assertFalse(in_array($unverified->id, $matched, true));
    }

    #[Test]
    #[TestDox('syncRuleSegment 按 identity_type is_null 过滤：仅未填类型的用户被纳入分群')]
    public function sync_rule_segment_filters_by_identity_type_is_null(): void
    {
        $seg = Segment::factory()->rule()->create([
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'identity_type', 'op' => 'is_null', 'value' => null],
                ],
            ],
        ]);

        $personal = $this->makeUser();
        $personal->forceFill([
            'identity_verified' => true,
            'identity_type' => IdentityType::PERSONAL,
        ])->saveQuietly();

        $unverifiedA = $this->makeUser(); // identity_type=null
        $unverifiedB = $this->makeUser(); // identity_type=null

        $result = $this->service->syncRuleSegment($seg);

        $this->assertSame(2, $result['added']);
        $this->assertSame(0, $result['removed']);
        $this->assertSame(2, $result['total']);
        $matched = Membership::where('segment_id', $seg->id)->pluck('user_id')->all();
        sort($matched);
        $this->assertSame([$unverifiedA->id, $unverifiedB->id], $matched);
        $this->assertFalse(in_array($personal->id, $matched, true));
    }

    #[Test]
    #[TestDox('syncRuleSegment 按 last_active_at > now-7d 过滤：仅近 7 天活跃用户被纳入分群')]
    public function sync_rule_segment_filters_by_last_active_recent(): void
    {
        $seg = Segment::factory()->rule()->create([
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'last_active_at', 'op' => '>', 'value' => 'now-7d'],
                ],
            ],
        ]);

        $recent = $this->makeUser();
        $recent->forceFill(['last_active_at' => now()->subDays(2)])->saveQuietly();

        $longAgo = $this->makeUser();
        $longAgo->forceFill(['last_active_at' => now()->subDays(30)])->saveQuietly();

        $never = $this->makeUser(); // last_active_at=null

        $result = $this->service->syncRuleSegment($seg);

        $this->assertSame(1, $result['added']);
        $this->assertSame(0, $result['removed']);
        $this->assertSame(1, $result['total']);
        $this->assertTrue(
            Membership::where('segment_id', $seg->id)->where('user_id', $recent->id)->exists()
        );
        $this->assertFalse(
            Membership::where('segment_id', $seg->id)->where('user_id', $longAgo->id)->exists()
        );
        $this->assertFalse(
            Membership::where('segment_id', $seg->id)->where('user_id', $never->id)->exists()
        );
    }

    #[Test]
    #[TestDox('syncRuleSegment 按 last_active_at is_null 过滤：仅从未活跃用户被纳入分群')]
    public function sync_rule_segment_filters_by_last_active_is_null(): void
    {
        $seg = Segment::factory()->rule()->create([
            'rule' => [
                'version' => 1,
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'last_active_at', 'op' => 'is_null', 'value' => null],
                ],
            ],
        ]);

        $recent = $this->makeUser();
        $recent->forceFill(['last_active_at' => now()->subDay()])->saveQuietly();

        $neverA = $this->makeUser();
        $neverB = $this->makeUser();

        $result = $this->service->syncRuleSegment($seg);

        $this->assertSame(2, $result['added']);
        $this->assertSame(0, $result['removed']);
        $this->assertSame(2, $result['total']);
        $matched = Membership::where('segment_id', $seg->id)->pluck('user_id')->all();
        sort($matched);
        $expected = [$neverA->id, $neverB->id];
        sort($expected);
        $this->assertSame($expected, $matched);
        $this->assertFalse(in_array($recent->id, $matched, true));
    }
}
