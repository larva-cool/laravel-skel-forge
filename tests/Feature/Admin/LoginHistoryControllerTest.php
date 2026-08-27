<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminStatus;
use App\Http\Controllers\Admin\LoginHistoryController;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\System\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 后台全局登录历史控制器功能测试
 */
#[CoversClass(LoginHistoryController::class)]
#[Group('admin')]
#[Group('login-history')]
class LoginHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
        $this->user = User::factory()->create(['name' => '测试用户']);
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 创建一条指定归属主体的登录历史记录
     */
    private function createHistory(string $type, int $userId, array $overrides = []): LoginHistory
    {
        return LoginHistory::factory()->create(array_merge([
            'user_id' => $userId,
            'user_type' => $type,
        ], $overrides));
    }

    #[Test]
    #[TestDox('未登录访问全局登录历史列表返回 401')]
    public function guest_cannot_list_login_histories(): void
    {
        $this->getJson('/admin/login-histories')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('无权限管理员访问全局登录历史列表返回 403')]
    public function admin_without_permission_cannot_list_login_histories(): void
    {
        $admin = Admin::query()->create([
            'username' => 'viewer',
            'name' => '受限管理员',
            'email' => 'viewer@example.com',
            'password' => bcrypt('12345678'),
            'status' => AdminStatus::ACTIVE,
        ]);
        $role = Role::create(['name' => 'viewer', 'display_name' => '受限角色', 'guard_name' => AdminMenu::GUARD_NAME]);
        $admin->assignRole($role);

        $this->actingAs($admin, 'admin')->getJson('/admin/login-histories')->assertForbidden();
    }

    #[Test]
    #[TestDox('管理员可查看全局登录历史列表且包含归属主体与结果字段')]
    public function admin_can_list_login_histories(): void
    {
        $this->createHistory('user', $this->user->id);
        $this->createHistory('admin', $this->admin->id);

        $this->actingAsAdmin()->getJson('/admin/login-histories')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'guard', 'user_id', 'user_name', 'result', 'ip', 'login_at']]])
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    #[TestDox('按 result 过滤登录历史')]
    public function admin_can_filter_login_histories_by_result(): void
    {
        $this->createHistory('user', $this->user->id, ['result' => true]);
        $this->createHistory('user', $this->user->id, ['result' => false]);

        $this->actingAsAdmin()->getJson('/admin/login-histories?result=0')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.result', false);
    }

    #[Test]
    #[TestDox('按 guard 过滤登录历史')]
    public function admin_can_filter_login_histories_by_guard(): void
    {
        $this->createHistory('user', $this->user->id);
        $this->createHistory('admin', $this->admin->id);

        $this->actingAsAdmin()->getJson('/admin/login-histories?guard=admin')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.guard', 'admin');
    }

    #[Test]
    #[TestDox('按 user_id 过滤登录历史')]
    public function admin_can_filter_login_histories_by_user_id(): void
    {
        $other = User::factory()->create();

        $this->createHistory('user', $this->user->id);
        $this->createHistory('user', $other->id);

        $this->actingAsAdmin()->getJson('/admin/login-histories?guard=user&user_id='.$this->user->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.user_id', $this->user->id);
    }

    #[Test]
    #[TestDox('按登录时间范围过滤登录历史')]
    public function admin_can_filter_login_histories_by_date_range(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 27, 12, 0, 0));

        $this->createHistory('user', $this->user->id, ['login_at' => Carbon::create(2026, 8, 26, 10, 0, 0)]);
        $this->createHistory('user', $this->user->id, ['login_at' => Carbon::create(2026, 8, 10, 10, 0, 0)]);

        $this->actingAsAdmin()->getJson('/admin/login-histories?login_start=2026-08-26&login_end=2026-08-27')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        Carbon::setTestNow();
    }

    #[Test]
    #[TestDox('按关键字过滤登录历史')]
    public function admin_can_filter_login_histories_by_keyword(): void
    {
        $this->createHistory('user', $this->user->id, ['device' => 'iPhone', 'address' => '北京']);
        $this->createHistory('user', $this->user->id, ['device' => 'PC', 'address' => '上海']);

        $this->actingAsAdmin()->getJson('/admin/login-histories?keyword='.urlencode('北京'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.address', '北京');
    }

    #[Test]
    #[TestDox('按 IP 精确过滤登录历史')]
    public function admin_can_filter_login_histories_by_ip(): void
    {
        $this->createHistory('user', $this->user->id, ['ip' => '192.168.1.100']);

        $this->actingAsAdmin()->getJson('/admin/login-histories?ip=192.168.1.100')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('非法 guard 参数返回 422')]
    public function invalid_guard_returns_validation_error(): void
    {
        $this->actingAsAdmin()->getJson('/admin/login-histories?guard=invalid')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['guard']]);
    }
}
