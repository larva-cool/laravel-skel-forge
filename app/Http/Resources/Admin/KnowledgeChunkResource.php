<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Ai\KnowledgeChunk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KnowledgeChunk
 */
class KnowledgeChunkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'content' => $this->content,
            'tokens' => $this->tokens,
            'sort' => $this->sort,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
