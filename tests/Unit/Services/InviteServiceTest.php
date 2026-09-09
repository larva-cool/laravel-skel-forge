<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Events\Invite\InviteRelationshipCreated;
use App\Models\Invite\InviteRecord;
use App\Models\User;
use App\Services\InviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * InviteService 单元测试
 */
#[CoversClass(InviteService::class)]
#[Group('services')]
#[Group('invite')]
class InviteServiceTest extends TestCase
{
    use RefreshDatabase;

    private InviteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(InviteService::class);
    }

    /**
     * 创建一个带自定义邀请码的用户
     */
    private function createUserWithInviteCode(string $code): User
    {
        $user = User::factory()->create();
        $user->inviteCode->forceFill(['code' => $code])->save();

        return $user;
    }

    #[Test]
    #[TestDox('通过有效邀请码可建立邀请关系并触发事件')]
    public function create_relationship_by_valid_code(): void
    {
        Event::fake([InviteRelationshipCreated::class]);

        $inviter = $this->createUserWithInviteCode('test_inviter_code');
        $invitee = User::factory()->create();

        $record = $this->service->createRelationshipByCode(
            $invitee,
            'test_inviter_code',
            '通过邀请码注册'
        );

        $this->assertNotNull($record);
        $this->assertInstanceOf(InviteRecord::class, $record);
        $this->assertSame($inviter->id, $record->inviter_id);
        $this->assertSame('user', $record->inviter_type);
        $this->assertSame($invitee->id, $record->invitee_id);
        $this->assertSame('user', $record->invitee_type);
        $this->assertSame('通过邀请码注册', $record->description);

        Event::assertDispatched(InviteRelationshipCreated::class, function ($event) use ($record) {
            return $event->inviteRecord->is($record);
        });
    }

    #[Test]
    #[TestDox('无效邀请码返回 null，不创建记录')]
    public function invalid_code_returns_null(): void
    {
        Event::fake([InviteRelationshipCreated::class]);

        $invitee = User::factory()->create();

        $result = $this->service->createRelationshipByCode($invitee, 'invalid_code_xxx');

        $this->assertNull($result);
        $this->assertSame(0, InviteRecord::count());
        Event::assertNotDispatched(InviteRelationshipCreated::class);
    }

    #[Test]
    #[TestDox('不能自己邀请自己')]
    public function cannot_invite_self(): void
    {
        Event::fake([InviteRelationshipCreated::class]);

        $user = $this->createUserWithInviteCode('self_invite_code');

        $result = $this->service->createRelationshipByCode(
            $user,
            'self_invite_code'
        );

        $this->assertNull($result);
        $this->assertSame(0, InviteRecord::count());
        Event::assertNotDispatched(InviteRelationshipCreated::class);
    }

    #[Test]
    #[TestDox('同一被邀请人只能建立一次邀请关系（幂等）')]
    public function duplicate_invitee_returns_null_idempotent(): void
    {
        Event::fake([InviteRelationshipCreated::class]);

        $inviter1 = $this->createUserWithInviteCode('inviter_one');
        $inviter2 = $this->createUserWithInviteCode('inviter_two');
        $invitee = User::factory()->create();

        // 第一次成功
        $first = $this->service->createRelationshipByCode(
            $invitee,
            'inviter_one',
            '首次邀请'
        );
        $this->assertNotNull($first);

        // 第二次（不同邀请人）返回 null
        $second = $this->service->createRelationshipByCode(
            $invitee,
            'inviter_two',
            '二次邀请'
        );
        $this->assertNull($second);

        // 数据库只有一条记录，且为第一个邀请人
        $this->assertSame(1, InviteRecord::count());
        $record = InviteRecord::first();
        $this->assertSame($inviter1->id, $record->inviter_id);
    }

    #[Test]
    #[TestDox('hasInviter 方法正确判断是否有邀请人')]
    public function has_inviter_check(): void
    {
        $inviter = $this->createUserWithInviteCode('has_inviter_check');
        $invitee = User::factory()->create();
        $lonely = User::factory()->create();

        $this->service->createRelationshipByCode($invitee, 'has_inviter_check');

        $this->assertTrue($this->service->hasInviter($invitee));
        $this->assertFalse($this->service->hasInviter($lonely));
    }

    #[Test]
    #[TestDox('getInviteeRecords 分页返回邀请记录')]
    public function get_invitee_records_pagination(): void
    {
        $inviter = $this->createUserWithInviteCode('inviter_for_paging');

        for ($i = 0; $i < 5; $i++) {
            $invitee = User::factory()->create();
            $this->service->createRelationshipByCode($invitee, 'inviter_for_paging');
        }

        $paginator = $this->service->getInviteeRecords($inviter, 10);

        $this->assertSame(5, $paginator->total());
        $this->assertSame(5, $paginator->count());
    }
}
