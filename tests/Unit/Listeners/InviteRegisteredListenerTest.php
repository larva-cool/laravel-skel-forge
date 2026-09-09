<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Invite\InviteRegistered;
use App\Events\Invite\InviteRelationshipCreated;
use App\Listeners\User\InviteRegisteredListener;
use App\Models\Invite\InviteRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * InviteRegisteredListener 单元测试
 */
#[CoversClass(InviteRegisteredListener::class)]
#[Group('listeners')]
#[Group('invite')]
class InviteRegisteredListenerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('handle 方法通过邀请码建立邀请关系并触发落地事件')]
    public function handle_creates_relationship_and_dispatches_relationship_created(): void
    {
        Event::fake([InviteRelationshipCreated::class]);

        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $inviteCode = $inviter->inviteCode->code;

        $event = new InviteRegistered($invitee, $inviteCode);
        $listener = $this->app->make(InviteRegisteredListener::class);
        $listener->handle($event);

        // 验证邀请关系已创建
        $this->assertSame(1, InviteRecord::count());
        $record = InviteRecord::first();
        $this->assertSame($inviter->id, $record->inviter_id);
        $this->assertSame($invitee->id, $record->invitee_id);
        $this->assertSame('通过邀请码注册', $record->description);

        // 验证落地事件已触发
        Event::assertDispatched(InviteRelationshipCreated::class, function ($e) use ($record) {
            return $e->inviteRecord->is($record);
        });
    }

    #[Test]
    #[TestDox('无效邀请码不创建邀请关系也不触发落地事件')]
    public function handle_with_invalid_code_does_nothing(): void
    {
        Event::fake([InviteRelationshipCreated::class]);

        $invitee = User::factory()->create();

        $event = new InviteRegistered($invitee, 'nonexistent_code');
        $listener = $this->app->make(InviteRegisteredListener::class);
        $listener->handle($event);

        $this->assertSame(0, InviteRecord::count());
        Event::assertNotDispatched(InviteRelationshipCreated::class);
    }

    #[Test]
    #[TestDox('监听器实现 ShouldQueue 接口')]
    public function listener_implements_should_queue(): void
    {
        $listener = $this->app->make(InviteRegisteredListener::class);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $listener);
    }
}
