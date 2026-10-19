<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Operation;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\AnnouncementStatus;
use App\Models\Operation\Announcement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 公告状态流转工具（发布 / 归档）。
 *
 * 发布：草稿或已归档 → 已发布（首次发布自动记录发布时间）；
 * 归档：已发布 → 已归档（用户端不可见）。
 * 敏感写操作，执行前需要管理员二次确认。
 */
class PublishAnnouncement implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'announcements.edit';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '发布或归档平台公告。action=publish 将草稿/已归档公告发布上线（用户端可见，首次发布自动记录发布时间）；action=archive 将已发布公告归档下线（用户端不可见）。该操作为敏感写操作，执行前会展示变更摘要并请求二次确认。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->required()
                ->description('公告 ID'),
            'action' => $schema->string()
                ->enum(['publish', 'archive'])
                ->required()
                ->description('操作类型：publish=发布上线，archive=归档下线'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $announcement = Announcement::query()->find($request->integer('id'));
        if (! $announcement) {
            return false;
        }

        $action = $request->string('action')->toString() === 'archive' ? '归档下线' : '发布上线';

        return Approval::required("即将{$action}公告「{$announcement->title}」（当前状态：{$announcement->status->label()}）。");
    }

    /**
     * 执行状态流转。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $announcement = Announcement::query()->find($request->integer('id'));
        if (! $announcement) {
            return '未找到目标公告，操作已取消。';
        }

        $isPublish = $request->string('action')->toString() === 'publish';
        $original = $announcement->status;

        if ($isPublish && $original->isPublished()) {
            return "公告「{$announcement->title}」当前已是「已发布」状态，无需重复发布。";
        }

        if (! $isPublish && ! $original->isPublished()) {
            return "公告「{$announcement->title}」当前状态为「{$original->label()}」，仅已发布公告可归档，操作已取消。";
        }

        $announcement->update([
            'status' => $isPublish ? AnnouncementStatus::PUBLISHED : AnnouncementStatus::ARCHIVED,
        ]);

        $fresh = $announcement->refresh();

        return $isPublish
            ? "已发布公告「{$fresh->title}」，发布时间：{$fresh->published_at?->toDateTimeString()}，用户端现已可见。"
            : "已归档公告「{$fresh->title}」，用户端不再展示。";
    }
}
