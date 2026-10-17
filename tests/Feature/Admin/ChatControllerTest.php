<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AI 聊天控制器权限测试
 *
 * 验证 chat.use 权限中间件对 AI 助手入口的放行与拦截。
 */
#[CoversClass(\App\Http\Controllers\Admin\ChatController::class)]
#[Group('admin')]
#[Group('permission')]
class ChatControllerTest extends TestCase
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

    #[Test]
    #[TestDox('未登录访问 AI 助手接口返回 401')]
    public function guest_gets_unauthorized(): void
    {
        $this->getJson('/admin/chat/conversations')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('缺少 chat.use 权限时访问 AI 助手接口返回 403')]
    public function admin_without_chat_use_gets_forbidden(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $this->actingAs($admin, 'admin')
            ->getJson('/admin/chat/conversations')
            ->assertForbidden();
    }

    #[Test]
    #[TestDox('拥有 chat.use 权限时可访问会话列表')]
    public function admin_with_chat_use_can_access_conversations(): void
    {
        $admin = $this->createAdminWithPermissions(['chat.use']);

        $this->actingAs($admin, 'admin')
            ->getJson('/admin/chat/conversations')
            ->assertOk();
    }

    #[Test]
    #[TestDox('超级管理员可访问会话列表')]
    public function super_admin_can_access_conversations(): void
    {
        $superAdmin = Admin::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($superAdmin, 'admin')
            ->getJson('/admin/chat/conversations')
            ->assertOk();
    }
}
