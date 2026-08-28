<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin\Segment;

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
 * User::deleted 事件清理分群成员测试
 */
#[CoversClass(\App\Observers\UserObserver::class)]
#[Group('admin')]
#[Group('segment')]
class UserDeletedMembershipCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function service(): \App\Services\Segment\SegmentService
    {
        return new \App\Services\Segment\SegmentService(new \App\Services\Segment\SegmentRuleEvaluator);
    }

    #[Test]
    #[TestDox('User 软删触发 deleted 事件，清理 user_segment_memberships 中该用户所有行')]
    public function soft_delete_clears_memberships(): void
    {
        $seg1 = Segment::factory()->manual()->create();
        $seg2 = Segment::factory()->rule()->create();
        $user = User::factory()->create();

        $this->service()->attachUsers($seg1, [$user->id], Membership::SOURCE_MANUAL);
        $this->service()->attachUsers($seg2, [$user->id], Membership::SOURCE_RULE);

        $this->assertSame(2, Membership::where('user_id', $user->id)->count());

        $user->delete(); // 软删

        $this->assertSame(0, Membership::where('user_id', $user->id)->count());
    }

    #[Test]
    #[TestDox('删除一个用户不影响其他用户的分群成员记录')]
    public function delete_isolates_to_target_user(): void
    {
        $seg = Segment::factory()->manual()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->service()->attachUsers($seg, [$alice->id, $bob->id]);
        $this->assertSame(2, Membership::where('segment_id', $seg->id)->count());

        $alice->delete();

        $this->assertSame(0, Membership::where('user_id', $alice->id)->count());
        $this->assertSame(1, Membership::where('segment_id', $seg->id)->count());
        $this->assertDatabaseHas('user_segment_memberships', [
            'segment_id' => $seg->id,
            'user_id' => $bob->id,
        ]);
    }

    #[Test]
    #[TestDox('没有分群成员的用户被删不会报错')]
    public function delete_user_without_memberships_is_safe(): void
    {
        $user = User::factory()->create();
        $this->assertSame(0, Membership::where('user_id', $user->id)->count());

        $user->delete();

        $this->assertSame(0, Membership::where('user_id', $user->id)->count());
        // 软删后记录仍存在但带 deleted_at
        $this->assertNotNull(User::withTrashed()->find($user->id)?->deleted_at);
    }
}
