<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Segments;

use App\Ai\Tools\Segments\CreateUserSegment;
use App\Enums\AdminStatus;
use App\Enums\SegmentType;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\UserSegment\Segment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 用户分群创建工具测试
 */
#[CoversClass(CreateUserSegment::class)]
#[Group('ai')]
#[Group('segment')]
class CreateUserSegmentTest extends TestCase
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

    /**
     * 合法的规则定义
     *
     * @return array<string, mixed>
     */
    private function validRule(): array
    {
        return [
            'logic' => 'and',
            'conditions' => [
                ['field' => 'status', 'op' => '=', 'value' => 1],
            ],
        ];
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new CreateUserSegment)->handle(new Request(['name' => '测试分群', 'type' => 1]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 segments.create 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.index']);

        $result = (new CreateUserSegment)->forAdmin($admin)->handle(new Request(['name' => '测试分群', 'type' => 1]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
        $this->assertSame(0, Segment::query()->count());
    }

    #[Test]
    #[TestDox('成功创建手动分群')]
    public function handle_creates_manual_segment(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.create']);

        $result = (new CreateUserSegment)->forAdmin($admin)->handle(new Request([
            'name' => '高价值用户',
            'type' => SegmentType::MANUAL->value,
            'group' => '运营分群',
            'description' => '手动维护的高价值用户',
        ]));

        $this->assertStringContainsString('已创建手动分群「高价值用户」', (string) $result);

        $segment = Segment::query()->where('name', '高价值用户')->first();
        $this->assertNotNull($segment);
        $this->assertSame(SegmentType::MANUAL, $segment->type);
        $this->assertNull($segment->code);
        $this->assertTrue($segment->status);
        $this->assertSame(1000, $segment->sort);
    }

    #[Test]
    #[TestDox('成功创建规则分群并写回规则定义')]
    public function handle_creates_rule_segment(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.create']);

        $result = (new CreateUserSegment)->forAdmin($admin)->handle(new Request([
            'name' => '活跃用户',
            'type' => SegmentType::RULE->value,
            'code' => 'active_users',
            'rule' => $this->validRule(),
        ]));

        $this->assertStringContainsString('已创建规则分群「活跃用户」', (string) $result);
        $this->assertStringContainsString('SyncUserSegment', (string) $result);

        $segment = Segment::query()->where('code', 'active_users')->first();
        $this->assertNotNull($segment);
        $this->assertSame(SegmentType::RULE, $segment->type);
        $this->assertSame(1, $segment->rule['version']);
        $this->assertSame('and', $segment->rule['logic']);
        $this->assertCount(1, $segment->rule['conditions']);
    }

    #[Test]
    #[TestDox('分群名称为空时拒绝创建')]
    public function handle_rejects_blank_name(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.create']);

        $result = (new CreateUserSegment)->forAdmin($admin)->handle(new Request(['name' => '  ', 'type' => 1]));

        $this->assertStringContainsString('分群名称不能为空', (string) $result);
        $this->assertSame(0, Segment::query()->count());
    }

    #[Test]
    #[TestDox('规则分群缺少 code 时拒绝创建')]
    public function handle_rejects_rule_segment_without_code(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.create']);

        $result = (new CreateUserSegment)->forAdmin($admin)->handle(new Request([
            'name' => '活跃用户',
            'type' => SegmentType::RULE->value,
            'rule' => $this->validRule(),
        ]));

        $this->assertStringContainsString('规则分群必须提供分群编码', (string) $result);
        $this->assertSame(0, Segment::query()->count());
    }

    #[Test]
    #[TestDox('code 格式非法时拒绝创建')]
    public function handle_rejects_invalid_code_format(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.create']);

        $result = (new CreateUserSegment)->forAdmin($admin)->handle(new Request([
            'name' => '活跃用户',
            'type' => SegmentType::RULE->value,
            'code' => 'active-users!',
            'rule' => $this->validRule(),
        ]));

        $this->assertStringContainsString('分群编码仅支持字母、数字、下划线', (string) $result);
        $this->assertSame(0, Segment::query()->count());
    }

    #[Test]
    #[TestDox('code 已被占用时拒绝创建')]
    public function handle_rejects_duplicate_code(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.create']);
        Segment::factory()->rule()->create(['code' => 'active_users']);

        $result = (new CreateUserSegment)->forAdmin($admin)->handle(new Request([
            'name' => '活跃用户',
            'type' => SegmentType::RULE->value,
            'code' => 'active_users',
            'rule' => $this->validRule(),
        ]));

        $this->assertStringContainsString('已被占用', (string) $result);
        $this->assertSame(1, Segment::query()->count());
    }

    #[Test]
    #[TestDox('规则分群缺少 rule 时拒绝创建')]
    public function handle_rejects_rule_segment_without_rule(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.create']);

        $result = (new CreateUserSegment)->forAdmin($admin)->handle(new Request([
            'name' => '活跃用户',
            'type' => SegmentType::RULE->value,
            'code' => 'active_users',
        ]));

        $this->assertStringContainsString('规则分群必须提供 rule 规则定义', (string) $result);
        $this->assertSame(0, Segment::query()->count());
    }

    #[Test]
    #[TestDox('rule 结构非法时拒绝创建')]
    public function handle_rejects_invalid_rule(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.create']);

        $result = (new CreateUserSegment)->forAdmin($admin)->handle(new Request([
            'name' => '活跃用户',
            'type' => SegmentType::RULE->value,
            'code' => 'active_users',
            'rule' => [
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'not_exists_field', 'op' => '=', 'value' => 1],
                ],
            ],
        ]));

        $this->assertStringContainsString('规则定义非法', (string) $result);
        $this->assertSame(0, Segment::query()->count());
    }

    #[Test]
    #[TestDox('非法标签样式回退为 default')]
    public function handle_falls_back_to_default_label_type(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.create']);

        (new CreateUserSegment)->forAdmin($admin)->handle(new Request([
            'name' => '测试分群',
            'type' => SegmentType::MANUAL->value,
            'label_type' => 'neon',
        ]));

        $segment = Segment::query()->where('name', '测试分群')->first();
        $this->assertNotNull($segment);
        $this->assertSame('default', $segment->label_type);
    }
}
