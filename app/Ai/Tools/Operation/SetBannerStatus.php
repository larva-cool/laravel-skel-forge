<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Operation;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\Operation\Banner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Banner 启用状态变更工具。
 *
 * 启用后 Banner 在生效时间范围内对用户可见；禁用后立即下线。
 * 敏感写操作，执行前需要管理员二次确认。
 */
class SetBannerStatus implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'banners.edit';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '启用或禁用指定 Banner/轮播图。启用后 Banner 在生效时间范围内对用户可见；禁用后立即从用户端下线。该操作为敏感写操作，执行前会展示变更摘要并请求二次确认。';
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
                ->description('Banner ID'),
            'status' => $schema->boolean()
                ->required()
                ->description('目标状态：true=启用，false=禁用'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $banner = Banner::query()->find($request->integer('id'));
        if (! $banner) {
            return false;
        }

        $action = $request->boolean('status') ? '启用' : '禁用';

        return Approval::required("即将{$action} Banner「{$banner->title}」（广告位：{$banner->position}，当前状态：".($banner->status ? '启用' : '禁用').'）。');
    }

    /**
     * 执行状态变更。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $banner = Banner::query()->find($request->integer('id'));
        if (! $banner) {
            return '未找到目标 Banner，操作已取消。';
        }

        $target = $request->boolean('status');

        if ($banner->status === $target) {
            return "Banner「{$banner->title}」当前已是「".($target ? '启用' : '禁用').'」状态，无需变更。';
        }

        $banner->update(['status' => $target]);

        return $target
            ? "已启用 Banner「{$banner->title}」，在生效时间范围内将对用户可见。"
            : "已禁用 Banner「{$banner->title}」，用户端不再展示。";
    }
}
