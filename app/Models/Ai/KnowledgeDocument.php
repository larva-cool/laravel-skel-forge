<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Ai;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\Model;
use Database\Factories\Ai\KnowledgeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 知识库文档
 *
 * 原始文本内容经分块、向量化后生成 KnowledgeChunk，供 RAG 语义检索使用。
 *
 * @property int $id 主键
 * @property string $title 文档标题
 * @property string $content 文档原始内容
 * @property KnowledgeDocumentStatus $status 索引状态
 * @property bool $is_enabled 是否启用（停用的文档不参与检索）
 * @property int $chunks_count 分块数量
 * @property int $tokens 向量化消耗的 Tokens
 * @property string|null $error 最近一次索引失败原因
 * @property int|null $created_by 创建管理员 ID
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('knowledge_documents')]
#[Fillable(['title', 'content', 'status', 'is_enabled', 'chunks_count', 'tokens', 'error', 'created_by'])]
class KnowledgeDocument extends Model
{
    /** @use HasFactory<KnowledgeDocumentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'title' => 'string',
            'content' => 'string',
            'status' => KnowledgeDocumentStatus::class,
            'is_enabled' => 'boolean',
            'chunks_count' => 'integer',
            'tokens' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * 文档分块
     *
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'document_id')->orderBy('sort');
    }

    /**
     * 标记为处理中
     */
    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => KnowledgeDocumentStatus::PROCESSING,
            'error' => null,
        ])->save();
    }

    /**
     * 标记为已完成
     */
    public function markReady(int $chunksCount, int $tokens): void
    {
        $this->forceFill([
            'status' => KnowledgeDocumentStatus::READY,
            'chunks_count' => $chunksCount,
            'tokens' => $tokens,
            'error' => null,
        ])->save();
    }

    /**
     * 标记为失败
     */
    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => KnowledgeDocumentStatus::FAILED,
            'error' => mb_substr($error, 0, 500),
        ])->save();
    }
}
