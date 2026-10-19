<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Queue;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Horizon\Contracts\JobRepository;
use Stringable;

/**
 * 队列失败任务查询工具（Horizon）。
 *
 * 读取 Horizon（Redis）中最近失败的队列任务，支持按任务类名/队列名/异常信息
 * 关键字过滤。只读操作。
 */
class ListFailedJobs implements RequiresPermission, Tool
{
    use AuthorizesAdmin;

    /**
     * 执行该工具所需的权限点。
     */
    public function permission(): string|array|null
    {
        return 'horizon.index';
    }

    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询队列系统中最近失败的任务（Horizon 数据），返回任务 ID、任务类名、所属队列、失败时间与异常摘要，支持按任务类名/队列名/异常信息关键字过滤。用于排查异步任务失败原因。只读操作。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()
                ->max(100)
                ->description('模糊匹配任务类名、队列名或异常信息'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(20)
                ->description('返回条数上限，最大 50'),
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

        $jobs = app(JobRepository::class);

        $keyword = $request->string('keyword')->toString();
        $limit = min(max($request->integer('limit', 20) ?: 20, 1), 50);

        $items = $jobs->getFailed(-1)
            ->filter(function ($job) use ($keyword) {
                if ($keyword === '') {
                    return true;
                }

                return str_contains($job->name ?? '', $keyword)
                    || str_contains($job->queue ?? '', $keyword)
                    || str_contains($job->exception ?? '', $keyword);
            })
            ->take($limit)
            ->map(fn ($job) => [
                'id' => $job->id,
                'name' => $job->name,
                'queue' => $job->queue,
                'status' => $job->status,
                'failed_at' => $job->failed_at ?? null,
                'exception' => isset($job->exception) ? mb_substr((string) $job->exception, 0, 500) : null,
            ])
            ->values();

        return json_encode([
            'data' => $items,
            'total' => $jobs->countFailed(),
            'returned' => $items->count(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
