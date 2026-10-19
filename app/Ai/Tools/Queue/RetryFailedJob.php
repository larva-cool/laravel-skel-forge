<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\Queue;

use App\Ai\Contracts\RequiresPermission;
use App\Ai\Tools\Concerns\AuthorizesAdmin;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Jobs\RetryFailedJob as HorizonRetryFailedJob;
use Stringable;

/**
 * 队列失败任务重试工具（Horizon）。
 *
 * 将指定的失败任务重新推入队列执行。敏感写操作，执行前需要管理员二次确认。
 */
class RetryFailedJob implements Approvable, RequiresPermission, Tool
{
    use AuthorizesAdmin, InteractsWithApprovals;

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
        return '将指定的队列失败任务重新推入队列执行（Horizon 重试机制）。重试前建议先用 ListFailedJobs 查询失败任务详情确认失败原因已排除。该操作为敏感写操作，执行前会展示重试摘要并请求二次确认。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->required()
                ->max(50)
                ->description('失败任务 ID（通过 ListFailedJobs 查询获得）'),
        ];
    }

    /**
     * 二次确认信息。
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $job = $this->findFailedJob($request->string('id')->toString());
        if (! $job) {
            return false;
        }

        return Approval::required("即将重试失败任务 [{$job->id}] {$job->name}（队列：{$job->queue}），任务将被重新推入队列执行。");
    }

    /**
     * 执行重试。
     */
    public function handle(Request $request): Stringable|string
    {
        if ($deny = $this->authorizeAdmin()) {
            return $deny;
        }

        $id = $request->string('id')->toString();
        $job = $this->findFailedJob($id);

        if (! $job) {
            return "未找到 ID 为 {$id} 的失败任务（可能已被重试或清理），操作已取消。";
        }

        dispatch(new HorizonRetryFailedJob($id));

        return "已将失败任务 [{$job->id}] {$job->name}（队列：{$job->queue}）重新推入队列，稍后可使用 ListFailedJobs 确认其是否已从失败列表移除。";
    }

    /**
     * 查找失败任务
     */
    protected function findFailedJob(string $id): ?object
    {
        if ($id === '') {
            return null;
        }

        $job = app(JobRepository::class)->getJobs([$id])->first();

        return $job && ($job->status ?? null) === 'failed' ? $job : null;
    }
}
