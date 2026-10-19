<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\System;

use App\Ai\Tools\System\AddSensitiveWord;
use App\Enums\AdminStatus;
use App\Enums\SensitiveCategory;
use App\Enums\SensitiveLevel;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\System\SensitiveWord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 敏感词新增工具测试
 */
#[CoversClass(AddSensitiveWord::class)]
#[Group('ai')]
#[Group('sensitive-word')]
class AddSensitiveWordTest extends TestCase
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
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new AddSensitiveWord)->handle(new Request(['word' => '测试词']));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 sensitive-words.create 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['sensitive-words.index']);

        $result = (new AddSensitiveWord)->forAdmin($admin)->handle(new Request(['word' => '测试词']));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertSame(0, SensitiveWord::query()->where('word', '测试词')->count());
    }

    #[Test]
    #[TestDox('新增敏感词后写入指定分类与级别')]
    public function handle_creates_word_with_category_and_level(): void
    {
        $admin = $this->createAdminWithPermissions(['sensitive-words.create']);

        $result = (new AddSensitiveWord)->forAdmin($admin)->handle(new Request([
            'word' => '违规广告词',
            'category' => 'ad',
            'level' => 'block',
        ]));

        $word = SensitiveWord::query()->where('word', '违规广告词')->first();
        $this->assertNotNull($word);
        $this->assertSame(SensitiveCategory::AD, $word->category);
        $this->assertSame(SensitiveLevel::BLOCK, $word->level);
        $this->assertStringContainsString('已新增敏感词', (string) $result);
    }

    #[Test]
    #[TestDox('未指定分类与级别时使用默认值')]
    public function handle_uses_defaults_when_options_omitted(): void
    {
        $admin = $this->createAdminWithPermissions(['sensitive-words.create']);

        (new AddSensitiveWord)->forAdmin($admin)->handle(new Request(['word' => '普通敏感词']));

        $word = SensitiveWord::query()->where('word', '普通敏感词')->first();
        $this->assertSame(SensitiveCategory::OTHER, $word->category);
        $this->assertSame(SensitiveLevel::REVIEW, $word->level);
        $this->assertSame('*', $word->replacement);
    }

    #[Test]
    #[TestDox('重复添加相同词语时提示已存在')]
    public function handle_skips_duplicated_word(): void
    {
        $admin = $this->createAdminWithPermissions(['sensitive-words.create']);
        SensitiveWord::factory()->create(['word' => '已存在的词']);

        $result = (new AddSensitiveWord)->forAdmin($admin)->handle(new Request(['word' => '已存在的词']));

        $this->assertStringContainsString('已存在', (string) $result);
        $this->assertSame(1, SensitiveWord::query()->where('word', '已存在的词')->count());
    }
}
