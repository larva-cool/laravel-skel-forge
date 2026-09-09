<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\User;

use App\Models\Invite\InviteRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * HasInvitation Trait 单元测试
 */
#[CoversClass(\App\Models\Traits\HasInvitation::class)]
#[Group('models')]
#[Group('invite')]
class HasInvitationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('invitees 关系返回我邀请的用户列表')]
    public function invitees_relation_returns_invited_users(): void
    {
        /** @var User $inviter */
        $inviter = User::factory()->create();
        $invitee1 = User::factory()->create();
        $invitee2 = User::factory()->create();

        InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee1)
            ->create();
        InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee2)
            ->create();

        $this->assertSame(2, $inviter->invitees()->count());
    }

    #[Test]
    #[TestDox('inviterRelation 关系返回我的邀请人记录')]
    public function inviter_relation_returns_inviter_record(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        $this->assertNull($invitee->inviterRelation);

        InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee)
            ->create();

        $invitee->refresh();
        $this->assertInstanceOf(InviteRecord::class, $invitee->inviterRelation);
        $this->assertSame($inviter->id, $invitee->inviterRelation->inviter_id);
    }

    #[Test]
    #[TestDox('getInviteCode 返回用户邀请码')]
    public function get_invite_code_returns_code(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->getInviteCode());
        $this->assertSame($user->inviteCode->code, $user->getInviteCode());
    }

    #[Test]
    #[TestDox('hasInviter 判断是否有邀请人')]
    public function has_inviter_returns_bool(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        $this->assertFalse($invitee->hasInviter());

        InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee)
            ->create();

        $invitee->refresh();
        $this->assertTrue($invitee->hasInviter());
    }

    #[Test]
    #[TestDox('getInviteeCount 返回邀请人数')]
    public function get_invitee_count_returns_count(): void
    {
        $inviter = User::factory()->create();

        $this->assertSame(0, $inviter->getInviteeCount());

        InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee(User::factory()->create())
            ->create();
        InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee(User::factory()->create())
            ->create();

        $this->assertSame(2, $inviter->getInviteeCount());
    }
}
