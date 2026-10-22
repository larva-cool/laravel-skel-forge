<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Knowledge;

use App\Ai\Tools\Knowledge\SearchPublicKnowledgeBase;
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
use Tests\TestCase;

/**
 * 公开知识库语义检索工具测试
 */
#[CoversClass(SearchPublicKnowledgeBase::class)]
#[Group('ai')]
#[Group('knowledge')]
class SearchPublicKnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('公开工具无权限要求（permission 返回 null）')]
    public function permission_is_null(): void
    {
        $this->assertNull((new SearchPublicKnowledgeBase)->permission());
    }

    #[Test]
    #[TestDox('未注入管理员也可正常检索')]
    public function handle_allows_search_without_admin(): void
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

        $result = (new SearchPublicKnowledgeBase)->handle(new Request(['query' => '苹果']));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame('苹果', $payload['query']);
        $this->assertSame('苹果文档', $payload['data'][0]['document_title']);
    }

    #[Test]
    #[TestDox('检索内容为空时返回提示')]
    public function handle_returns_hint_for_blank_query(): void
    {
        $result = (new SearchPublicKnowledgeBase)->handle(new Request(['query' => '  ']));

        $this->assertStringContainsString('检索内容不能为空', (string) $result);
    }

    #[Test]
    #[TestDox('知识库无相关内容时返回空结果提示')]
    public function handle_returns_empty_hint_when_no_results(): void
    {
        Embeddings::fake();

        $result = (new SearchPublicKnowledgeBase)->handle(new Request(['query' => '苹果']));

        $this->assertStringContainsString('未检索到相关内容', (string) $result);
    }
}
