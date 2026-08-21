<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\System;

use App\Enums\SettingType;
use App\Models\System\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Setting 模型单元测试
 */
#[CoversClass(Setting::class)]
#[Group('models')]
class SettingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试获取已存在配置的变量类型
     */
    #[Test]
    #[TestDox('获取已存在配置的变量类型')]
    public function get_value_type_returns_cast_type_for_existing_key(): void
    {
        Setting::query()->delete();

        Setting::create([
            'name' => '测试配置',
            'key' => 'test.key',
            'value' => 'hello',
            'cast_type' => SettingType::CAST_TYPE_STRING,
        ]);

        $this->assertSame(SettingType::CAST_TYPE_STRING, Setting::getValueType('test.key'));
    }

    /**
     * 测试获取不存在配置时返回默认值
     */
    #[Test]
    #[TestDox('获取不存在配置时返回默认值')]
    public function get_value_type_returns_default_for_non_existing_key(): void
    {
        $this->assertSame('string', Setting::getValueType('non.existent.key'));
    }

    /**
     * 测试获取不存在配置时返回自定义默认值
     */
    #[Test]
    #[TestDox('获取不存在配置时返回自定义默认值')]
    public function get_value_type_returns_custom_default_for_non_existing_key(): void
    {
        $this->assertSame('int', Setting::getValueType('non.existent.key', 'int'));
    }

    /**
     * 测试空表时获取所有配置返回空数组
     */
    #[Test]
    #[TestDox('空表时获取所有配置返回空数组')]
    public function get_all_returns_empty_array_when_no_settings(): void
    {
        Setting::query()->delete();

        $this->assertSame([], Setting::getAll());
    }

    /**
     * 测试获取所有配置并按 sort 排序
     */
    #[Test]
    #[TestDox('获取所有配置并按 sort 排序')]
    public function get_all_returns_all_settings_ordered(): void
    {
        Setting::query()->delete();

        Setting::create(['name' => '配置B', 'key' => 'key.b', 'value' => 'b_value', 'sort' => 2]);
        Setting::create(['name' => '配置A', 'key' => 'key.a', 'value' => 'a_value', 'sort' => 1]);
        Setting::create(['name' => '配置C', 'key' => 'key.c', 'value' => 'c_value', 'sort' => 3]);

        $settings = Setting::getAll();

        $this->assertCount(3, $settings);
        $this->assertSame(['key.a', 'key.b', 'key.c'], array_keys($settings));
        $this->assertSame('a_value', $settings['key.a']);
    }

    /**
     * 测试批量插入新配置
     */
    #[Test]
    #[TestDox('批量插入新配置')]
    public function batch_set_inserts_new_settings(): void
    {
        Setting::query()->delete();

        Setting::batchSet([
            ['name' => '配置一', 'key' => 'test.one', 'value' => 'value1'],
            ['name' => '配置二', 'key' => 'test.two', 'value' => 'value2'],
        ]);

        $this->assertDatabaseHas('settings', ['key' => 'test.one', 'value' => 'value1']);
        $this->assertDatabaseHas('settings', ['key' => 'test.two', 'value' => 'value2']);
    }

    /**
     * 测试批量更新已存在配置
     */
    #[Test]
    #[TestDox('批量更新已存在配置')]
    public function batch_set_updates_existing_settings(): void
    {
        Setting::query()->delete();

        Setting::create(['name' => '原名称', 'key' => 'test.update', 'value' => 'old_value']);

        Setting::batchSet([
            ['name' => '新名称', 'key' => 'test.update', 'value' => 'new_value'],
        ]);

        $setting = Setting::where('key', 'test.update')->first();
        $this->assertNotNull($setting);
        $this->assertSame('新名称', $setting->name);
        $this->assertSame('new_value', $setting->value);
    }

    /**
     * 测试批量设置时缺失字段使用默认值
     */
    #[Test]
    #[TestDox('批量设置时缺失字段使用默认值')]
    public function batch_set_uses_default_values_for_missing_fields(): void
    {
        Setting::query()->delete();

        Setting::batchSet([
            ['key' => 'test.defaults', 'value' => 'some_value'],
        ]);

        $setting = Setting::where('key', 'test.defaults')->first();
        $this->assertNotNull($setting);
        $this->assertNull($setting->name);
        $this->assertSame('string', $setting->cast_type);
        $this->assertSame('text', $setting->input_type);
        $this->assertSame(0, $setting->sort);
    }

    /**
     * 测试批量同时插入和更新配置
     */
    #[Test]
    #[TestDox('批量同时插入和更新配置')]
    public function batch_set_inserts_and_updates_simultaneously(): void
    {
        Setting::query()->delete();

        Setting::create(['name' => '旧名称', 'key' => 'test.existing', 'value' => 'old_value']);

        Setting::batchSet([
            ['name' => '新名称', 'key' => 'test.existing', 'value' => 'new_value'],
            ['name' => '新配置', 'key' => 'test.new', 'value' => 'new_item_value'],
        ]);

        $this->assertSame(2, Setting::count());

        $existing = Setting::where('key', 'test.existing')->first();
        $this->assertSame('new_value', $existing->value);

        $new = Setting::where('key', 'test.new')->first();
        $this->assertSame('new_item_value', $new->value);
    }

    /**
     * 测试配置不记录 created_at
     */
    #[Test]
    #[TestDox('配置不记录 created_at')]
    public function setting_does_not_record_created_at(): void
    {
        Setting::query()->delete();

        $setting = Setting::create(['name' => '测试', 'key' => 'test.no_created', 'value' => 'val']);

        $this->assertNull($setting->created_at);
        $this->assertNotNull($setting->updated_at);
    }
}
