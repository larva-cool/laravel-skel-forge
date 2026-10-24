<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Common;

use App\Enums\AnnouncementStatus;
use App\Http\Resources\Api\V1\AnnouncementResource;
use App\Mcp\Tools\Concerns\BuildsPaginatedResponse;
use App\Models\Operation\Announcement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取公告列表（仅已发布）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('common.announcement.list')]
#[Description('获取已发布的公告列表（分页），按发布时间倒序返回。')]
#[IsReadOnly]
#[IsIdempotent]
class ListAnnouncementsTool extends Tool
{
    use BuildsPaginatedResponse;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $arguments = $request->validate($this->paginationRules());

        $paginator = Announcement::query()
            ->where('status', AnnouncementStatus::PUBLISHED)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($this->resolvePerPage($arguments), ['*'], 'page', $this->resolvePage($arguments));

        return Response::structured($this->paginatedResult($paginator, AnnouncementResource::class));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->paginationSchema($schema);
    }
}
