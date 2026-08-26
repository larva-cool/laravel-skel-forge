<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\CommonController;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台公共接口控制器功能测试
 */
#[CoversClass(CommonController::class)]
#[Group('admin')]
#[Group('common')]
class CommonControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    #[TestDox('未登录访问公共接口返回 401')]
    public function guest_cannot_access_common_endpoints(): void
    {
        $this->getJson('/admin/common/source-types')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可获取源类型选项')]
    public function admin_can_get_source_types(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/common/source-types');

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        foreach ($data as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('value', $item);
        }
        // 至少包含 user 类型
        $values = array_column($data, 'value');
        $this->assertContains('user', $values);
    }
}
