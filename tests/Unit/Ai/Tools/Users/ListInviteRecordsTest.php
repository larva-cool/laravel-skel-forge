<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Users;

use App\Ai\Tools\Users\ListInviteRecords;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\Invite\InviteRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 邀请记录查询工具测试
 */
#[CoversClass(ListInviteRecords::class)]
#[Group('ai')]
#[Group('invite')]
class ListInviteRecordsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 创建一个仅拥有指定权限的管理员
     *
     * @param  array<int, string>  $permissions
     */
    private function createAdminWithPermissions(array $permissions): Admin
    {
        $suffix = uniqid();

        $admin = Admin::query()->create([
            'username' => 'limited_'.$suffix,
            'name' => '受限管理员',
            'email' => "limited_{$suffix}@example.com",
            'password' => 'password',
            'status' => AdminStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'limited_'.$suffix,
            'display_name' => '受限角色',
            'guard_name' => AdminMenu::GUARD_NAME,
        ]);

        $role->syncPermissions($permissions);
        $admin->assignRole($role);

        return $admin->refresh();
    }

    /**
     * 创建一条邀请记录
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createInviteRecord(array $overrides = []): InviteRecord
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        return InviteRecord::factory()->create(array_merge([
            'inviter_id' => $inviter->id,
            'inviter_type' => 'user',
            'invitee_id' => $invitee->id,
            'invitee_type' => 'user',
        ], $overrides));
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ListInviteRecords)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 invite-records.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListInviteRecords)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('按邀请人筛选返回其下线邀请记录')]
    public function handle_filters_by_inviter(): void
    {
        $admin = $this->createAdminWithPermissions(['invite-records.index']);
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $this->createInviteRecord([
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
        ]);
        $this->createInviteRecord();

        $result = (new ListInviteRecords)->forAdmin($admin)->handle(new Request(['inviter_id' => $inviter->id]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame($inviter->id, $payload['data'][0]['inviter']['id']);
        $this->assertSame($invitee->id, $payload['data'][0]['invitee']['id']);
    }

    #[Test]
    #[TestDox('按被邀请人筛选返回其上线邀请记录')]
    public function handle_filters_by_invitee(): void
    {
        $admin = $this->createAdminWithPermissions(['invite-records.index']);
        $invitee = User::factory()->create();
        $this->createInviteRecord(['invitee_id' => $invitee->id]);
        $this->createInviteRecord();

        $result = (new ListInviteRecords)->forAdmin($admin)->handle(new Request(['invitee_id' => $invitee->id]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame($invitee->id, $payload['data'][0]['invitee']['id']);
    }
}
