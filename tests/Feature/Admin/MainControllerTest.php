<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\MainController;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台主控制器功能测试
 */
#[CoversClass(MainController::class)]
#[Group('admin')]
#[Group('main')]
class MainControllerTest extends TestCase
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
    #[TestDox('未登录访问前端路由配置返回 401')]
    public function guest_cannot_get_routes(): void
    {
        $this->getJson('/admin/routes')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取前端路由配置返回 200 与数组')]
    public function admin_can_get_routes(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/routes');

        $response->assertOk()
            ->assertJsonIsArray();
    }

    #[Test]
    #[TestDox('禁用菜单不出现在路由配置中')]
    public function disabled_menus_not_in_routes(): void
    {
        // 初始数据中已有启用的菜单
        $response = $this->actingAsAdmin()->getJson('/admin/routes');
        $enabledCount = count($response->json());

        // 创建一个禁用的菜单，不应出现在路由中
        \App\Models\Admin\AdminMenu::create([
            'title' => '禁用菜单',
            'type' => 1,
            'sort' => 99,
            'is_enable' => false,
            'is_hide' => false,
            'is_hide_tab' => false,
            'is_iframe' => false,
            'keep_alive' => false,
            'is_full_page' => false,
            'fixed_tab' => false,
            'show_badge' => false,
        ]);

        $response = $this->actingAsAdmin()->getJson('/admin/routes');
        $response->assertOk();
        $this->assertCount($enabledCount, $response->json());
    }

    #[Test]
    #[TestDox('未登录用户无法清理缓存')]
    public function guest_cannot_clear_cache(): void
    {
        $this->postJson('/admin/cache/clear')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员清理全部缓存成功')]
    public function admin_can_clear_all_cache(): void
    {
        Cache::put('test:key', 'value', 60);

        $response = $this->actingAsAdmin()->postJson('/admin/cache/clear');

        $response->assertOk()
            ->assertJson([
                'message' => '缓存清理成功',
                'types' => ['all'],
            ]);
        $this->assertNull(Cache::get('test:key'));
    }

    #[Test]
    #[TestDox('管理员按类型清理 settings 缓存')]
    public function admin_can_clear_settings_cache(): void
    {
        Cache::put('test:key', 'value', 60);
        // 预热 settings 缓存
        settings()->all();
        $this->assertNotNull(Cache::get(\App\Enums\CacheKey::SETTINGS));

        $response = $this->actingAsAdmin()->postJson('/admin/cache/clear', [
            'types' => ['settings'],
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => '缓存清理成功',
                'types' => ['settings'],
            ]);
        // settings 缓存已清理
        $this->assertNull(Cache::get(\App\Enums\CacheKey::SETTINGS));
        // 其他缓存仍在
        $this->assertSame('value', Cache::get('test:key'));
    }
}
