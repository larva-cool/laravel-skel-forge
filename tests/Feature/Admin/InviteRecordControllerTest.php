<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminStatus;
use App\Http\Controllers\Admin\InviteRecordController;
use App\Models\Admin\Admin;
use App\Models\Invite\InviteRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台邀请记录控制器功能测试
 */
#[CoversClass(InviteRecordController::class)]
#[Group('admin')]
#[Group('invite')]
class InviteRecordControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 创建一条邀请记录
     */
    private function createRecord(?User $inviter = null, ?User $invitee = null, array $overrides = []): InviteRecord
    {
        $inviter ??= User::factory()->create();
        $invitee ??= User::factory()->create();

        return InviteRecord::factory()
            ->forInviter($inviter)
            ->forInvitee($invitee)
            ->create($overrides);
    }

    #[Test]
    #[TestDox('未登录访问邀请记录列表返回 401')]
    public function guest_cannot_list_invite_records(): void
    {
        $this->getJson('/admin/invite-records')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('未登录访问邀请记录详情返回 401')]
    public function guest_cannot_show_invite_record(): void
    {
        $record = $this->createRecord();

        $this->getJson('/admin/invite-records/'.$record->id)->assertUnauthorized();
    }

    #[Test]
    #[TestDox('无权限管理员访问邀请记录列表返回 403')]
    public function admin_without_permission_cannot_list_invite_records(): void
    {
        $admin = Admin::query()->create([
            'username' => 'viewer',
            'name' => '受限管理员',
            'email' => 'viewer@example.com',
            'password' => bcrypt('12345678'),
            'status' => AdminStatus::ACTIVE,
        ]);

        $this->actingAs($admin, 'admin')
            ->getJson('/admin/invite-records')
            ->assertForbidden();
    }

    #[Test]
    #[TestDox('超级管理员可获取邀请记录分页列表')]
    public function admin_can_list_invite_records(): void
    {
        $inviter = User::factory()->create(['name' => '邀请人甲']);
        $invitee = User::factory()->create(['name' => '被邀请人乙']);
        $this->createRecord($inviter, $invitee, ['description' => '通过邀请码注册']);

        $response = $this->actingAsAdmin()
            ->getJson('/admin/invite-records')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'inviter_type',
                    'inviter_id',
                    'inviter_name',
                    'invitee_type',
                    'invitee_id',
                    'invitee_name',
                    'description',
                    'created_at',
                ],
            ],
            'links',
            'meta',
        ]);

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('邀请人甲', $response->json('data.0.inviter_name'));
        $this->assertSame('被邀请人乙', $response->json('data.0.invitee_name'));
        $this->assertSame('user', $response->json('data.0.inviter_type'));
    }

    #[Test]
    #[TestDox('邀请记录列表支持按邀请人ID筛选')]
    public function index_filters_by_inviter_id(): void
    {
        $inviter = User::factory()->create();
        $this->createRecord($inviter);
        $this->createRecord(); // 其它邀请人

        $response = $this->actingAsAdmin()
            ->getJson('/admin/invite-records?inviter_id='.$inviter->id)
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($inviter->id, $response->json('data.0.inviter_id'));
    }

    #[Test]
    #[TestDox('邀请记录列表支持按被邀请人ID筛选')]
    public function index_filters_by_invitee_id(): void
    {
        $invitee = User::factory()->create();
        $this->createRecord(null, $invitee);
        $this->createRecord();

        $response = $this->actingAsAdmin()
            ->getJson('/admin/invite-records?invitee_id='.$invitee->id)
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($invitee->id, $response->json('data.0.invitee_id'));
    }

    #[Test]
    #[TestDox('邀请记录列表支持按主体类型筛选')]
    public function index_filters_by_inviter_type(): void
    {
        $this->createRecord();

        $matched = $this->actingAsAdmin()
            ->getJson('/admin/invite-records?inviter_type=user')
            ->assertOk();
        $this->assertSame(1, $matched->json('meta.total'));

        $unmatched = $this->actingAsAdmin()
            ->getJson('/admin/invite-records?inviter_type=admin')
            ->assertOk();
        $this->assertSame(0, $unmatched->json('meta.total'));
    }

    #[Test]
    #[TestDox('邀请记录列表支持按描述模糊筛选')]
    public function index_filters_by_description(): void
    {
        $this->createRecord(null, null, ['description' => '通过邀请码注册']);
        $this->createRecord(null, null, ['description' => '后台手动绑定']);

        $response = $this->actingAsAdmin()
            ->getJson('/admin/invite-records?'.http_build_query(['description' => '邀请码']))
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('通过邀请码注册', $response->json('data.0.description'));
    }

    #[Test]
    #[TestDox('邀请记录列表支持按时间范围筛选')]
    public function index_filters_by_created_range(): void
    {
        $old = $this->createRecord();
        $old->forceFill(['created_at' => Carbon::parse('2020-01-01 10:00:00')])->save();

        $this->createRecord(); // 今天创建

        $response = $this->actingAsAdmin()
            ->getJson('/admin/invite-records?created_start='.Carbon::today()->toDateString())
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
    }

    #[Test]
    #[TestDox('无效的主体类型参数返回 422')]
    public function index_rejects_invalid_inviter_type(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/invite-records?inviter_type=invalid_type')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inviter_type']);
    }

    #[Test]
    #[TestDox('结束时间早于开始时间返回 422')]
    public function index_rejects_invalid_date_range(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/invite-records?created_start=2026-01-10&created_end=2026-01-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['created_end']);
    }

    #[Test]
    #[TestDox('邀请记录列表支持 per_page 参数')]
    public function index_supports_per_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createRecord();
        }

        $response = $this->actingAsAdmin()
            ->getJson('/admin/invite-records?per_page=2')
            ->assertOk();

        $this->assertSame(2, $response->json('meta.per_page'));
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(5, $response->json('meta.total'));
    }

    #[Test]
    #[TestDox('超级管理员可获取邀请记录详情')]
    public function admin_can_show_invite_record(): void
    {
        $inviter = User::factory()->create(['name' => '邀请人甲']);
        $invitee = User::factory()->create(['name' => '被邀请人乙']);
        $record = $this->createRecord($inviter, $invitee, ['description' => '通过邀请码注册']);

        $response = $this->actingAsAdmin()
            ->getJson('/admin/invite-records/'.$record->id)
            ->assertOk();

        $this->assertSame($record->id, $response->json('id'));
        $this->assertSame('邀请人甲', $response->json('inviter_name'));
        $this->assertSame('被邀请人乙', $response->json('invitee_name'));
        $this->assertSame('通过邀请码注册', $response->json('description'));
    }

    #[Test]
    #[TestDox('访问不存在的邀请记录返回 404')]
    public function show_returns_404_for_missing_record(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/invite-records/999999')
            ->assertNotFound();
    }
}
