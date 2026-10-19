<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Segments;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\UserSegment\Segment;
use App\Services\Segment\SegmentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 规则分群同步工具。
 *
 * 按分群规则重新匹配用户并同步成员（仅新增/移除规则来源成员，
 * 不影响手动打标成员）。敏感写操作，执行前需要管理员二次确认。
 */
class SyncUserSegment implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'segments.edit';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '手动触发规则分群的成员同步：按分群规则重新匹配全量用户，将新命中用户加入分群、不再命中的规则来源用户移出分群（不影响手动打标成员）。仅支持已启用的规则分群。该操作为敏感写操作，执行前会展示同步摘要并请求二次确认。';
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
                ->description('规则分群 ID（可通过 ListUserSegments 查询）'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $segment = Segment::query()->find($request->integer('id'));
        if (! $segment) {
            return false;
        }

        return Approval::required("即将同步规则分群「{$segment->name}」（类型：{$segment->type->label()}，当前".($segment->status ? '启用' : '禁用').'），将按规则重新匹配用户并更新分群成员。');
    }

    /**
     * 执行同步。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $segment = Segment::query()->find($request->integer('id'));
        if (! $segment) {
            return '未找到目标分群，操作已取消。';
        }

        try {
            $result = app(SegmentService::class)->syncRuleSegment($segment);
        } catch (InvalidArgumentException $e) {
            return "操作已取消：{$e->getMessage()}";
        }

        $fresh = $segment->refresh();

        return "规则分群「{$fresh->name}」同步完成：新增 {$result['added']} 人，移出 {$result['removed']} 人，当前共匹配 {$result['total']} 人。同步时间：{$fresh->last_synced_at?->toDateTimeString()}。";
    }
}
