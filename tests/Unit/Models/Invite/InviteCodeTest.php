<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\Invite;

use App\Models\Invite\InviteCode;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * InviteCode 模型单元测试
 */
#[CoversClass(InviteCode::class)]
#[Group('models')]
#[Group('invite')]
class InviteCodeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('表名为 invite_codes')]
    public function table_name_is_invite_codes(): void
    {
        $this->assertSame('invite_codes', (new InviteCode)->getTable());
    }

    #[Test]
    #[TestDox('fillable 允许批量赋值指定字段')]
    public function fillable_allows_mass_assignment(): void
    {
        $inviteCode = InviteCode::create([
            'code' => 'manual_code_value',
            'owner_id' => 999,
            'owner_type' => 'admin',
        ]);

        $this->assertSame('manual_code_value', $inviteCode->code);
        $this->assertSame(999, $inviteCode->owner_id);
        $this->assertSame('admin', $inviteCode->owner_type);
    }

    #[Test]
    #[TestDox('创建时自动生成 26 位大写邀请码')]
    public function code_is_auto_generated_on_create(): void
    {
        $inviteCode = InviteCode::factory()->create([
            'owner_type' => 'user',
            'owner_id' => 90001,
        ]);

        $this->assertNotNull($inviteCode->code);
        $this->assertSame(26, strlen($inviteCode->code));
        $this->assertSame(strtoupper($inviteCode->code), $inviteCode->code);
    }

    #[Test]
    #[TestDox('用户创建时观察者自动为其生成邀请码')]
    public function observer_auto_provisions_code_for_user(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->inviteCode);
        $this->assertSame(26, strlen($user->inviteCode->code));
        $this->assertSame('user', $user->inviteCode->owner_type);
        $this->assertSame($user->id, $user->inviteCode->owner_id);
    }

    #[Test]
    #[TestDox('显式指定 code 时不自动生成')]
    public function explicit_code_is_not_overridden(): void
    {
        $inviteCode = InviteCode::factory()->create([
            'code' => 'explicit_code',
            'owner_type' => 'user',
            'owner_id' => 90002,
        ]);

        $this->assertSame('explicit_code', $inviteCode->code);
    }

    #[Test]
    #[TestDox('不同主体生成的邀请码唯一')]
    public function generated_codes_are_unique(): void
    {
        $code1 = InviteCode::factory()->create(['owner_type' => 'user', 'owner_id' => 90003]);
        $code2 = InviteCode::factory()->create(['owner_type' => 'user', 'owner_id' => 90004]);

        $this->assertNotSame($code1->code, $code2->code);
    }

    #[Test]
    #[TestDox('id 与 owner_id 正确 cast 为 integer')]
    public function id_fields_are_cast_to_integer(): void
    {
        $inviteCode = InviteCode::factory()->create(['owner_type' => 'user', 'owner_id' => 90005]);
        $inviteCode->refresh();

        $this->assertIsInt($inviteCode->id);
        $this->assertIsInt($inviteCode->owner_id);
    }

    #[Test]
    #[TestDox('created_at 与 updated_at 自动维护且为 Carbon 实例')]
    public function timestamps_are_auto_maintained(): void
    {
        $inviteCode = InviteCode::factory()->create(['owner_type' => 'user', 'owner_id' => 90006]);

        $this->assertInstanceOf(Carbon::class, $inviteCode->created_at);
        $this->assertInstanceOf(Carbon::class, $inviteCode->updated_at);
    }

    #[Test]
    #[TestDox('owner 多态关系返回所属主体（User）')]
    public function owner_relation_returns_user(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(User::class, $user->inviteCode->owner);
        $this->assertSame($user->id, $user->inviteCode->owner->id);
    }

    #[Test]
    #[TestDox('forOwner 工厂方法正确写入多态归属')]
    public function factory_for_owner_sets_morph_columns(): void
    {
        $user = User::factory()->create();
        // 用户已被观察者自动分配邀请码，删除后用工厂重建以验证工厂方法
        $user->inviteCode()->delete();

        $inviteCode = InviteCode::factory()->forOwner($user)->create();

        $this->assertSame('user', $inviteCode->owner_type);
        $this->assertSame($user->id, $inviteCode->owner_id);
        $this->assertInstanceOf(User::class, $inviteCode->owner);
    }

    #[Test]
    #[TestDox('同一主体只能持有一个邀请码（唯一索引）')]
    public function duplicate_owner_violates_unique_constraint(): void
    {
        InviteCode::factory()->create(['owner_type' => 'admin', 'owner_id' => 1001]);

        $this->expectException(QueryException::class);

        InviteCode::factory()->create(['code' => 'another_code', 'owner_type' => 'admin', 'owner_id' => 1001]);
    }

    #[Test]
    #[TestDox('相同邀请码不能重复（唯一索引）')]
    public function duplicate_code_violates_unique_constraint(): void
    {
        InviteCode::factory()->create(['code' => 'dup_code', 'owner_type' => 'user', 'owner_id' => 90007]);

        $this->expectException(QueryException::class);

        InviteCode::factory()->create(['code' => 'dup_code', 'owner_type' => 'user', 'owner_id' => 90008]);
    }

    #[Test]
    #[TestDox('支持不同主体类型共享同一张邀请码表（多态）')]
    public function supports_different_owner_types(): void
    {
        $userCode = InviteCode::factory()->create(['code' => 'user_code', 'owner_type' => 'user', 'owner_id' => 90009]);
        $adminCode = InviteCode::factory()->create(['code' => 'admin_code', 'owner_type' => 'admin', 'owner_id' => 1002]);

        $this->assertSame('user', $userCode->owner_type);
        $this->assertSame('admin', $adminCode->owner_type);
    }

    #[Test]
    #[TestDox('generateUniqueCode 返回未被占用的邀请码')]
    public function generate_unique_code_returns_unused_code(): void
    {
        $code = InviteCode::generateUniqueCode();

        $this->assertSame(26, strlen($code));
        $this->assertSame(0, InviteCode::where('code', $code)->count());
    }
}
