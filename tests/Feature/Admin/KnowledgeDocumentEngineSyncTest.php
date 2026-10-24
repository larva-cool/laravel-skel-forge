<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin\Admin;
use App\Models\Ai\KnowledgeDocument;
use App\Services\Ai\Contracts\KnowledgeSearchEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 知识库文档变更与检索引擎同步测试
 *
 * 通过间谍引擎验证：停用/删除文档时移除索引，启用/元数据变更时重新同步。
 */
#[CoversClass(\App\Http\Controllers\Admin\KnowledgeDocumentController::class)]
#[Group('admin')]
#[Group('knowledge')]
class KnowledgeDocumentEngineSyncTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private SpyKnowledgeSearchEngine $spy;

    protected function setUp(): void
    {
        parent::setUp();

        // seed_initial_admin_data 迁移已预置超级管理员
        $this->admin = Admin::query()->where('username', 'admin')->firstOrFail();
        $this->spy = new SpyKnowledgeSearchEngine;
        $this->app->instance(KnowledgeSearchEngine::class, $this->spy);
    }

    #[Test]
    #[TestDox('停用已就绪文档时从检索引擎移除')]
    public function disabling_ready_document_removes_from_engine(): void
    {
        $document = KnowledgeDocument::factory()->ready()->create();

        $this->actingAs($this->admin, 'admin')
            ->putJson("/admin/knowledge-documents/{$document->id}/toggle-status")
            ->assertOk();

        $this->assertSame([$document->id], $this->spy->removed);
        $this->assertSame([], $this->spy->synced);
    }

    #[Test]
    #[TestDox('重新启用已就绪文档时同步至检索引擎')]
    public function enabling_ready_document_syncs_to_engine(): void
    {
        $document = KnowledgeDocument::factory()->ready()->disabled()->create();

        $this->actingAs($this->admin, 'admin')
            ->putJson("/admin/knowledge-documents/{$document->id}/toggle-status")
            ->assertOk();

        $this->assertSame([$document->id], $this->spy->synced);
        $this->assertSame([], $this->spy->removed);
    }

    #[Test]
    #[TestDox('仅修改标题时同步元数据而不重建索引')]
    public function title_only_update_syncs_metadata_without_reindex(): void
    {
        $document = KnowledgeDocument::factory()->ready()->create(['title' => '旧标题']);

        $this->actingAs($this->admin, 'admin')
            ->putJson("/admin/knowledge-documents/{$document->id}", [
                'title' => '新标题',
                'content' => $document->content,
                'is_enabled' => true,
            ])
            ->assertOk();

        $this->assertSame([$document->id], $this->spy->synced);
    }

    #[Test]
    #[TestDox('删除文档时先从检索引擎移除')]
    public function destroy_removes_document_from_engine(): void
    {
        $document = KnowledgeDocument::factory()->ready()->create();

        $this->actingAs($this->admin, 'admin')
            ->deleteJson("/admin/knowledge-documents/{$document->id}")
            ->assertNoContent();

        $this->assertSame([$document->id], $this->spy->removed);
        $this->assertDatabaseMissing('knowledge_documents', ['id' => $document->id]);
    }
}

/**
 * 记录同步调用的间谍检索引擎
 */
class SpyKnowledgeSearchEngine implements KnowledgeSearchEngine
{
    /** @var array<int, int> */
    public array $synced = [];

    /** @var array<int, int> */
    public array $removed = [];

    public function search(string $query, array $queryVector, int $limit): array
    {
        return [];
    }

    public function syncDocument(KnowledgeDocument $document): void
    {
        $this->synced[] = $document->id;
    }

    public function removeDocument(int $documentId): void
    {
        $this->removed[] = $documentId;
    }
}
