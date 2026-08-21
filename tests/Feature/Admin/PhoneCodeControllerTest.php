<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\PhoneCodeController;
use App\Models\Admin\Admin;
use App\Models\System\PhoneCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台手机验证码管理控制器功能测试
 */
#[CoversClass(PhoneCodeController::class)]
#[Group('admin')]
#[Group('phone-code')]
class PhoneCodeControllerTest extends TestCase
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
    #[TestDox('未登录访问手机验证码列表返回 401')]
    public function guest_cannot_list_phone_codes(): void
    {
        $this->getJson('/admin/phone-codes')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取手机验证码列表返回 200 与分页数据')]
    public function admin_can_list_phone_codes(): void
    {
        PhoneCode::create(['phone' => '13800000001', 'code' => '123456', 'ip' => '127.0.0.1', 'scene' => 'login']);
        PhoneCode::create(['phone' => '13800000002', 'code' => '654321', 'ip' => '127.0.0.2', 'scene' => 'register']);

        $response = $this->actingAsAdmin()->getJson('/admin/phone-codes');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    #[TestDox('按手机号搜索验证码')]
    public function admin_can_search_phone_codes_by_phone(): void
    {
        PhoneCode::create(['phone' => '13900000001', 'code' => '111111', 'ip' => '127.0.0.1', 'scene' => 'login']);
        PhoneCode::create(['phone' => '13800000002', 'code' => '222222', 'ip' => '127.0.0.2', 'scene' => 'login']);

        $response = $this->actingAsAdmin()->getJson('/admin/phone-codes?phone=139');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.phone', '13900000001');
    }

    #[Test]
    #[TestDox('按场景过滤验证码')]
    public function admin_can_filter_phone_codes_by_scene(): void
    {
        PhoneCode::create(['phone' => '13800000001', 'code' => '111111', 'ip' => '127.0.0.1', 'scene' => 'login']);
        PhoneCode::create(['phone' => '13800000002', 'code' => '222222', 'ip' => '127.0.0.2', 'scene' => 'register']);

        $response = $this->actingAsAdmin()->getJson('/admin/phone-codes?scene=register');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.scene', 'register');
    }

    #[Test]
    #[TestDox('按状态过滤验证码')]
    public function admin_can_filter_phone_codes_by_state(): void
    {
        PhoneCode::create(['phone' => '13800000001', 'code' => '111111', 'ip' => '127.0.0.1', 'state' => 0, 'scene' => 'login']);
        PhoneCode::create(['phone' => '13800000002', 'code' => '222222', 'ip' => '127.0.0.2', 'state' => 1, 'scene' => 'login']);

        $response = $this->actingAsAdmin()->getJson('/admin/phone-codes?state=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.state', 1);
    }

    #[Test]
    #[TestDox('空数据库返回空列表')]
    public function empty_phone_code_list(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/phone-codes');
        $response->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    #[Test]
    #[TestDox('获取手机验证码详情')]
    public function admin_can_view_phone_code(): void
    {
        $code = PhoneCode::create(['phone' => '13800000099', 'code' => '888888', 'ip' => '10.0.0.1', 'scene' => 'login']);

        $response = $this->actingAsAdmin()->getJson("/admin/phone-codes/{$code->id}");
        $response->assertOk()
            ->assertJsonPath('id', $code->id)
            ->assertJsonPath('phone', '13800000099')
            ->assertJsonPath('code', '888888');
    }

    #[Test]
    #[TestDox('获取不存在的手机验证码返回 404')]
    public function view_nonexistent_phone_code_returns_404(): void
    {
        $this->actingAsAdmin()->getJson('/admin/phone-codes/99999')->assertNotFound();
    }

    #[Test]
    #[TestDox('手机验证码按发送时间倒序排列')]
    public function phone_codes_ordered_by_send_at_desc(): void
    {
        $old = PhoneCode::create(['phone' => '13800000001', 'code' => '111', 'ip' => '127.0.0.1', 'scene' => 'login']);
        $new = PhoneCode::create(['phone' => '13800000002', 'code' => '222', 'ip' => '127.0.0.1', 'scene' => 'login']);

        $response = $this->actingAsAdmin()->getJson('/admin/phone-codes');
        $response->assertOk()
            ->assertJsonPath('data.0.phone', '13800000002')
            ->assertJsonPath('data.1.phone', '13800000001');
    }
}
