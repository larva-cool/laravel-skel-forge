<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Knowledge;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Services\Ai\KnowledgeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 知识库语义检索工具。
 *
 * 将查询文本向量化后与知识库分块做余弦相似度计算，
 * 返回最相关的 Top-N 分块（含来源文档与相似度得分）。只读操作。
 */
class SearchKnowledgeBase implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'knowledge-documents.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '在知识库中进行语义检索。当管理员询问平台业务规则、产品说明、操作指南等知识类问题时，用自然语言描述问题进行检索，返回最相关的知识片段（含来源文档标题与相似度得分）。仅检索已完成索引且启用的文档。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->required()
                ->max(500)
                ->description('检索内容，用完整的自然语言描述问题效果最佳'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(10)
                ->default(5)
                ->description('返回结果条数，默认 5，最大 10'),
        ];
    }

    /**
     * 执行检索。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $query = trim($request->string('query')->toString());
        if ($query === '') {
            return '检索内容不能为空。';
        }

        $limit = $request->integer('limit', 5) ?: 5;

        $results = app(KnowledgeService::class)->search($query, min(max($limit, 1), 10));

        if ($results->isEmpty()) {
            return '知识库中未检索到相关内容。可能是知识库尚未收录该主题，建议换个表述重试，或如实告知管理员该问题暂无知识库支持。';
        }

        return json_encode([
            'query' => $query,
            'total' => $results->count(),
            'data' => $results->map(static fn (array $item): array => [
                'document_title' => $item['document_title'],
                'content' => $item['content'],
                'score' => round($item['score'], 4),
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
