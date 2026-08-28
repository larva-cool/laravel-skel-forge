<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Segment;

use App\Models\Admin\Admin;
use App\Models\User;
use App\Models\UserSegment\Segment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 用户列表 segment_ids 筛选测试（AND 语义）
 */
#[CoversClass(\App\Http\Controllers\Admin\UserController::class)]
#[Group('admin')]
#[Group('segment')]
class UserSegmentFilterTest extends TestCase
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

    private function makeUser(?string $username = null): User
    {
        return User::factory()->create([
            'username' => $username ?? 'user_'.fake()->unique()->word(),
        ]);
    }

    #[Test]
    #[TestDox('segment_ids 单值：仅命中该分群成员')]
    public function single_segment_id_filters_users(): void
    {
        $seg = Segment::factory()->manual()->create();
        $u1 = $this->makeUser('alice');
        $u2 = $this->makeUser('bob');
        $this->service()->attachUsers($seg, [$u1->id]);

        $response = $this->actingAsAdmin()
            ->getJson('/admin/users?segment_ids[]='.$seg->id);

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $u1->id);
    }

    #[Test]
    #[TestDox('segment_ids 多值 AND：用户必须同时属于全部指定分群')]
    public function multiple_segment_ids_apply_and_logic(): void
    {
        $s1 = Segment::factory()->manual()->create(['name' => 'VIP']);
        $s2 = Segment::factory()->manual()->create(['name' => '活跃']);

        $both = $this->makeUser('both');    // 两个分群都有
        $onlyVip = $this->makeUser('vip');   // 仅 s1
        $onlyActive = $this->makeUser('act'); // 仅 s2
        $none = $this->makeUser('none');

        $svc = $this->service();
        $svc->attachUsers($s1, [$both->id, $onlyVip->id]);
        $svc->attachUsers($s2, [$both->id, $onlyActive->id]);

        $response = $this->actingAsAdmin()
            ->getJson('/admin/users?segment_ids[]='.$s1->id.'&segment_ids[]='.$s2->id);

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $both->id);
    }

    #[Test]
    #[TestDox('segment_ids 支持逗号分隔字符串')]
    public function segment_ids_accepts_comma_separated_string(): void
    {
        $s1 = Segment::factory()->manual()->create();
        $s2 = Segment::factory()->manual()->create();
        $both = $this->makeUser('both');

        $svc = $this->service();
        $svc->attachUsers($s1, [$both->id]);
        $svc->attachUsers($s2, [$both->id]);

        $response = $this->actingAsAdmin()
            ->getJson('/admin/users?segment_ids='.$s1->id.','.$s2->id);

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $both->id);
    }

    #[Test]
    #[TestDox('segment_ids 空数组/不存在：返回全部用户')]
    public function empty_segment_ids_returns_all(): void
    {
        $seg = Segment::factory()->manual()->create();
        $u1 = $this->makeUser('alice');
        $u2 = $this->makeUser('bob');
        $this->service()->attachUsers($seg, [$u1->id]);

        // 空值
        $r1 = $this->actingAsAdmin()->getJson('/admin/users?segment_ids[]=');
        $r1->assertOk()->assertJsonPath('meta.total', 2);

        // 不存在的分群 ID
        $r2 = $this->actingAsAdmin()->getJson('/admin/users?segment_ids[]=99999');
        $r2->assertOk()->assertJsonPath('meta.total', 0);
    }

    #[Test]
    #[TestDox('segment_ids 与其他筛选（keyword）可叠加')]
    public function segment_ids_compose_with_other_filters(): void
    {
        $seg = Segment::factory()->manual()->create();
        $matched = $this->makeUser('vip_user');
        $unmatchedName = $this->makeUser('normal_user');
        $otherSegmentUser = $this->makeUser('someone_else');

        $svc = $this->service();
        $svc->attachUsers($seg, [$matched->id, $otherSegmentUser->id]);

        // keyword=vip_user 只匹配 matched，但 otherSegmentUser 因不属于分群被排掉
        $response = $this->actingAsAdmin()
            ->getJson('/admin/users?segment_ids[]='.$seg->id.'&keyword=vip_user');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $matched->id);
    }

    private function service(): \App\Services\Segment\SegmentService
    {
        return new \App\Services\Segment\SegmentService(new \App\Services\Segment\SegmentRuleEvaluator);
    }
}
