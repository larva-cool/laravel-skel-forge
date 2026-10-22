<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Knowledge;

use App\Ai\Tools\Knowledge\SearchKnowledgeBase;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\Ai\KnowledgeDocument;
use App\Services\Ai\KnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 知识库语义检索工具测试
 */
#[CoversClass(SearchKnowledgeBase::class)]
#[Group('ai')]
#[Group('knowledge')]
class SearchKnowledgeBaseTest extends TestCase
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
        $result = (new SearchKnowledgeBase)->handle(new Request(['query' => '苹果']));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 knowledge-documents.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new SearchKnowledgeBase)->forAdmin($admin)->handle(new Request(['query' => '苹果']));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('检索内容为空时返回提示')]
    public function handle_returns_hint_for_blank_query(): void
    {
        $admin = $this->createAdminWithPermissions(['knowledge-documents.index']);

        $result = (new SearchKnowledgeBase)->forAdmin($admin)->handle(new Request(['query' => '  ']));

        $this->assertStringContainsString('检索内容不能为空', (string) $result);
    }

    #[Test]
    #[TestDox('知识库无相关内容时返回空结果提示')]
    public function handle_returns_empty_hint_when_no_results(): void
    {
        Embeddings::fake();
        $admin = $this->createAdminWithPermissions(['knowledge-documents.index']);

        $result = (new SearchKnowledgeBase)->forAdmin($admin)->handle(new Request(['query' => '苹果']));

        $this->assertStringContainsString('未检索到相关内容', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回相似度排序的检索结果')]
    public function handle_returns_ranked_results(): void
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

        $service = app(KnowledgeService::class);

        $doc = KnowledgeDocument::factory()->create([
            'title' => '苹果文档',
            'content' => '苹果是一种营养丰富的水果。',
        ]);
        $service->indexDocument($doc);

        $otherDoc = KnowledgeDocument::factory()->create([
            'title' => '无关文档',
            'content' => '香蕉富含钾元素。',
        ]);
        $service->indexDocument($otherDoc);

        $admin = $this->createAdminWithPermissions(['knowledge-documents.index']);

        $result = (new SearchKnowledgeBase)->forAdmin($admin)->handle(new Request(['query' => '苹果']));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame('苹果', $payload['query']);
        $this->assertSame('苹果文档', $payload['data'][0]['document_title']);
        $this->assertArrayHasKey('score', $payload['data'][0]);
        $this->assertGreaterThanOrEqual($payload['data'][1]['score'], $payload['data'][0]['score']);
    }
}
