<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\WechatController;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台微信公众号配置控制器功能测试
 */
#[CoversClass(WechatController::class)]
#[Group('admin')]
#[Group('wechat')]
class WechatControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    #[Test]
    #[TestDox('未登录时无法获取微信公众号配置')]
    public function guest_cannot_get_wechat_config(): void
    {
        $this->getJson('/admin/wechat')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可以获取微信公众号只读配置')]
    public function admin_can_get_wechat_config(): void
    {
        config([
            'services.wechat.app_id' => 'wx-demo-app-id',
            'services.wechat.app_secret' => 'demo-app-secret',
            'services.wechat.token' => 'demo-token',
            'services.wechat.aes_key' => 'demo-aes-key',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/wechat')
            ->assertOk()
            ->assertJson([
                'app_id' => 'wx-demo-app-id',
                'app_secret' => 'demo-app-secret',
                'token' => 'demo-token',
                'aes_key' => 'demo-aes-key',
            ])
            ->assertJsonPath('server_url', route('wechat.serve'));
    }

    #[Test]
    #[TestDox('未配置时返回空字符串而非 null')]
    public function admin_gets_empty_strings_when_wechat_not_configured(): void
    {
        config([
            'services.wechat.app_id' => null,
            'services.wechat.app_secret' => null,
            'services.wechat.token' => null,
            'services.wechat.aes_key' => null,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/wechat')
            ->assertOk()
            ->assertJson([
                'app_id' => '',
                'app_secret' => '',
                'token' => '',
                'aes_key' => '',
            ]);
    }
}
