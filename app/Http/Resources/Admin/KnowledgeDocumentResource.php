<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Ai\KnowledgeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KnowledgeDocument
 */
class KnowledgeDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            // 原始内容仅在详情接口返回，避免列表负载过大
            'content' => $this->when($request->routeIs('admin.knowledge-documents.show'), $this->content),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_type' => $this->status->labelType(),
            'is_enabled' => $this->is_enabled,
            'chunks_count' => $this->chunks_count,
            'tokens' => $this->tokens,
            'error' => $this->error,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
