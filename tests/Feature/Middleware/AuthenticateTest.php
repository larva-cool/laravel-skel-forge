<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Enums\System\AdminStatus;
use App\Enums\User\UserStatus;
use App\Http\Middleware\Authenticate;
use App\Models\Admin\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

#[CoversClass(Authenticate::class)]
#[Group('account-status')]
class AuthenticateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('正常用户的 Token 可以访问受保护接口')]
    public function active_user_token_can_access_protected_route(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['user'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user')
            ->assertOk();

        $this->assertSame(1, $user->tokens()->count());
    }

    #[Test]
    #[TestDox('正常管理员的 Token 可以访问受保护接口')]
    public function active_admin_token_can_access_protected_route(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/admin/auth/info')
            ->assertOk();

        $this->assertSame(1, $admin->tokens()->count());
    }

    #[Test]
    #[TestDox('直接冻结用户后旧 Token 返回 401 并全部撤销')]
    public function frozen_user_tokens_are_revoked_and_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('device1', ['user'])->plainTextToken;
        $user->createToken('device2', ['user']);

        DB::table('users')->where('id', $user->id)->update([
            'status' => UserStatus::FROZEN->value,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();

        $this->assertSame(0, $user->tokens()->count());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    #[Test]
    #[TestDox('直接禁用管理员后旧 Token 返回 401 并全部撤销')]
    public function disabled_admin_tokens_are_revoked_and_rejected(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('device1', ['admin'])->plainTextToken;
        $admin->createToken('device2', ['admin']);

        DB::table('admin_users')->where('id', $admin->id)->update([
            'status' => AdminStatus::DISABLED->value,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/admin/auth/info')
            ->assertUnauthorized();

        $this->assertSame(0, $admin->tokens()->count());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/admin/auth/info')
            ->assertUnauthorized();
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'username' => 'middleware_admin',
            'name' => 'Middleware Admin',
            'password' => Hash::make('Password123!'),
            'status' => AdminStatus::ACTIVE,
        ]);
    }
}
