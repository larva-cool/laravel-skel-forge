<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\MailCodeController;
use App\Models\Admin\Admin;
use App\Models\System\MailCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台邮件验证码管理控制器功能测试
 */
#[CoversClass(MailCodeController::class)]
#[Group('admin')]
#[Group('mail-code')]
class MailCodeControllerTest extends TestCase
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
    #[TestDox('未登录访问邮件验证码列表返回 401')]
    public function guest_cannot_list_mail_codes(): void
    {
        $this->getJson('/admin/mail-codes')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取邮件验证码列表返回 200 与分页数据')]
    public function admin_can_list_mail_codes(): void
    {
        MailCode::create(['email' => 'test@example.com', 'code' => '123456', 'ip' => '127.0.0.1']);
        MailCode::create(['email' => 'test2@example.com', 'code' => '654321', 'ip' => '127.0.0.2']);

        $response = $this->actingAsAdmin()->getJson('/admin/mail-codes');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    #[TestDox('按邮箱搜索邮件验证码')]
    public function admin_can_search_mail_codes_by_email(): void
    {
        MailCode::create(['email' => 'alice@example.com', 'code' => '111111', 'ip' => '127.0.0.1']);
        MailCode::create(['email' => 'bob@example.com', 'code' => '222222', 'ip' => '127.0.0.2']);

        $response = $this->actingAsAdmin()->getJson('/admin/mail-codes?email=alice');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'alice@example.com');
    }

    #[Test]
    #[TestDox('按状态过滤邮件验证码')]
    public function admin_can_filter_mail_codes_by_state(): void
    {
        MailCode::create(['email' => 'unused@example.com', 'code' => '111111', 'ip' => '127.0.0.1', 'state' => 0]);
        MailCode::create(['email' => 'used@example.com', 'code' => '222222', 'ip' => '127.0.0.2', 'state' => 1]);

        $response = $this->actingAsAdmin()->getJson('/admin/mail-codes?state=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'used@example.com');
    }

    #[Test]
    #[TestDox('空数据库返回空列表')]
    public function empty_mail_code_list(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/mail-codes');
        $response->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    #[Test]
    #[TestDox('获取邮件验证码详情')]
    public function admin_can_view_mail_code(): void
    {
        $code = MailCode::create(['email' => 'detail@example.com', 'code' => '999999', 'ip' => '10.0.0.1']);

        $response = $this->actingAsAdmin()->getJson("/admin/mail-codes/{$code->id}");
        $response->assertOk()
            ->assertJsonPath('id', $code->id)
            ->assertJsonPath('email', 'detail@example.com')
            ->assertJsonPath('code', '999999');
    }

    #[Test]
    #[TestDox('获取不存在的邮件验证码返回 404')]
    public function view_nonexistent_mail_code_returns_404(): void
    {
        $this->actingAsAdmin()->getJson('/admin/mail-codes/99999')->assertNotFound();
    }

    #[Test]
    #[TestDox('邮件验证码按发送时间倒序排列')]
    public function mail_codes_ordered_by_send_at_desc(): void
    {
        $old = MailCode::create(['email' => 'old@example.com', 'code' => '111', 'ip' => '127.0.0.1']);
        $new = MailCode::create(['email' => 'new@example.com', 'code' => '222', 'ip' => '127.0.0.1']);

        $response = $this->actingAsAdmin()->getJson('/admin/mail-codes');
        $response->assertOk()
            ->assertJsonPath('data.0.email', 'new@example.com')
            ->assertJsonPath('data.1.email', 'old@example.com');
    }
}
