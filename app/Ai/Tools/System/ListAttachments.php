<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\System;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\System\Attachment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 附件列表查询工具。
 *
 * 支持按附件类型、存储磁盘、扩展名、文件名关键字、上传时间范围筛选，
 * 返回分页附件数据。只读操作。
 */
class ListAttachments implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'attachments.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询附件列表。支持按附件类型（图片/视频/音频/文档/其他）、存储磁盘、扩展名、文件名关键字、上传时间范围筛选，以分页形式返回附件信息（含原始文件名、MIME 类型、文件大小、存储路径等）。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['image', 'video', 'audio', 'document', 'other'])
                ->description('附件类型筛选：image=图片，video=视频，audio=音频，document=文档，other=其他'),
            'extension' => $schema->string()
                ->max(10)
                ->description('扩展名筛选，如 jpg、pdf'),
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配显示名称或原始文件名'),
            'start_date' => $schema->string()
                ->format('date')
                ->description('上传起始日期（YYYY-MM-DD），含当天'),
            'end_date' => $schema->string()
                ->format('date')
                ->description('上传截止日期（YYYY-MM-DD），含当天'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('页码，从 1 开始'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(20)
                ->description('每页条数，最大 50'),
        ];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $filters = [];

        foreach (['type', 'extension', 'keyword', 'start_date', 'end_date'] as $key) {
            if ($value = $request->string($key)->toString()) {
                $filters[$key] = $value;
            }
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = Attachment::query()
            ->filter($filters)
            ->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (Attachment $attachment) => [
            'id' => $attachment->id,
            'name' => $attachment->name,
            'original_name' => $attachment->original_name,
            'type' => $attachment->type->value,
            'type_label' => $attachment->type->label(),
            'extension' => $attachment->extension,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'size_text' => $attachment->size_text,
            'disk' => $attachment->disk,
            'path' => $attachment->path,
            'created_at' => $attachment->created_at?->toDateTimeString(),
        ]);

        return json_encode([
            'data' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
