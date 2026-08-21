<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\SettingInputType;
use App\Http\Controllers\Admin\SettingController;
use App\Models\Admin\Admin;
use App\Models\System\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台配置管理控制器功能测试
 */
#[CoversClass(SettingController::class)]
#[Group('admin')]
#[Group('setting')]
class SettingControllerTest extends TestCase
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
    #[TestDox('未登录访问配置列表返回 401')]
    public function guest_cannot_list_settings(): void
    {
        $this->getJson('/admin/settings')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取配置列表返回 200 与分页数据')]
    public function admin_can_list_settings(): void
    {
        $baseCount = Setting::query()->count();
        Setting::create(['name' => '站点名称', 'key' => 'site.name', 'value' => 'MySite', 'cast_type' => 'string', 'input_type' => 'string']);
        Setting::create(['name' => '每页数量', 'key' => 'page.size', 'value' => '15', 'cast_type' => 'int', 'input_type' => 'int']);

        $response = $this->actingAsAdmin()->getJson('/admin/settings');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', $baseCount + 2);
    }

    #[Test]
    #[TestDox('按关键字搜索配置（名称或 key）')]
    public function admin_can_search_settings_by_keyword(): void
    {
        Setting::create(['name' => '站点名称', 'key' => 'site.name', 'value' => 'MySite', 'cast_type' => 'string', 'input_type' => 'string']);
        Setting::create(['name' => '分页大小', 'key' => 'page.size', 'value' => '15', 'cast_type' => 'int', 'input_type' => 'int']);

        $response = $this->actingAsAdmin()->getJson('/admin/settings?keyword=site');
        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.key', 'site.name');
    }

    #[Test]
    #[TestDox('按配置类型过滤')]
    public function admin_can_filter_settings_by_cast_type(): void
    {
        $intCount = Setting::query()->where('cast_type', 'int')->count();
        Setting::create(['name' => '字符串配置', 'key' => 'str.config', 'value' => 'hello', 'cast_type' => 'string', 'input_type' => 'string']);
        Setting::create(['name' => '整型配置', 'key' => 'int.config', 'value' => '42', 'cast_type' => 'int', 'input_type' => 'int']);

        $response = $this->actingAsAdmin()->getJson('/admin/settings?cast_type=int');
        $response->assertOk()
            ->assertJsonPath('meta.total', $intCount + 1);
    }

    #[Test]
    #[TestDox('创建配置返回 201')]
    public function admin_can_create_setting(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/settings', [
            'name' => '站点标题',
            'key' => 'site.title',
            'value' => 'My Website',
            'cast_type' => 'string',
            'input_type' => 'string',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', '站点标题')
            ->assertJsonPath('key', 'site.title')
            ->assertJsonPath('value', 'My Website');

        $this->assertDatabaseHas('settings', ['key' => 'site.title']);
    }

    #[Test]
    #[TestDox('创建配置时名称必填返回 422')]
    public function create_setting_requires_name(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/settings', [
            'key' => 'test.key',
            'cast_type' => 'string',
            'input_type' => 'string',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    #[TestDox('创建配置时 key 必填返回 422')]
    public function create_setting_requires_key(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/settings', [
            'name' => '测试配置',
            'cast_type' => 'string',
            'input_type' => 'string',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    #[Test]
    #[TestDox('创建配置时 key 不能重复返回 422')]
    public function create_setting_key_must_be_unique(): void
    {
        Setting::create(['name' => '已有配置', 'key' => 'duplicate.key', 'value' => '1', 'cast_type' => 'string', 'input_type' => 'string']);

        $response = $this->actingAsAdmin()->postJson('/admin/settings', [
            'name' => '重复配置',
            'key' => 'duplicate.key',
            'cast_type' => 'string',
            'input_type' => 'string',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    #[Test]
    #[TestDox('创建配置时 key 格式必须合法返回 422')]
    public function create_setting_key_must_be_valid_format(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/settings', [
            'name' => '非法Key',
            'key' => 'invalid key!',
            'cast_type' => 'string',
            'input_type' => 'string',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    #[Test]
    #[TestDox('创建配置时 cast_type 必须合法返回 422')]
    public function create_setting_cast_type_must_be_valid(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/settings', [
            'name' => '非法类型',
            'key' => 'test.invalid',
            'cast_type' => 'invalid_type',
            'input_type' => 'string',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['cast_type']);
    }

    #[Test]
    #[TestDox('创建配置时 input_type 必须合法返回 422')]
    public function create_setting_input_type_must_be_valid(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/settings', [
            'name' => '非法输入类型',
            'key' => 'test.input',
            'cast_type' => 'string',
            'input_type' => 'invalid_input',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['input_type']);
    }

    #[Test]
    #[TestDox('创建配置时 input_type 支持远程下拉')]
    public function create_setting_accepts_remote_select_input_type(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/settings', [
            'name' => '远程下拉配置',
            'key' => 'test.remote_select',
            'value' => 'local',
            'cast_type' => 'string',
            'input_type' => 'remote_select',
            'param' => '{"url":"/admin/settings/options"}',
        ]);

        $response->assertCreated()
            ->assertJsonPath('input_type', 'remote_select');

        $this->assertDatabaseHas('settings', [
            'key' => 'test.remote_select',
            'input_type' => 'remote_select',
        ]);
    }

    #[Test]
    #[TestDox('获取配置详情')]
    public function admin_can_view_setting(): void
    {
        $setting = Setting::create(['name' => '详情测试', 'key' => 'detail.key', 'value' => 'val', 'cast_type' => 'string', 'input_type' => 'string']);

        $response = $this->actingAsAdmin()->getJson("/admin/settings/{$setting->id}");
        $response->assertOk()
            ->assertJsonPath('id', $setting->id)
            ->assertJsonPath('key', 'detail.key');
    }

    #[Test]
    #[TestDox('获取不存在的配置返回 404')]
    public function view_nonexistent_setting_returns_404(): void
    {
        $this->actingAsAdmin()->getJson('/admin/settings/99999')->assertNotFound();
    }

    #[Test]
    #[TestDox('更新配置成功')]
    public function admin_can_update_setting(): void
    {
        $setting = Setting::create(['name' => '旧名称', 'key' => 'update.key', 'value' => 'old', 'cast_type' => 'string', 'input_type' => 'string']);

        $response = $this->actingAsAdmin()->putJson("/admin/settings/{$setting->id}", [
            'name' => '新名称',
            'key' => 'update.key',
            'value' => 'new value',
            'cast_type' => 'string',
            'input_type' => 'string',
        ]);

        $response->assertOk()
            ->assertJsonPath('name', '新名称')
            ->assertJsonPath('value', 'new value');

        $this->assertDatabaseHas('settings', [
            'id' => $setting->id,
            'name' => '新名称',
            'value' => 'new value',
        ]);
    }

    #[Test]
    #[TestDox('更新配置时 key 可以保持不变')]
    public function update_setting_can_keep_same_key(): void
    {
        $setting = Setting::create(['name' => '保留Key', 'key' => 'keep.key', 'value' => '1', 'cast_type' => 'string', 'input_type' => 'string']);

        $response = $this->actingAsAdmin()->putJson("/admin/settings/{$setting->id}", [
            'name' => '保留Key更新',
            'key' => 'keep.key',
            'cast_type' => 'string',
            'input_type' => 'string',
        ]);

        $response->assertOk()
            ->assertJsonPath('key', 'keep.key');
    }

    #[Test]
    #[TestDox('删除配置返回 204')]
    public function admin_can_delete_setting(): void
    {
        $setting = Setting::create(['name' => '待删除', 'key' => 'delete.key', 'value' => '1', 'cast_type' => 'string', 'input_type' => 'string']);

        $response = $this->actingAsAdmin()->deleteJson("/admin/settings/{$setting->id}");
        $response->assertNoContent();

        $this->assertDatabaseMissing('settings', ['id' => $setting->id]);
    }

    #[Test]
    #[TestDox('配置按 sort 升序排列')]
    public function settings_ordered_by_sort_asc(): void
    {
        Setting::create(['name' => '丙', 'key' => 'c.key', 'value' => '1', 'cast_type' => 'string', 'input_type' => 'string', 'sort' => 3]);
        Setting::create(['name' => '甲', 'key' => 'a.key', 'value' => '1', 'cast_type' => 'string', 'input_type' => 'string', 'sort' => 1]);
        Setting::create(['name' => '乙', 'key' => 'b.key', 'value' => '1', 'cast_type' => 'string', 'input_type' => 'string', 'sort' => 2]);

        // 按 sort=1 的配置中找到我们创建的 a.key
        $response = $this->actingAsAdmin()->getJson('/admin/settings?keyword=a.key');
        $response->assertOk()
            ->assertJsonPath('data.0.key', 'a.key');
    }

    #[Test]
    #[TestDox('未登录不能创建配置')]
    public function guest_cannot_create_setting(): void
    {
        $this->postJson('/admin/settings', ['name' => 'test', 'key' => 'test.key', 'cast_type' => 'string', 'input_type' => 'string'])
            ->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取分组配置返回 200 且包含 groups 和 disks')]
    public function admin_can_get_setting_groups(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/settings/groups');

        $response->assertOk()
            ->assertJsonStructure(['groups', 'disks']);

        $groups = $response->json('groups');
        $this->assertNotEmpty($groups);
        // 验证每个分组有 key/title/items 结构
        $this->assertArrayHasKey('key', $groups[0]);
        $this->assertArrayHasKey('title', $groups[0]);
        $this->assertArrayHasKey('items', $groups[0]);
    }

    #[Test]
    #[TestDox('批量更新配置成功并刷新缓存')]
    public function admin_can_batch_update_settings(): void
    {
        $response = $this->actingAsAdmin()->putJson('/admin/settings/batch', [
            'system' => [
                'title' => '新的网站标题',
                'keywords' => 'laravel,test',
            ],
            'sms_captcha' => [
                'length' => 8,
            ],
            'user' => [
                'enable_register' => 0,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', '设置保存成功');

        // 验证数据已更新
        $this->assertDatabaseHas('settings', [
            'key' => 'system.title',
            'value' => '新的网站标题',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'sms_captcha.length',
            'value' => '8',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'user.enable_register',
            'value' => '0',
        ]);

        // 验证缓存刷新
        $this->assertEquals('新的网站标题', settings('system.title'));
        $this->assertEquals(8, settings('sms_captcha.length'));
        $this->assertFalse(settings('user.enable_register'));
    }

    #[Test]
    #[TestDox('批量更新配置支持扁平点号 key（前端实际提交格式）')]
    public function admin_can_batch_update_settings_with_flat_dotted_keys(): void
    {
        $response = $this->actingAsAdmin()->putJson('/admin/settings/batch', [
            'system.title' => '扁平键标题',
            'sms_captcha.length' => 8,
            'user.enable_register' => 0,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', '设置保存成功');

        $this->assertDatabaseHas('settings', [
            'key' => 'system.title',
            'value' => '扁平键标题',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'sms_captcha.length',
            'value' => '8',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'user.enable_register',
            'value' => '0',
        ]);

        $this->assertEquals('扁平键标题', settings('system.title'));
        $this->assertEquals(8, settings('sms_captcha.length'));
        $this->assertFalse(settings('user.enable_register'));
    }

    #[Test]
    #[TestDox('批量更新时整型字段传入非数字返回 422')]
    public function batch_update_validates_int_fields(): void
    {
        $response = $this->actingAsAdmin()->putJson('/admin/settings/batch', [
            'sms_captcha' => [
                'length' => 'not-a-number',
            ],
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    #[TestDox('批量更新时扁平点号 key 的整型字段传入非数字返回 422')]
    public function batch_update_validates_int_fields_with_flat_dotted_keys(): void
    {
        $response = $this->actingAsAdmin()->putJson('/admin/settings/batch', [
            'sms_captcha.length' => 'not-a-number',
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    #[TestDox('批量更新时没有匹配到任何已知配置项返回 422')]
    public function batch_update_returns_422_when_no_known_settings_matched(): void
    {
        $response = $this->actingAsAdmin()->putJson('/admin/settings/batch', [
            'not.a.real.key' => 'value',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', '没有可保存的配置项，请检查提交的数据');
    }

    #[Test]
    #[TestDox('未登录不能批量更新配置')]
    public function guest_cannot_batch_update_settings(): void
    {
        $this->putJson('/admin/settings/batch', [])
            ->assertUnauthorized();
    }

    #[Test]
    #[TestDox('未登录不能获取分组配置')]
    public function guest_cannot_get_setting_groups(): void
    {
        $this->getJson('/admin/settings/groups')
            ->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取输入类型列表返回所有枚举选项')]
    public function admin_can_get_setting_input_types(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/settings/input-types');

        $response->assertOk()
            ->assertJsonCount(count(SettingInputType::cases()), 'data')
            ->assertJsonStructure(['data' => [['value', 'label']]])
            ->assertJsonPath('data.0.value', SettingInputType::STRING->value)
            ->assertJsonPath('data.0.label', SettingInputType::STRING->label());

        $this->assertSame(
            SettingInputType::values(),
            array_column($response->json('data'), 'value')
        );
    }

    #[Test]
    #[TestDox('未登录不能获取输入类型列表')]
    public function guest_cannot_get_setting_input_types(): void
    {
        $this->getJson('/admin/settings/input-types')
            ->assertUnauthorized();
    }
}
