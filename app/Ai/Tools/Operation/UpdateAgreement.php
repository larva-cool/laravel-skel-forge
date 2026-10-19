<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Operation;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Enums\AgreementStatus;
use App\Models\Operation\Agreement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * 用户协议更新工具。
 *
 * 更新协议的标题、版本号、正文、状态或排序权重；至少提供一项变更。
 * 敏感写操作，执行前需要管理员二次确认。
 */
class UpdateAgreement implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'agreements.edit';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '更新指定用户协议的内容：可修改标题、版本号、正文（富文本 HTML）、状态（draft=草稿，published=发布，archived=归档）、排序权重，至少提供一项变更。发布后用户端按类别展示最新已发布协议，状态首次变为已发布时自动记录生效时间。该操作为敏感写操作，执行前会展示变更摘要并请求二次确认。';
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
                ->description('协议 ID'),
            'title' => $schema->string()
                ->max(100)
                ->description('新协议标题'),
            'version' => $schema->string()
                ->max(20)
                ->description('新版本号，如 2.0'),
            'content' => $schema->string()
                ->description('新协议正文（富文本 HTML）'),
            'status' => $schema->string()
                ->enum(['draft', 'published', 'archived'])
                ->description('目标状态：draft=草稿，published=发布，archived=归档'),
            'sort' => $schema->integer()
                ->min(0)
                ->description('新排序权重，越小越靠前'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $agreement = Agreement::query()->find($request->integer('id'));
        if (! $agreement) {
            return false;
        }

        $changes = [];
        if ($request->string('title')->toString() !== '') {
            $changes[] = '修改标题';
        }
        if ($request->string('version')->toString() !== '') {
            $changes[] = '版本号 → '.$request->string('version')->toString();
        }
        if ($request->string('content')->toString() !== '') {
            $changes[] = '更新正文';
        }
        if ($status = $request->string('status')->toString()) {
            $changes[] = '状态 →「'.(AgreementStatus::tryFrom($status)?->label() ?? $status).'」';
        }
        if ($request->has('sort')) {
            $changes[] = '排序权重 → '.$request->integer('sort');
        }

        return Approval::required("即将更新协议「{$agreement->title}」（类别：{$agreement->category}）：".implode('，', $changes).'。');
    }

    /**
     * 执行更新。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $agreement = Agreement::query()->find($request->integer('id'));
        if (! $agreement) {
            return '未找到目标协议，操作已取消。';
        }

        $data = [];
        $changes = [];

        if ($title = $request->string('title')->toString()) {
            $data['title'] = $title;
            $changes[] = '标题';
        }
        if ($version = $request->string('version')->toString()) {
            $data['version'] = $version;
            $changes[] = "版本号 → {$version}";
        }
        if ($content = $request->string('content')->toString()) {
            $data['content'] = $content;
            $changes[] = '正文';
        }
        if ($status = $request->string('status')->toString()) {
            $data['status'] = AgreementStatus::from($status);
            $changes[] = '状态 →「'.AgreementStatus::from($status)->label().'」';
        }
        if ($request->has('sort')) {
            $data['sort'] = $request->integer('sort');
            $changes[] = '排序权重';
        }

        if ($data === []) {
            return '请至少提供一项需要更新的字段（title/version/content/status/sort），操作已取消。';
        }

        $data['admin_id'] = $this->admin->id;
        $agreement->update($data);

        $fresh = $agreement->refresh();
        $suffix = $fresh->status->isPublished() && $fresh->effective_at
            ? "，生效时间：{$fresh->effective_at->toDateTimeString()}"
            : '';

        return "已更新协议「{$fresh->title}」（类别：{$fresh->category}）：".implode('，', $changes).$suffix.'。';
    }
}
