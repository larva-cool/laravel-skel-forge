<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ChatModelController;
use App\Models\Admin\Admin;
use App\Models\Ai\ChatModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台 AI 模型管理控制器功能测试
 */
#[CoversClass(ChatModelController::class)]
#[Group('admin')]
#[Group('chat-model')]
class ChatModelControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // seed_initial_admin_data 迁移已预置超级管理员
        $this->admin = Admin::query()->where('username', 'admin')->firstOrFail();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 默认有效 payload
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'provider' => 'volc',
            'model_id' => 'test-model-'.uniqid(),
            'name' => '测试模型',
            'is_default' => false,
            'is_enabled' => true,
            'sort' => 10,
        ], $overrides);
    }

    #[Test]
    #[TestDox('未登录访问模型列表返回 401')]
    public function guest_cannot_list_chat_models(): void
    {
        $this->getJson('/admin/chat-models')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可获取模型列表分页数据（含种子迁移预置模型）')]
    public function admin_can_list_chat_models(): void
    {
        ChatModel::factory()->count(2)->create();

        // 种子迁移已预置 4 个模型
        $response = $this->actingAsAdmin()->getJson('/admin/chat-models');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 6);
    }

    #[Test]
    #[TestDox('按关键词搜索模型名称或模型 ID')]
    public function admin_can_search_by_keyword(): void
    {
        ChatModel::factory()->create(['name' => '特定的模型名称']);

        $response = $this->actingAsAdmin()->getJson('/admin/chat-models?keyword='.rawurlencode('特定的模型名称'));

        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('按启用状态筛选模型')]
    public function admin_can_filter_by_enabled_status(): void
    {
        ChatModel::factory()->disabled()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/chat-models?is_enabled=0');

        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('管理员可创建模型')]
    public function admin_can_create_chat_model(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/chat-models', $this->validPayload([
            'model_id' => 'new-model-001',
            'name' => '新模型',
        ]));

        $response->assertCreated()
            ->assertJsonPath('model_id', 'new-model-001')
            ->assertJsonPath('name', '新模型')
            ->assertJsonPath('provider', 'volc');

        $this->assertDatabaseHas('chat_models', ['model_id' => 'new-model-001']);
    }

    #[Test]
    #[TestDox('创建模型时缺少 model_id 返回 422')]
    public function create_requires_model_id(): void
    {
        $payload = $this->validPayload();
        unset($payload['model_id']);

        $this->actingAsAdmin()->postJson('/admin/chat-models', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['model_id']);
    }

    #[Test]
    #[TestDox('创建模型时同服务商下 model_id 重复返回 422')]
    public function create_validates_unique_model_id_per_provider(): void
    {
        $existing = ChatModel::query()->firstOrFail();

        $this->actingAsAdmin()->postJson('/admin/chat-models', $this->validPayload([
            'provider' => $existing->provider,
            'model_id' => $existing->model_id,
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['model_id']);
    }

    #[Test]
    #[TestDox('管理员可查看模型详情')]
    public function admin_can_view_chat_model_detail(): void
    {
        $model = ChatModel::factory()->create(['name' => '详情模型']);

        $this->actingAsAdmin()->getJson("/admin/chat-models/{$model->id}")
            ->assertOk()
            ->assertJsonPath('id', $model->id)
            ->assertJsonPath('name', '详情模型');
    }

    #[Test]
    #[TestDox('查看不存在模型返回 404')]
    public function view_missing_chat_model_returns_404(): void
    {
        $this->actingAsAdmin()->getJson('/admin/chat-models/999999')
            ->assertNotFound();
    }

    #[Test]
    #[TestDox('管理员可更新模型')]
    public function admin_can_update_chat_model(): void
    {
        $model = ChatModel::factory()->create(['name' => '原名称']);

        $response = $this->actingAsAdmin()->putJson("/admin/chat-models/{$model->id}", $this->validPayload([
            'model_id' => $model->model_id,
            'name' => '更新后的名称',
            'sort' => 99,
        ]));

        $response->assertOk()
            ->assertJsonPath('name', '更新后的名称')
            ->assertJsonPath('sort', 99);

        $model->refresh();
        $this->assertSame('更新后的名称', $model->name);
        $this->assertSame(99, $model->sort);
    }

    #[Test]
    #[TestDox('切换模型启用状态')]
    public function admin_can_toggle_chat_model_status(): void
    {
        $model = ChatModel::factory()->create(['is_enabled' => true]);

        $response = $this->actingAsAdmin()->putJson("/admin/chat-models/{$model->id}/toggle-status");

        $response->assertOk()->assertJsonPath('is_enabled', false);

        $model->refresh();
        $this->assertFalse($model->is_enabled);
    }

    #[Test]
    #[TestDox('设置某模型为默认时自动取消其他模型的默认标记')]
    public function setting_default_unsets_other_defaults(): void
    {
        // 种子迁移已预置一个默认模型
        $oldDefault = ChatModel::query()->where('is_default', true)->firstOrFail();

        $model = ChatModel::factory()->create(['is_default' => false]);

        $this->actingAsAdmin()->putJson("/admin/chat-models/{$model->id}", $this->validPayload([
            'model_id' => $model->model_id,
            'is_default' => true,
        ]))->assertOk()->assertJsonPath('is_default', true);

        $this->assertFalse($oldDefault->refresh()->is_default);
        $this->assertSame(1, ChatModel::query()->where('is_default', true)->count());
    }

    #[Test]
    #[TestDox('管理员可删除模型')]
    public function admin_can_delete_chat_model(): void
    {
        $model = ChatModel::factory()->create();

        $this->actingAsAdmin()->deleteJson("/admin/chat-models/{$model->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('chat_models', ['id' => $model->id]);
    }
}
