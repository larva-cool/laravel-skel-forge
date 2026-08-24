<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\CoinType;
use App\Enums\PointType;
use App\Http\Controllers\Admin\UserController;
use App\Models\Admin\Admin;
use App\Models\User;
use App\Support\CoinHelper;
use App\Support\PointHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台用户管理控制器功能测试
 */
#[CoversClass(UserController::class)]
#[Group('admin')]
#[Group('admin-users')]
class UserControllerTest extends TestCase
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

    #[Test]
    #[TestDox('未登录访问用户列表返回 401')]
    public function guest_cannot_list_users(): void
    {
        $response = $this->getJson('/admin/users');
        $response->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取用户列表返回 200 与分页数据')]
    public function admin_can_list_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->actingAsAdmin()->getJson('/admin/users');
        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按关键字搜索用户')]
    public function admin_can_search_users_by_keyword(): void
    {
        User::factory()->create(['username' => 'testuser', 'name' => '测试用户']);

        $response = $this->actingAsAdmin()->getJson('/admin/users?keyword=testuser');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'testuser');
    }

    #[Test]
    #[TestDox('按状态过滤用户')]
    public function admin_can_filter_users_by_status(): void
    {
        User::factory()->count(2)->create();
        User::factory()->frozen()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/users?status=1');
        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    #[TestDox('获取用户详情返回 200')]
    public function admin_can_view_user_detail(): void
    {
        $user = User::factory()->create(['name' => '张三']);

        $response = $this->actingAsAdmin()->getJson("/admin/users/{$user->id}");
        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('name', '张三')
            ->assertJsonStructure(['profile']);
    }

    #[Test]
    #[TestDox('更新用户信息')]
    public function admin_can_update_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsAdmin()->putJson("/admin/users/{$user->id}", [
            'name' => '更新后的昵称',
            'status' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('name', '更新后的昵称');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '更新后的昵称',
        ]);
    }

    #[Test]
    #[TestDox('冻结/启用用户状态切换')]
    public function admin_can_toggle_user_status(): void
    {
        $user = User::factory()->create();

        // 切换为冻结
        $response = $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/toggle-status");
        $response->assertOk()
            ->assertJsonPath('status.value', 0);

        // 切换回正常
        $response = $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/toggle-status");
        $response->assertOk()
            ->assertJsonPath('status.value', 1);
    }

    #[Test]
    #[TestDox('重置用户密码')]
    public function admin_can_reset_user_password(): void
    {
        $user = User::factory()->create();
        $oldPasswordHash = $user->password;

        $response = $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/reset-password", [
            'password' => 'NewPassword123!',
        ]);

        $response->assertNoContent();
        $user->refresh();
        $this->assertNotEquals($oldPasswordHash, $user->password);
    }

    #[Test]
    #[TestDox('调整用户积分')]
    public function admin_can_adjust_user_points(): void
    {
        $user = User::factory()->create();
        PointHelper::incr($user->id, 100, $user, PointType::TYPE_ADMIN_RECHARGE, '初始化');

        $response = $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/adjust-balance", [
            'type' => 'points',
            'amount' => 50,
        ]);

        $response->assertOk()
            ->assertJsonPath('available_points', 150);
    }

    #[Test]
    #[TestDox('调整用户积分（扣减）')]
    public function admin_can_deduct_user_points(): void
    {
        $user = User::factory()->create();
        PointHelper::incr($user->id, 100, $user, PointType::TYPE_ADMIN_RECHARGE, '初始化');

        $response = $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/adjust-balance", [
            'type' => 'points',
            'amount' => -30,
        ]);

        $response->assertOk()
            ->assertJsonPath('available_points', 70);
    }

    #[Test]
    #[TestDox('调整用户积分（积分不足返回422）')]
    public function admin_cannot_deduct_points_beyond_balance(): void
    {
        $user = User::factory()->create();
        PointHelper::incr($user->id, 10, $user, PointType::TYPE_ADMIN_RECHARGE, '初始化');

        $response = $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/adjust-balance", [
            'type' => 'points',
            'amount' => -50,
        ]);

        $response->assertStatus(422);
        $this->assertSame(10, $user->fresh()->available_points);
    }

    #[Test]
    #[TestDox('调整用户金币')]
    public function admin_can_adjust_user_coins(): void
    {
        $user = User::factory()->create();
        CoinHelper::incr($user, 100, $user, CoinType::TYPE_ADMIN_RECHARGE, '初始化');

        $response = $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/adjust-balance", [
            'type' => 'coins',
            'amount' => -40,
        ]);

        $response->assertOk()
            ->assertJsonPath('available_coins', 60);
    }

    #[Test]
    #[TestDox('调整用户金币（金币不足返回422）')]
    public function admin_cannot_deduct_coins_beyond_balance(): void
    {
        $user = User::factory()->create();
        CoinHelper::incr($user, 10, $user, CoinType::TYPE_ADMIN_RECHARGE, '初始化');

        $response = $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/adjust-balance", [
            'type' => 'coins',
            'amount' => -50,
        ]);

        $response->assertStatus(422);
        $this->assertSame(10, $user->fresh()->available_coins);
    }

    #[Test]
    #[TestDox('延长用户 VIP 天数')]
    public function admin_can_extend_user_vip(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/extend-vip", [
            'days' => 30,
        ]);

        $response->assertOk()
            ->assertJsonPath('is_vip', true);

        $this->assertTrue($user->fresh()->isVip());
    }

    #[Test]
    #[TestDox('删除用户（软删除）')]
    public function admin_can_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsAdmin()->deleteJson("/admin/users/{$user->id}");
        $response->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    #[Test]
    #[TestDox('获取用户登录历史')]
    public function admin_can_view_user_login_histories(): void
    {
        $user = User::factory()->create();
        \App\Models\System\LoginHistory::factory()->count(2)->create([
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
        ]);

        $response = $this->actingAsAdmin()->getJson("/admin/users/{$user->id}/login-histories");
        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    #[TestDox('重置用户手机号联系')]
    public function admin_can_reset_user_phone(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/reset-contact", [
            'type' => 'phone',
            'value' => '13800138001',
        ]);

        $response->assertNoContent();
        $this->assertEquals('13800138001', $user->fresh()->phone);
    }

    #[Test]
    #[TestDox('回收站列表只返回已软删除的用户')]
    public function admin_can_view_trash_users(): void
    {
        User::factory()->count(2)->create();
        $deletedUser = User::factory()->create(['username' => 'deleted_user']);
        $deletedUser->delete();

        $response = $this->actingAsAdmin()->getJson('/admin/users/trash');
        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.username', 'deleted_user')
            ->assertJsonPath('data.0.deleted_at', fn ($v) => ! is_null($v));
    }

    #[Test]
    #[TestDox('回收站列表支持关键字搜索')]
    public function admin_can_search_trash_users_by_keyword(): void
    {
        $deletedUser = User::factory()->create(['username' => 'will_restore', 'name' => '待恢复']);
        $deletedUser->delete();
        $otherDeleted = User::factory()->create(['username' => 'other_deleted']);
        $otherDeleted->delete();

        $response = $this->actingAsAdmin()->getJson('/admin/users/trash?keyword=will_restore');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'will_restore');
    }

    #[Test]
    #[TestDox('恢复软删除的用户')]
    public function admin_can_restore_soft_deleted_user(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $response = $this->actingAsAdmin()->putJson("/admin/users/{$user->id}/restore");
        $response->assertNoContent();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    #[TestDox('彻底删除用户不可恢复')]
    public function admin_can_force_delete_user(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $response = $this->actingAsAdmin()->deleteJson("/admin/users/{$user->id}/force-delete");
        $response->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[Test]
    #[TestDox('恢复不存在的用户返回 404')]
    public function restore_non_existent_user_returns_404(): void
    {
        $response = $this->actingAsAdmin()->putJson('/admin/users/99999/restore');
        $response->assertNotFound();
    }

    #[Test]
    #[TestDox('彻底删除不存在的用户返回 404')]
    public function force_delete_non_existent_user_returns_404(): void
    {
        $response = $this->actingAsAdmin()->deleteJson('/admin/users/99999/force-delete');
        $response->assertNotFound();
    }
}
