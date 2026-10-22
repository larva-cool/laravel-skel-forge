<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Ai;

use App\Models\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 知识库文档分块
 *
 * 保存分块文本及其向量，供语义检索时做余弦相似度计算。
 *
 * @property int $id 主键
 * @property int $document_id 所属文档 ID
 * @property string $content 分块文本内容
 * @property array<int, float> $embedding 向量
 * @property int $tokens 该分块向量化消耗的 Tokens
 * @property int $sort 分块在文档内的顺序
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('knowledge_chunks')]
#[Fillable(['document_id', 'content', 'embedding', 'tokens', 'sort'])]
class KnowledgeChunk extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'document_id' => 'integer',
            'content' => 'string',
            'embedding' => 'array',
            'tokens' => 'integer',
            'sort' => 'integer',
        ];
    }

    /**
     * 所属文档
     *
     * @return BelongsTo<KnowledgeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }
}
