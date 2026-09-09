<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\InviteController;
use App\Models\Invite\InviteRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 前台邀请接口功能测试
 */
#[CoversClass(InviteController::class)]
#[Group('api')]
#[Group('invite')]
class InviteControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    #[Test]
    #[TestDox('未登录访问邀请记录返回 401')]
    public function guest_cannot_get_records(): void
    {
        $this->getJson('/api/v1/invite/records')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('未登录访问邀请统计返回 401')]
    public function guest_cannot_get_stats(): void
    {
        $this->getJson('/api/v1/invite/stats')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('邀请记录列表返回标准分页结构')]
    public function records_returns_paginated_structure(): void
    {
        Sanctum::actingAs($this->user);

        $invitee = User::factory()->create(['name' => '被邀请用户']);
        InviteRecord::factory()
            ->forInviter($this->user)
            ->forInvitee($invitee)
            ->withDescription('通过邀请码注册')
            ->create();

        $response = $this->getJson('/api/v1/invite/records')->assertOk();

        $response->assertJsonStructure([
            'data' => [
                ['id', 'invitee' => ['id', 'name', 'username', 'avatar'], 'description', 'created_at'],
            ],
            'links',
            'meta',
        ]);

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('被邀请用户', $response->json('data.0.invitee.name'));
        $this->assertSame('通过邀请码注册', $response->json('data.0.description'));
    }

    #[Test]
    #[TestDox('邀请记录只返回自己邀请的用户')]
    public function records_only_returns_own_invitees(): void
    {
        Sanctum::actingAs($this->user);

        // 自己邀请的
        InviteRecord::factory()
            ->forInviter($this->user)
            ->forInvitee(User::factory()->create())
            ->create();

        // 别人邀请的
        $otherInviter = User::factory()->create();
        InviteRecord::factory()
            ->forInviter($otherInviter)
            ->forInvitee(User::factory()->create())
            ->create();

        $response = $this->getJson('/api/v1/invite/records')->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
    }

    #[Test]
    #[TestDox('邀请记录支持 per_page 参数')]
    public function records_supports_per_page(): void
    {
        Sanctum::actingAs($this->user);

        for ($i = 0; $i < 5; $i++) {
            InviteRecord::factory()
                ->forInviter($this->user)
                ->forInvitee(User::factory()->create())
                ->create();
        }

        $response = $this->getJson('/api/v1/invite/records?per_page=2')->assertOk();

        $this->assertSame(2, $response->json('meta.per_page'));
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(5, $response->json('meta.total'));
    }

    #[Test]
    #[TestDox('邀请记录按 ID 倒序返回')]
    public function records_ordered_by_id_desc(): void
    {
        Sanctum::actingAs($this->user);

        $first = InviteRecord::factory()
            ->forInviter($this->user)
            ->forInvitee(User::factory()->create())
            ->create();
        $second = InviteRecord::factory()
            ->forInviter($this->user)
            ->forInvitee(User::factory()->create())
            ->create();

        $response = $this->getJson('/api/v1/invite/records')->assertOk();

        $this->assertSame($second->id, $response->json('data.0.id'));
        $this->assertSame($first->id, $response->json('data.1.id'));
    }

    #[Test]
    #[TestDox('邀请统计返回邀请人数与是否有邀请人')]
    public function stats_returns_counts(): void
    {
        Sanctum::actingAs($this->user);

        // 我邀请了 3 个人
        for ($i = 0; $i < 3; $i++) {
            InviteRecord::factory()
                ->forInviter($this->user)
                ->forInvitee(User::factory()->create())
                ->create();
        }

        // 我自己也被人邀请
        $myInviter = User::factory()->create();
        InviteRecord::factory()
            ->forInviter($myInviter)
            ->forInvitee($this->user)
            ->create();

        $response = $this->getJson('/api/v1/invite/stats')->assertOk();

        $this->assertSame(3, $response->json('invitee_count'));
        $this->assertTrue($response->json('has_inviter'));
    }

    #[Test]
    #[TestDox('无邀请记录时统计返回 0 与 false')]
    public function stats_returns_zero_when_no_records(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/invite/stats')->assertOk();

        $this->assertSame(0, $response->json('invitee_count'));
        $this->assertFalse($response->json('has_inviter'));
    }
}
