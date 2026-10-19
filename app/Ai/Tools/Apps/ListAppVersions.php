<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Apps;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use App\Models\App\AppVersion;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * App 版本查询工具。
 *
 * 支持按平台、发布状态、版本号、是否强制更新、创建时间范围筛选，
 * 返回分页版本数据。只读操作。
 */
class ListAppVersions implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'app-versions.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询 App 版本列表。支持按平台（安卓/iOS/鸿蒙）、发布状态（草稿/已发布/已撤回/已废弃）、版本号关键字、是否强制更新、创建时间范围筛选，以分页形式返回版本信息（含构建号、最低支持版本、下载地址、发布时间等）。只读操作，不能执行发布或撤回。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'platform' => $schema->string()
                ->enum(['android', 'ios', 'harmony'])
                ->description('平台筛选：android=安卓，ios=iOS，harmony=鸿蒙'),
            'status' => $schema->string()
                ->enum(['draft', 'published', 'withdrawn', 'obsolete'])
                ->description('发布状态筛选：draft=草稿，published=已发布，withdrawn=已撤回，obsolete=已废弃'),
            'version' => $schema->string()
                ->max(50)
                ->description('模糊匹配版本号，如 1.2'),
            'force_update' => $schema->boolean()
                ->description('是否仅查询强制更新版本：true=仅强制更新，false=仅非强制更新。不传则查询全部'),
            'start_date' => $schema->string()
                ->format('date')
                ->description('创建起始日期（YYYY-MM-DD），含当天'),
            'end_date' => $schema->string()
                ->format('date')
                ->description('创建截止日期（YYYY-MM-DD），含当天'),
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

        foreach (['platform', 'status', 'version', 'start_date', 'end_date'] as $key) {
            if ($value = $request->string($key)->toString()) {
                $filters[$key] = $value;
            }
        }

        if ($request->has('force_update')) {
            $filters['force_update'] = $request->boolean('force_update');
        }

        $page = $request->integer('page', 1) ?: 1;
        $perPage = $request->integer('per_page', 20) ?: 20;

        $paginator = AppVersion::query()
            ->filter($filters)
            ->with('app')
            ->orderByDesc('id')
            ->paginate(perPage: min(max($perPage, 1), 50), page: $page);

        $items = $paginator->getCollection()->map(fn (AppVersion $version) => [
            'id' => $version->id,
            'app' => $version->app ? [
                'id' => $version->app->id,
                'name' => $version->app->name,
            ] : null,
            'platform' => $version->platform->value,
            'platform_label' => $version->platform->label(),
            'version' => $version->version,
            'build' => $version->build,
            'release_notes' => $version->release_notes,
            'min_supported_version' => $version->min_supported_version,
            'min_supported_build' => $version->min_supported_build,
            'force_update' => $version->force_update,
            'status' => $version->status->value,
            'status_label' => $version->status->label(),
            'download_url' => $version->download_url,
            'published_at' => $version->published_at?->toDateTimeString(),
            'created_at' => $version->created_at?->toDateTimeString(),
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
