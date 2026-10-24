<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Common;

use App\Enums\AnnouncementStatus;
use App\Http\Resources\Api\V1\AnnouncementResource;
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
 * 获取公告详情
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('common.announcement.get')]
#[Description('根据公告 ID 获取已发布公告的详情；不存在或未发布返回错误。')]
#[IsReadOnly]
#[IsIdempotent]
class GetAnnouncementTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $announcement = Announcement::query()
            ->where('status', AnnouncementStatus::PUBLISHED)
            ->find($arguments['id']);

        if (! $announcement) {
            return Response::error('Announcement not found.');
        }

        return Response::structured((new AnnouncementResource($announcement))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('公告 ID')->min(1)->required(),
        ];
    }
}
