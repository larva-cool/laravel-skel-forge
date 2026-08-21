<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\UserHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * UserHelper 单元测试
 */
#[CoversClass(UserHelper::class)]
#[Group('support')]
class UserHelperTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('findForAccount 空字符串返回 null')]
    public function find_for_account_empty_returns_null(): void
    {
        $this->assertNull(UserHelper::findForAccount(''));
    }

    #[Test]
    #[TestDox('findForAccount 纯空格返回 null')]
    public function find_for_account_whitespace_returns_null(): void
    {
        $this->assertNull(UserHelper::findForAccount('   '));
    }

    #[Test]
    #[TestDox('findForAccount 按用户名查找')]
    public function find_for_account_by_username(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $result = UserHelper::findForAccount('testuser');

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
    }

    #[Test]
    #[TestDox('findForAccount 按邮箱查找')]
    public function find_for_account_by_email(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $result = UserHelper::findForAccount('test@example.com');

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
    }

    #[Test]
    #[TestDox('findForAccount 按手机号查找')]
    public function find_for_account_by_phone(): void
    {
        $user = User::factory()->create(['phone' => '13800000001']);

        $result = UserHelper::findForAccount('13800000001');

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
    }

    #[Test]
    #[TestDox('findForAccount 不存在的用户返回 null')]
    public function find_for_account_not_found(): void
    {
        $this->assertNull(UserHelper::findForAccount('nonexistent_user'));
    }

    #[Test]
    #[TestDox('findForAccount 用户名前后有空格也能找到')]
    public function find_for_account_trims_input(): void
    {
        $user = User::factory()->create(['username' => 'trimuser']);

        $result = UserHelper::findForAccount('  trimuser  ');

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
    }

    #[Test]
    #[TestDox('findOrCreatePhone 用户不存在时自动创建')]
    public function find_or_create_phone_creates_new_user(): void
    {
        $phone = '13900000001';

        $user = UserHelper::findOrCreatePhone($phone);

        $this->assertNotNull($user);
        $this->assertSame($phone, $user->phone);
        $this->assertDatabaseHas('users', ['phone' => $phone]);
    }

    #[Test]
    #[TestDox('findOrCreatePhone 用户已存在时返回现有用户')]
    public function find_or_create_phone_returns_existing(): void
    {
        $phone = '13900000002';
        $existing = User::factory()->create(['phone' => $phone]);

        $user = UserHelper::findOrCreatePhone($phone);

        $this->assertSame($existing->id, $user->id);
    }

    #[Test]
    #[TestDox('findOrCreatePhone 软删除用户返回 null')]
    public function find_or_create_phone_trashed_returns_null(): void
    {
        $phone = '13900000003';
        $user = User::factory()->create(['phone' => $phone]);
        $user->delete();

        $result = UserHelper::findOrCreatePhone($phone);

        $this->assertNull($result);
    }

    #[Test]
    #[TestDox('generateUsername 无冲突时返回原始用户名')]
    public function generate_username_no_conflict(): void
    {
        $username = UserHelper::generateUsername('newuser');

        $this->assertSame('newuser', $username);
    }

    #[Test]
    #[TestDox('generateUsername 有冲突时添加数字后缀')]
    public function generate_username_with_conflict(): void
    {
        User::factory()->create(['username' => 'conflict']);

        $username = UserHelper::generateUsername('conflict');

        $this->assertSame('conflict1', $username);
    }

    #[Test]
    #[TestDox('generateUsername 多个冲突时递增后缀')]
    public function generate_username_multiple_conflicts(): void
    {
        User::factory()->create(['username' => 'multi']);
        User::factory()->create(['username' => 'multi1']);
        User::factory()->create(['username' => 'multi2']);

        $username = UserHelper::generateUsername('multi');

        $this->assertSame('multi3', $username);
    }

    #[Test]
    #[TestDox('generateUsername 软删除用户名也算冲突')]
    public function generate_username_considers_trashed(): void
    {
        $user = User::factory()->create(['username' => 'trashed']);
        $user->delete();

        $username = UserHelper::generateUsername('trashed');

        $this->assertSame('trashed1', $username);
    }
}
