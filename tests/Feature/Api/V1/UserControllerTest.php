<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\UserController;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * API V1 用户控制器功能测试
 */
#[CoversClass(UserController::class)]
#[Group('api')]
#[Group('user')]
class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $password = 'password123';

    /** @var string 测试环境下验证码规则固定的验证码 */
    private string $fixedVerifyCode = '123456';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'username' => 'testuser',
            'email' => 'testuser@example.com',
            'phone' => '13800000000',
            'password' => Hash::make($this->password),
        ]);
    }

    /**
     * 以当前用户身份发起认证请求
     */
    private function actingAsUser(): static
    {
        return $this->withHeader(
            'Authorization',
            'Bearer '.$this->user->createToken('test', ['user'])->plainTextToken
        );
    }

    #[Test]
    #[TestDox('获取基本资料返回完整字段')]
    public function authenticated_user_can_get_base_profile(): void
    {
        $this->actingAsUser()
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('id', $this->user->id)
            ->assertJsonPath('username', 'testuser')
            ->assertJsonStructure([
                'id', 'username', 'email', 'phone', 'name', 'avatar',
                'is_vip', 'is_password', 'available_points', 'available_coins',
                'gender', 'birthday', 'register_time',
            ]);
    }

    #[Test]
    #[TestDox('修改个人资料成功并落库')]
    public function authenticated_user_can_modify_profile(): void
    {
        $this->actingAsUser()
            ->postJson('/api/v1/user/profile', [
                'name' => '新昵称',
                'bio' => '个性签名',
                'website' => 'https://example.com',
            ])
            ->assertOk()
            ->assertJsonPath('name', '新昵称')
            ->assertJsonPath('bio', '个性签名');

        $this->assertSame('新昵称', $this->user->fresh()->name);
    }

    #[Test]
    #[TestDox('修改个人资料网址格式非法返回 422')]
    public function modify_profile_with_invalid_website(): void
    {
        $this->actingAsUser()
            ->postJson('/api/v1/user/profile', ['website' => 'not-a-url'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['website']);
    }

    #[Test]
    #[TestDox('修改头像成功并转换为 webp 落盘')]
    public function authenticated_user_can_modify_avatar(): void
    {
        Storage::fake('public');
        settings()->set('upload.storage', 'public');

        $paddedId = str_pad((string) $this->user->id, 9, '0', STR_PAD_LEFT);
        $expectedPath = 'avatar/'.substr($paddedId, 0, 3).'/'.substr($paddedId, 3, 4).'/'.$this->user->id.'.webp';

        $this->actingAsUser()
            ->post('/api/v1/user/avatar', [
                'avatar' => UploadedFile::fake()->image('avatar.jpg', 500, 500),
            ])
            ->assertOk()
            ->assertJsonPath('message', __('user.avatar_modification_completed'))
            ->assertJsonPath('path', $expectedPath)
            ->assertJsonPath('avatar', $expectedPath);

        Storage::disk('public')->assertExists($expectedPath);
        // RIFF 为 webp 文件头，证明图片已完成格式转换
        $this->assertSame('RIFF', substr(Storage::disk('public')->get($expectedPath), 0, 4));
        $this->assertSame($expectedPath, $this->user->fresh()->avatar);
        $this->assertDatabaseHas('attachments', [
            'path' => $expectedPath,
            'uploader_id' => $this->user->id,
            'mime_type' => 'image/webp',
        ]);
    }

    #[Test]
    #[TestDox('修改头像未上传文件返回 422')]
    public function modify_avatar_without_file(): void
    {
        $this->actingAsUser()
            ->post('/api/v1/user/avatar')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);

        $this->assertNull($this->user->fresh()->avatar);
    }

    #[Test]
    #[TestDox('修改头像上传非图片文件返回 422')]
    public function modify_avatar_with_non_image_file(): void
    {
        $this->actingAsUser()
            ->post('/api/v1/user/avatar', [
                'avatar' => UploadedFile::fake()->create('avatar.txt', 100, 'text/plain'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);

        $this->assertNull($this->user->fresh()->avatar);
    }

    #[Test]
    #[TestDox('修改用户名成功并累加修改次数')]
    public function authenticated_user_can_modify_username(): void
    {
        $this->actingAsUser()
            ->postJson('/api/v1/user/username', ['username' => 'newusername'])
            ->assertOk()
            ->assertJsonPath('message', __('user.username_modification_completed'));

        $this->assertSame('newusername', $this->user->fresh()->username);
        $this->assertSame(1, $this->user->fresh()->extra->username_change_count);
    }

    #[Test]
    #[TestDox('修改用户名占用已有账号返回 422')]
    public function modify_username_with_taken_username(): void
    {
        User::factory()->create(['username' => 'occupied']);

        $this->actingAsUser()
            ->postJson('/api/v1/user/username', ['username' => 'occupied'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    #[Test]
    #[TestDox('修改用户名超出允许次数返回 422')]
    public function modify_username_exceeds_allowed_count(): void
    {
        $this->user->extra->update(['username_change_count' => 3]);

        $this->actingAsUser()
            ->postJson('/api/v1/user/username', ['username' => 'anothername'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    #[Test]
    #[TestDox('修改邮箱成功并落库')]
    public function authenticated_user_can_modify_email(): void
    {
        $this->actingAsUser()
            ->postJson('/api/v1/user/email', [
                'email' => 'newmail@example.com',
                'verify_code' => $this->fixedVerifyCode,
            ])
            ->assertOk()
            ->assertJsonPath('message', __('user.email_modification_completed'));

        $this->assertSame('newmail@example.com', $this->user->fresh()->email);
    }

    #[Test]
    #[TestDox('修改邮箱验证码错误返回 422')]
    public function modify_email_with_wrong_verify_code(): void
    {
        $this->actingAsUser()
            ->postJson('/api/v1/user/email', [
                'email' => 'newmail@example.com',
                'verify_code' => '000000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['verify_code']);
    }

    #[Test]
    #[TestDox('修改邮箱占用已有邮箱返回 422')]
    public function modify_email_with_taken_email(): void
    {
        User::factory()->create(['email' => 'occupied@example.com']);

        $this->actingAsUser()
            ->postJson('/api/v1/user/email', [
                'email' => 'occupied@example.com',
                'verify_code' => $this->fixedVerifyCode,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    #[TestDox('修改手机号成功并落库')]
    public function authenticated_user_can_modify_phone(): void
    {
        $this->actingAsUser()
            ->postJson('/api/v1/user/phone', [
                'phone' => '13900000001',
                'verify_code' => $this->fixedVerifyCode,
            ])
            ->assertOk()
            ->assertJsonPath('message', __('user.phone_modification_completed'));

        $this->assertSame('13900000001', $this->user->fresh()->phone);
    }

    #[Test]
    #[TestDox('修改手机号格式非法返回 422')]
    public function modify_phone_with_invalid_format(): void
    {
        $this->actingAsUser()
            ->postJson('/api/v1/user/phone', [
                'phone' => 'invalid',
                'verify_code' => $this->fixedVerifyCode,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    #[Test]
    #[TestDox('修改密码成功且新密码可用')]
    public function authenticated_user_can_modify_password(): void
    {
        $this->actingAsUser()
            ->postJson('/api/v1/user/password', [
                'old_password' => $this->password,
                'password' => 'BrandNewSecret2026',
            ])
            ->assertOk()
            ->assertJsonPath('message', __('user.password_reset_complete'));

        $this->assertTrue(Hash::check('BrandNewSecret2026', $this->user->fresh()->password));
    }

    #[Test]
    #[TestDox('修改密码旧密码错误返回 422')]
    public function modify_password_with_wrong_old_password(): void
    {
        $this->actingAsUser()
            ->postJson('/api/v1/user/password', [
                'old_password' => 'wrong_password',
                'password' => 'BrandNewSecret2026',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['old_password']);
    }

    #[Test]
    #[TestDox('修改密码新密码过短返回 422')]
    public function modify_password_with_short_new_password(): void
    {
        $this->actingAsUser()
            ->postJson('/api/v1/user/password', [
                'old_password' => $this->password,
                'password' => 'short',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    #[TestDox('发送验证邮件返回成功提示并派发通知')]
    public function authenticated_user_can_send_verification_mail(): void
    {
        // 隔离邮件渲染：验证链接依赖 verification.verify 命名路由，此处只验证通知已派发
        Notification::fake();

        $this->actingAsUser()
            ->postJson('/api/v1/user/verification-mail')
            ->assertOk()
            ->assertJsonPath('message', __('user.send_complete'));

        Notification::assertSentTo($this->user, VerifyEmail::class);
    }

    #[Test]
    #[TestDox('获取登录历史返回分页结构')]
    public function authenticated_user_can_list_login_histories(): void
    {
        $this->actingAsUser()
            ->getJson('/api/v1/user/login-histories')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    #[TestDox('获取积分记录返回分页结构')]
    public function authenticated_user_can_list_points(): void
    {
        $this->actingAsUser()
            ->getJson('/api/v1/user/points')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    #[TestDox('获取金币记录返回分页结构')]
    public function authenticated_user_can_list_coins(): void
    {
        $this->actingAsUser()
            ->getJson('/api/v1/user/coins')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    #[TestDox('注销账户返回 204 且软删除')]
    public function authenticated_user_can_destroy_account(): void
    {
        $userId = $this->user->id;

        $this->actingAsUser()
            ->deleteJson('/api/v1/user')
            ->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $userId]);
    }

    /**
     * 需要认证的接口清单
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestForbiddenRouteProvider(): array
    {
        return [
            '获取基本资料' => ['getJson', '/api/v1/user'],
            '修改个人资料' => ['postJson', '/api/v1/user/profile'],
            '修改用户名' => ['postJson', '/api/v1/user/username'],
            '修改邮箱' => ['postJson', '/api/v1/user/email'],
            '修改手机号' => ['postJson', '/api/v1/user/phone'],
            '修改头像' => ['postJson', '/api/v1/user/avatar'],
            '修改密码' => ['postJson', '/api/v1/user/password'],
            '发送验证邮件' => ['postJson', '/api/v1/user/verification-mail'],
            '获取登录历史' => ['getJson', '/api/v1/user/login-histories'],
            '获取积分记录' => ['getJson', '/api/v1/user/points'],
            '获取金币记录' => ['getJson', '/api/v1/user/coins'],
            '注销账户' => ['deleteJson', '/api/v1/user'],
        ];
    }

    #[Test]
    #[TestDox('未认证访问用户接口返回 401')]
    #[DataProvider('guestForbiddenRouteProvider')]
    public function guest_cannot_access_user_endpoints(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertUnauthorized();
    }
}
