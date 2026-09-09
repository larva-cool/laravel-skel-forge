<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\Invite;

use App\Models\Invite\InviteRecord;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * InviteRecord 模型单元测试
 */
#[CoversClass(InviteRecord::class)]
#[Group('models')]
#[Group('invite')]
class InviteRecordTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('fillable 允许批量赋值指定字段')]
    public function fillable_allows_mass_assignment(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        $record = InviteRecord::create([
            'inviter_id' => $inviter->id,
            'inviter_type' => $inviter->getMorphClass(),
            'invitee_id' => $invitee->id,
            'invitee_type' => $invitee->getMorphClass(),
            'description' => '通过邀请码注册',
        ]);

        $this->assertSame($inviter->id, $record->inviter_id);
        $this->assertSame($invitee->id, $record->invitee_id);
        $this->assertSame('通过邀请码注册', $record->description);
    }

    #[Test]
    #[TestDox('表名为 invite_records')]
    public function table_name_is_invite_records(): void
    {
        $this->assertSame('invite_records', (new InviteRecord)->getTable());
    }

    #[Test]
    #[TestDox('inviter_type 与 invitee_type 使用 morph 别名存储')]
    public function morph_types_are_stored_as_alias(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        $record = InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee)
            ->create();

        $this->assertSame('user', $record->inviter_type);
        $this->assertSame('user', $record->invitee_type);
    }

    #[Test]
    #[TestDox('id 与关联 ID 正确 cast 为 integer')]
    public function id_fields_are_cast_to_integer(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        $record = InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee)
            ->create();

        $record->refresh();

        $this->assertIsInt($record->id);
        $this->assertIsInt($record->inviter_id);
        $this->assertIsInt($record->invitee_id);
    }

    #[Test]
    #[TestDox('没有 updated_at 字段')]
    public function no_updated_at_field(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        $record = InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee)
            ->create();

        $this->assertNull($record->updated_at);
    }

    #[Test]
    #[TestDox('created_at 自动维护且为 Carbon 实例')]
    public function created_at_is_auto_maintained(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        $record = InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee)
            ->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $record->created_at);
    }

    #[Test]
    #[TestDox('inviter 多态关系返回邀请人（User）')]
    public function inviter_relation_returns_user_inviter(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        $record = InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee)
            ->create();

        $this->assertInstanceOf(User::class, $record->inviter);
        $this->assertSame($inviter->id, $record->inviter->id);
    }

    #[Test]
    #[TestDox('invitee 多态关系返回被邀请人（User）')]
    public function invitee_relation_returns_user_invitee(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        $record = InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee)
            ->create();

        $this->assertInstanceOf(User::class, $record->invitee);
        $this->assertSame($invitee->id, $record->invitee->id);
    }

    #[Test]
    #[TestDox('inviter 与 invitee 类型支持不同值（模拟多类型场景）')]
    public function morph_types_support_different_values(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        $record = InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee)
            ->create([
                'inviter_type' => 'admin',
                'inviter_id' => 1,
            ]);

        $this->assertSame('admin', $record->inviter_type);
        $this->assertSame('user', $record->invitee_type);
    }

    #[Test]
    #[TestDox('同一被邀请人只能有一条邀请记录（唯一索引）')]
    public function duplicate_invitee_violates_unique_constraint(): void
    {
        $inviter1 = User::factory()->create();
        $inviter2 = User::factory()->create();
        $invitee = User::factory()->create();

        InviteRecord::factory()
            ->forInviter($inviter1)
            ->forInvitee($invitee)
            ->create();

        $this->expectException(QueryException::class);

        InviteRecord::factory()
            ->forInviter($inviter2)
            ->forInvitee($invitee)
            ->create();
    }

    #[Test]
    #[TestDox('同一邀请人可邀请多个不同用户')]
    public function inviter_can_invite_multiple_users(): void
    {
        $inviter = User::factory()->create();
        $invitee1 = User::factory()->create();
        $invitee2 = User::factory()->create();

        InviteRecord::factory()->forInviter($inviter)->forInvitee($invitee1)->create();
        InviteRecord::factory()->forInviter($inviter)->forInvitee($invitee2)->create();

        $this->assertSame(2, InviteRecord::where('inviter_id', $inviter->id)->count());
    }

    #[Test]
    #[TestDox('description 默认为 null')]
    public function description_defaults_to_null(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        $record = InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee)
            ->create();

        $this->assertNull($record->description);
    }

    #[Test]
    #[TestDox('withDescription 工厂方法可设置描述')]
    public function factory_with_description_sets_description(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        $record = InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee)
            ->withDescription('通过分享链接注册')
            ->create();

        $this->assertSame('通过分享链接注册', $record->description);
    }
}
