<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\CacheKey;
use App\Enums\SettingType;
use App\Models\System\Setting;
use App\Services\SettingManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * SettingManagerService 单元测试
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
#[CoversClass(SettingManagerService::class)]
#[Group('services')]
class SettingManagerServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettingManagerService $service;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(CacheKey::SETTINGS);
        Setting::query()->delete();
        $this->service = new SettingManagerService;
    }

    /**
     * 测试空数据库时 all 返回空集合
     */
    #[Test]
    #[TestDox('空数据库时 all 返回空集合并写入缓存')]
    public function all_returns_empty_collection_when_no_settings(): void
    {
        $result = $this->service->all();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
        $this->assertTrue(Cache::has(CacheKey::SETTINGS));
    }

    /**
     * 测试 all 从数据库加载配置并按类型转换
     */
    #[Test]
    #[TestDox('all 从数据库加载配置并按 cast_type 正确转换类型')]
    public function all_loads_from_database_and_casts_values(): void
    {
        Setting::create(['name' => '站点名称', 'key' => 'site_name', 'value' => 'My Site', 'cast_type' => 'string']);
        Setting::create(['name' => '每页条数', 'key' => 'page_size', 'value' => '20', 'cast_type' => SettingType::CAST_TYPE_INT]);
        Setting::create(['name' => '版本号', 'key' => 'version', 'value' => '1.5', 'cast_type' => SettingType::CAST_TYPE_FLOAT]);
        Setting::create(['name' => '是否开启', 'key' => 'enabled', 'value' => '1', 'cast_type' => SettingType::CAST_TYPE_BOOL]);
        Setting::create(['name' => '整型别名', 'key' => 'alt_int', 'value' => '42', 'cast_type' => 'integer']);
        Setting::create(['name' => '布尔别名', 'key' => 'alt_bool', 'value' => '0', 'cast_type' => 'boolean']);

        $result = $this->service->all();

        $this->assertSame('My Site', $result->get('site_name'));
        $this->assertSame(20, $result->get('page_size'));
        $this->assertSame(1.5, $result->get('version'));
        $this->assertTrue($result->get('enabled'));
        $this->assertSame(42, $result->get('alt_int'));
        $this->assertFalse($result->get('alt_bool'));
    }

    /**
     * 测试 all 使用内存缓存，不重复查询数据库
     */
    #[Test]
    #[TestDox('all 已加载时直接返回内存缓存，不重新查库')]
    public function all_returns_in_memory_cached_collection_without_reload(): void
    {
        Setting::create(['name' => 'A', 'key' => 'a', 'value' => '1']);
        $this->service->all();

        Setting::create(['name' => 'B', 'key' => 'b', 'value' => '2']);

        $result = $this->service->all();
        $this->assertTrue($result->has('a'));
        $this->assertFalse($result->has('b'));
    }

    /**
     * 测试 all(reload=true) 强制重载
     */
    #[Test]
    #[TestDox('all(reload=true) 强制从数据库重新加载')]
    public function all_forces_reload_when_reload_flag_is_true(): void
    {
        Setting::create(['name' => 'A', 'key' => 'a', 'value' => '1']);
        $this->service->all();

        Setting::create(['name' => 'B', 'key' => 'b', 'value' => '2']);

        $result = $this->service->all(true);
        $this->assertTrue($result->has('a'));
        $this->assertTrue($result->has('b'));
    }

    /**
     * 测试 all 使用缓存命中时不查库
     */
    #[Test]
    #[TestDox('all 命中应用缓存时直接从缓存读取')]
    public function all_reads_from_application_cache_when_available(): void
    {
        Cache::put(CacheKey::SETTINGS, ['cached_key' => 'cached_value']);

        $result = $this->service->all();

        $this->assertSame('cached_value', $result->get('cached_key'));
    }

    /**
     * 测试 get 返回配置值
     */
    #[Test]
    #[TestDox('get 返回已存在配置的值')]
    public function get_returns_value_for_existing_key(): void
    {
        Setting::create(['name' => '站点名', 'key' => 'site_name', 'value' => 'Foo']);

        $this->assertSame('Foo', $this->service->get('site_name'));
    }

    /**
     * 测试 get 不存在时返回默认值
     */
    #[Test]
    #[TestDox('get 配置不存在时返回默认值')]
    public function get_returns_default_for_missing_key(): void
    {
        $this->assertSame('default_val', $this->service->get('missing.key', 'default_val'));
        $this->assertNull($this->service->get('missing.key'));
    }

    /**
     * 测试 get 支持点号分隔的嵌套键
     */
    #[Test]
    #[TestDox('get 支持通过点号访问嵌套键（由 Arr::set 构建）')]
    public function get_supports_dot_notation_for_nested_keys(): void
    {
        Setting::create(['name' => 'SMTP 主机', 'key' => 'mail.host', 'value' => 'smtp.example.com']);
        Setting::create(['name' => 'SMTP 端口', 'key' => 'mail.port', 'value' => '465', 'cast_type' => SettingType::CAST_TYPE_INT]);

        $mail = $this->service->get('mail');

        $this->assertIsArray($mail);
        $this->assertSame('smtp.example.com', $mail['host']);
        $this->assertSame(465, $mail['port']);
        $this->assertSame('smtp.example.com', $this->service->get('mail.host'));
        $this->assertSame(465, $this->service->get('mail.port'));
    }

    /**
     * 测试 has 判断配置存在
     */
    #[Test]
    #[TestDox('has 正确判断配置项是否存在')]
    public function has_correctly_checks_key_existence(): void
    {
        Setting::create(['name' => 'A', 'key' => 'exists_key', 'value' => 'x']);

        $this->assertTrue($this->service->has('exists_key'));
        $this->assertFalse($this->service->has('not_exists_key'));
    }

    /**
     * 测试 tag 获取配置组
     */
    #[Test]
    #[TestDox('tag 返回指定标签分组下的配置数组')]
    public function tag_returns_nested_array_under_tag(): void
    {
        Setting::create(['name' => 'host', 'key' => 'db.host', 'value' => '127.0.0.1']);
        Setting::create(['name' => 'port', 'key' => 'db.port', 'value' => '3306', 'cast_type' => SettingType::CAST_TYPE_INT]);

        $db = $this->service->tag('db');

        $this->assertIsArray($db);
        $this->assertSame('127.0.0.1', $db['host']);
        $this->assertSame(3306, $db['port']);
    }

    /**
     * 测试 tag 默认标签为 default
     */
    #[Test]
    #[TestDox('tag 未传参时使用 default 分组')]
    public function tag_default_tag_is_default(): void
    {
        Setting::create(['name' => '时区', 'key' => 'default.timezone', 'value' => 'Asia/Shanghai']);

        $defaultGroup = $this->service->tag();

        $this->assertIsArray($defaultGroup);
        $this->assertSame('Asia/Shanghai', $defaultGroup['timezone']);
    }

    /**
     * 测试 set 插入新配置
     */
    #[Test]
    #[TestDox('set 插入新配置后可通过 get 读取到')]
    public function set_inserts_new_setting_and_reloads_cache(): void
    {
        $result = $this->service->set('new_key', 'new_value');

        $this->assertTrue($result);
        $this->assertDatabaseHas('settings', ['key' => 'new_key', 'value' => 'new_value', 'cast_type' => 'string']);
        $this->assertSame('new_value', $this->service->get('new_key'));
    }

    /**
     * 测试 set 更新已存在配置
     */
    #[Test]
    #[TestDox('set 更新已存在配置并可立即读到新值')]
    public function set_updates_existing_setting(): void
    {
        Setting::create(['name' => '旧值', 'key' => 'update_key', 'value' => 'old']);
        Cache::forget(CacheKey::SETTINGS);

        $result = $this->service->set('update_key', 'new_value', SettingType::CAST_TYPE_STRING);

        $this->assertTrue($result);
        $this->assertSame('new_value', $this->service->get('update_key'));
        $this->assertSame(1, Setting::query()->where('key', '=', 'update_key')->count());
    }

    /**
     * 测试 set 数组值返回 false
     */
    #[Test]
    #[TestDox('set 传入数组值时返回 false 且不写入')]
    public function set_returns_false_for_array_value(): void
    {
        $result = $this->service->set('arr_key', ['a', 'b']);

        $this->assertFalse($result);
        $this->assertDatabaseMissing('settings', ['key' => 'arr_key']);
    }

    /**
     * 测试 set 支持指定类型转换
     */
    #[Test]
    #[TestDox('set 使用指定 cast_type 写入并正确转换读取')]
    public function set_respects_cast_type_and_is_cast_on_read(): void
    {
        $this->service->set('int_key', '100', SettingType::CAST_TYPE_INT);

        $this->assertSame(100, $this->service->get('int_key'));
    }

    /**
     * 测试 forge 删除配置
     */
    #[Test]
    #[TestDox('forge 删除配置并从缓存中失效')]
    public function forge_deletes_setting_and_reloads(): void
    {
        Setting::create(['name' => 'TBD', 'key' => 'del_key', 'value' => 'to_delete']);
        $this->service->all();
        $this->assertTrue($this->service->has('del_key'));

        $result = $this->service->forge('del_key');

        $this->assertTrue($result);
        $this->assertDatabaseMissing('settings', ['key' => 'del_key']);
        $this->assertFalse($this->service->has('del_key'));
    }

    /**
     * 测试 forge 删除不存在的键返回 true
     */
    #[Test]
    #[TestDox('forge 删除不存在的键仍返回 true')]
    public function forge_returns_true_even_when_key_not_exists(): void
    {
        $this->assertTrue($this->service->forge('not_exists'));
    }

    /**
     * 测试 castTypes 返回各配置项类型映射
     */
    #[Test]
    #[TestDox('castTypes 返回 key 到 cast_type 的映射数组')]
    public function cast_types_returns_key_to_cast_type_map(): void
    {
        Setting::create(['name' => '站点', 'key' => 'site', 'value' => 'S', 'cast_type' => 'string']);
        Setting::create(['name' => '数量', 'key' => 'count', 'value' => '5', 'cast_type' => SettingType::CAST_TYPE_INT]);

        $castTypes = $this->service->castTypes();

        $this->assertIsArray($castTypes);
        $this->assertSame('string', $castTypes['site']);
        $this->assertSame(SettingType::CAST_TYPE_INT, $castTypes['count']);
    }
}
