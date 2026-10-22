<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\KnowledgeDocumentController;
use App\Jobs\Ai\ProcessKnowledgeDocumentJob;
use App\Models\Admin\Admin;
use App\Models\Ai\KnowledgeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台知识库管理控制器功能测试
 */
#[CoversClass(KnowledgeDocumentController::class)]
#[Group('admin')]
#[Group('knowledge')]
class KnowledgeDocumentControllerTest extends TestCase
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
            'title' => '平台使用指南',
            'content' => '这是一段用于测试的知识库文档内容，长度满足校验要求。',
            'is_enabled' => true,
        ], $overrides);
    }

    #[Test]
    #[TestDox('未登录访问文档列表返回 401')]
    public function guest_cannot_list_documents(): void
    {
        $this->getJson('/admin/knowledge-documents')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可获取文档列表分页数据')]
    public function admin_can_list_documents(): void
    {
        KnowledgeDocument::factory()->count(3)->create();

        $response = $this->actingAsAdmin()->getJson('/admin/knowledge-documents');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按关键词搜索文档标题')]
    public function admin_can_search_by_keyword(): void
    {
        KnowledgeDocument::factory()->create(['title' => '特定的知识标题']);
        KnowledgeDocument::factory()->create(['title' => '其他文档']);

        $response = $this->actingAsAdmin()->getJson('/admin/knowledge-documents?keyword='.rawurlencode('特定的知识'));

        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('按索引状态与启用状态筛选文档')]
    public function admin_can_filter_by_status_and_enabled(): void
    {
        KnowledgeDocument::factory()->ready()->create();
        KnowledgeDocument::factory()->disabled()->create();

        $this->actingAsAdmin()->getJson('/admin/knowledge-documents?status=ready')
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->actingAsAdmin()->getJson('/admin/knowledge-documents?is_enabled=0')
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('管理员可创建文档并自动派发索引任务')]
    public function admin_can_create_document_and_dispatch_indexing(): void
    {
        Queue::fake();

        $response = $this->actingAsAdmin()->postJson('/admin/knowledge-documents', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('title', '平台使用指南')
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('created_by', $this->admin->id);

        $document = KnowledgeDocument::query()->firstOrFail();
        Queue::assertPushed(ProcessKnowledgeDocumentJob::class, fn (ProcessKnowledgeDocumentJob $job): bool => $job->documentId === $document->id);
    }

    #[Test]
    #[TestDox('创建文档时缺少标题或内容返回 422')]
    public function create_requires_title_and_content(): void
    {
        $this->actingAsAdmin()->postJson('/admin/knowledge-documents', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content']);
    }

    #[Test]
    #[TestDox('管理员可查看文档详情（含原始内容）')]
    public function admin_can_view_document_detail(): void
    {
        $document = KnowledgeDocument::factory()->create(['content' => '这是一段用于测试的详情内容文本。']);

        $this->actingAsAdmin()->getJson("/admin/knowledge-documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('id', $document->id)
            ->assertJsonPath('content', '这是一段用于测试的详情内容文本。');
    }

    #[Test]
    #[TestDox('列表接口不返回原始内容')]
    public function list_does_not_include_content(): void
    {
        KnowledgeDocument::factory()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/knowledge-documents');

        $response->assertOk();
        $this->assertArrayNotHasKey('content', $response->json('data.0'));
    }

    #[Test]
    #[TestDox('查看不存在文档返回 404')]
    public function view_missing_document_returns_404(): void
    {
        $this->actingAsAdmin()->getJson('/admin/knowledge-documents/999999')
            ->assertNotFound();
    }

    #[Test]
    #[TestDox('仅更新标题时不重新派发索引任务')]
    public function update_title_only_does_not_dispatch_indexing(): void
    {
        Queue::fake();
        $document = KnowledgeDocument::factory()->create(['title' => '原标题']);

        $this->actingAsAdmin()->putJson("/admin/knowledge-documents/{$document->id}", $this->validPayload([
            'title' => '新标题',
            'content' => $document->content,
        ]))->assertOk()->assertJsonPath('title', '新标题');

        Queue::assertNotPushed(ProcessKnowledgeDocumentJob::class);
    }

    #[Test]
    #[TestDox('更新内容时自动重新派发索引任务')]
    public function update_content_dispatches_indexing(): void
    {
        Queue::fake();
        $document = KnowledgeDocument::factory()->create();

        $this->actingAsAdmin()->putJson("/admin/knowledge-documents/{$document->id}", $this->validPayload([
            'content' => '这是一段更新后的知识库文档内容，用于触发重建索引。',
        ]))->assertOk();

        Queue::assertPushed(ProcessKnowledgeDocumentJob::class);
        $this->assertSame('pending', $document->refresh()->status->value);
    }

    #[Test]
    #[TestDox('切换文档启用状态')]
    public function admin_can_toggle_document_status(): void
    {
        $document = KnowledgeDocument::factory()->create(['is_enabled' => true]);

        $this->actingAsAdmin()->putJson("/admin/knowledge-documents/{$document->id}/toggle-status")
            ->assertOk()->assertJsonPath('is_enabled', false);

        $this->assertFalse($document->refresh()->is_enabled);
    }

    #[Test]
    #[TestDox('重建索引会重置状态并派发任务')]
    public function admin_can_reindex_document(): void
    {
        Queue::fake();
        $document = KnowledgeDocument::factory()->failed()->create();

        $this->actingAsAdmin()->postJson("/admin/knowledge-documents/{$document->id}/reindex")
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('error', null);

        Queue::assertPushed(ProcessKnowledgeDocumentJob::class);
    }

    #[Test]
    #[TestDox('管理员可删除文档且分块级联删除')]
    public function admin_can_delete_document_and_chunks(): void
    {
        $document = KnowledgeDocument::factory()->create();
        $document->chunks()->create([
            'content' => '分块内容',
            'embedding' => [0.1, 0.2],
            'tokens' => 0,
            'sort' => 0,
        ]);

        $this->actingAsAdmin()->deleteJson("/admin/knowledge-documents/{$document->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('knowledge_documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('knowledge_chunks', ['document_id' => $document->id]);
    }

    #[Test]
    #[TestDox('管理员可查看文档分块列表')]
    public function admin_can_list_document_chunks(): void
    {
        $document = KnowledgeDocument::factory()->ready()->create();
        foreach (range(1, 2) as $i) {
            $document->chunks()->create([
                'content' => "分块内容 {$i}",
                'embedding' => [0.1, 0.2],
                'tokens' => 0,
                'sort' => $i - 1,
            ]);
        }

        $this->actingAsAdmin()->getJson("/admin/knowledge-documents/{$document->id}/chunks")
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.content', '分块内容 1');
    }

    #[Test]
    #[TestDox('语义检索测试返回按相似度排序的结果')]
    public function admin_can_test_semantic_search(): void
    {
        Embeddings::fake(function (EmbeddingsPrompt $prompt): array {
            $vectors = [];
            foreach ($prompt->inputs as $input) {
                $vectors[] = match (true) {
                    str_contains((string) $input, '苹果') => array_pad([1.0], $prompt->dimensions, 0.0),
                    default => array_pad([0.0, 1.0], $prompt->dimensions, 0.0),
                };
            }

            return $vectors;
        });

        $document = KnowledgeDocument::factory()->create([
            'title' => '苹果文档',
            'content' => '苹果是一种营养丰富的水果。',
        ]);
        app(\App\Services\Ai\KnowledgeService::class)->indexDocument($document);

        $response = $this->actingAsAdmin()->postJson('/admin/knowledge-documents/search', [
            'query' => '苹果怎么吃',
            'limit' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('0.document_title', '苹果文档')
            ->assertJsonStructure(['0' => ['chunk_id', 'document_id', 'document_title', 'content', 'score']]);
    }

    #[Test]
    #[TestDox('语义检索缺少 query 参数返回 422')]
    public function search_requires_query(): void
    {
        $this->actingAsAdmin()->postJson('/admin/knowledge-documents/search', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }
}
