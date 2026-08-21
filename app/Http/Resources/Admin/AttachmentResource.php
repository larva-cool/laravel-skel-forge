<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\System\Attachment;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 附件资源
 *
 * @mixin Attachment
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class AttachmentResource extends JsonResource
{
    /**
     * 是否附带物理文件存在性检查（仅详情接口使用）。
     */
    protected bool $withExists = false;

    /**
     * 标记需要返回 exists 字段。
     */
    public function withExists(): static
    {
        $this->withExists = true;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = app(AttachmentService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'original_name' => $this->original_name,
            'disk' => $this->disk,
            'path' => $this->path,
            'url' => $service->url($this->resource),
            'preview_url' => $this->previewUrl($service),
            'type' => $this->type,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_text' => $this->size_text,
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader ? [
                'id' => $this->uploader->getKey(),
                'name' => $this->uploader->name ?? $this->uploader->username ?? null,
            ] : null),
            'created_at' => $this->created_at?->toDateTimeString(),
            $this->mergeWhen($this->withExists, fn () => ['exists' => $service->exists($this->resource)]),
        ];
    }

    /**
     * 获取可直接用于展示的地址。
     *
     * 公开磁盘返回直链，私有磁盘退化为临时签名地址；取址失败时返回 null。
     */
    protected function previewUrl(AttachmentService $service): ?string
    {
        $url = $service->url($this->resource);
        if ($url !== null) {
            return $url;
        }

        try {
            return $service->temporaryUrl($this->resource);
        } catch (\Throwable) {
            return null;
        }
    }
}
