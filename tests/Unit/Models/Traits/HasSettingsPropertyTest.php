<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\Traits;

use App\Models\Traits\HasSettingsProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * HasSettingsProperty trait 单元测试
 *
 * 使用独立测试模型验证 trait 行为
 */
#[CoversClass(HasSettingsProperty::class)]
#[Group('traits')]
class HasSettingsPropertyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建临时表测试 trait
        Schema::create('test_settings_models', function ($table) {
            $table->id();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_settings_models');
        parent::tearDown();
    }

    private function createTestModel(?array $settings = null): object
    {
        $model = new class extends Model
        {
            use HasSettingsProperty;

            protected $table = 'test_settings_models';

            protected $guarded = [];

            protected $casts = ['settings' => 'json'];
            public const DEFAULT_SETTINGS = ['theme' => 'light', 'lang' => 'en'];
        };

        if ($settings !== null) {
            $model->settings = $settings;
        }
        $model->save();

        return $model->fresh();
    }

    #[Test]
    #[TestDox('getSettings 返回 Fluent 对象')]
    public function get_settings_attribute_returns_fluent(): void
    {
        $model = $this->createTestModel();

        $this->assertInstanceOf(Fluent::class, $model->settings);
    }

    #[Test]
    #[TestDox('setSettingsAttribute 将数组序列化为 JSON')]
    public function set_settings_attribute_serializes_to_json(): void
    {
        $model = $this->createTestModel(['theme' => 'dark', 'lang' => 'zh']);

        $this->assertStringContainsString('dark', $model->getRawOriginal('settings'));
        $this->assertStringContainsString('zh', $model->getRawOriginal('settings'));
    }

    #[Test]
    #[TestDox('getSettings 返回存储的设置与默认值合并')]
    public function get_settings_merges_with_defaults(): void
    {
        $model = $this->createTestModel(['theme' => 'dark']);

        $settings = $model->getSettings();

        // 存储值覆盖默认值
        $this->assertSame('dark', $settings['theme']);
        // 默认值保留
        $this->assertSame('en', $settings['lang']);
    }

    #[Test]
    #[TestDox('getSettings 无存储值时返回默认值')]
    public function get_settings_returns_defaults_when_empty(): void
    {
        $model = $this->createTestModel();

        $settings = $model->getSettings();

        $this->assertSame('light', $settings['theme']);
        $this->assertSame('en', $settings['lang']);
    }

    #[Test]
    #[TestDox('settings 属性支持动态访问')]
    public function settings_supports_dynamic_access(): void
    {
        $model = $this->createTestModel(['theme' => 'dark']);
        $model->refresh();

        $this->assertSame('dark', $model->settings->theme);
    }
}
