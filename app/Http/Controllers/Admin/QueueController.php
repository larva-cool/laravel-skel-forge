<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Bus\BatchRepository;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\Jobs\MonitorTag;
use Laravel\Horizon\Jobs\RetryFailedJob;
use Laravel\Horizon\Jobs\StopMonitoringTag;
use Laravel\Horizon\ProvisioningPlan;
use Laravel\Horizon\WaitTimeCalculator;

/**
 * 队列管理控制器（Laravel Horizon 数据代理）
 *
 * 通过 Horizon 内部契约（Contracts）直接读取 Redis 中的队列数据，
 * 提供给前后端分离的管理后台进行队列监控与管理。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class QueueController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:horizon.index');
    }

    /**
     * Dashboard 统计数据
     */
    public function stats(): JsonResponse
    {
        $jobRepo = app(JobRepository::class);
        $metricsRepo = app(MetricsRepository::class);

        return response()->json([
            'failedJobs' => $jobRepo->countRecentlyFailed(),
            'jobsPerMinute' => $metricsRepo->jobsProcessedPerMinute(),
            'pausedMasters' => $this->totalPausedMasters(),
            'periods' => [
                'failedJobs' => config('horizon.trim.recent_failed', config('horizon.trim.failed')),
                'recentJobs' => config('horizon.trim.recent'),
            ],
            'processes' => $this->totalProcessCount(),
            'queueWithMaxRuntime' => $metricsRepo->queueWithMaximumRuntime(),
            'queueWithMaxThroughput' => $metricsRepo->queueWithMaximumThroughput(),
            'recentJobs' => $jobRepo->countRecent(),
            'status' => $this->currentStatus(),
            'wait' => collect(app(WaitTimeCalculator::class)->calculate())->take(1),
        ]);
    }

    /**
     * 各队列工作负载
     */
    public function workload(WorkloadRepository $workload): JsonResponse
    {
        return response()->json(
            collect($workload->get())
                ->sortBy('name')
                ->values()
                ->all()
        );
    }

    /**
     * 主监督器及其下属监督器
     */
    public function masters(MasterSupervisorRepository $masters, SupervisorRepository $supervisors): JsonResponse
    {
        $masters = collect($masters->all())->keyBy('name')->sortBy('name');

        $supervisors = collect($supervisors->all())->sortBy('name')->groupBy('master');

        $result = $masters->each(function ($master, $name) use ($supervisors) {
            $master->supervisors = ($supervisors->get($name) ?? collect())
                ->merge(
                    collect(ProvisioningPlan::get($name)->plan[$master->environment ?? config('horizon.env') ?? config('app.env')] ?? [])
                        ->map(function ($value, $key) use ($name) {
                            return (object) [
                                'name' => $name.':'.$key,
                                'master' => $name,
                                'status' => 'inactive',
                                'processes' => [],
                                'options' => [
                                    'queue' => array_key_exists('queue', $value) && is_array($value['queue']) ? implode(',', $value['queue']) : ($value['queue'] ?? ''),
                                    'balance' => $value['balance'] ?? null,
                                ],
                            ];
                        })
                )
                ->unique('name')
                ->values();
        })->values();

        return response()->json($result);
    }

    /**
     * 监控标签列表
     */
    public function monitoringTags(TagRepository $tags, JobRepository $jobs): JsonResponse
    {
        $data = collect($tags->monitoring())
            ->map(fn ($tag) => [
                'tag' => $tag,
                'count' => $tags->count($tag) + $tags->count('failed:'.$tag),
            ])
            ->sortBy('tag')
            ->values();

        return response()->json($data);
    }

    /**
     * 分页获取某标签下的任务
     */
    public function monitoringJobs(Request $request, TagRepository $tags, JobRepository $jobs): JsonResponse
    {
        $tag = $request->query('tag');
        $startingAt = (int) $request->query('starting_at', 0);
        $limit = (int) $request->query('limit', 25);

        $jobIds = $tags->paginate($tag, $startingAt, $limit);

        $jobsList = $jobs->getJobs($jobIds, $startingAt)
            ->map(function ($job) {
                $job->payload = json_decode($job->payload);

                return $job;
            })
            ->values();

        return response()->json([
            'jobs' => $jobsList,
            'total' => $tags->count($tag),
        ]);
    }

    /**
     * 新增监控标签
     */
    public function monitorTag(Request $request): JsonResponse
    {
        $validated = $request->validate(['tag' => 'required|string']);

        dispatch(new MonitorTag($validated['tag']));

        return response()->json(['message' => 'ok']);
    }

    /**
     * 取消监控标签
     */
    public function stopMonitoringTag(string $tag): JsonResponse
    {
        dispatch(new StopMonitoringTag($tag));

        return response()->json(['message' => 'ok']);
    }

    /**
     * 任务指标列表
     */
    public function jobMetrics(MetricsRepository $metrics): JsonResponse
    {
        return response()->json($metrics->measuredJobs());
    }

    /**
     * 指定任务的指标快照
     */
    public function jobMetricsDetail(string $id, MetricsRepository $metrics): JsonResponse
    {
        $snapshots = collect($metrics->snapshotsForJob($id))
            ->map(function ($record) {
                $record->runtime = round($record->runtime / 1000, 3);
                $record->throughput = (int) $record->throughput;

                return $record;
            })
            ->values();

        return response()->json($snapshots);
    }

    /**
     * 队列指标列表
     */
    public function queueMetrics(MetricsRepository $metrics): JsonResponse
    {
        return response()->json($metrics->measuredQueues());
    }

    /**
     * 指定队列的指标快照
     */
    public function queueMetricsDetail(string $id, MetricsRepository $metrics): JsonResponse
    {
        $snapshots = collect($metrics->snapshotsForQueue($id))
            ->map(function ($record) {
                $record->runtime = round($record->runtime / 1000, 3);
                $record->throughput = (int) $record->throughput;

                return $record;
            })
            ->values();

        return response()->json($snapshots);
    }

    /**
     * 批处理列表
     */
    public function batches(Request $request, BatchRepository $batches): JsonResponse
    {
        try {
            $list = $request->query('query')
                ? $this->searchBatches($request, $batches)
                : $batches->get(50, $request->query('before_id'));
        } catch (QueryException) {
            $list = [];
        }

        return response()->json(['batches' => $list]);
    }

    /**
     * 批处理详情（含失败任务）
     */
    public function batchDetail(string $id, BatchRepository $batches, JobRepository $jobs): JsonResponse
    {
        $batch = $batches->find($id);
        $failedJobs = null;

        if ($batch) {
            $failedJobs = $jobs->getJobs($batch->failedJobIds);
        }

        return response()->json([
            'batch' => $batch,
            'failedJobs' => $failedJobs,
        ]);
    }

    /**
     * 重试批处理中的失败任务
     */
    public function retryBatch(string $id, BatchRepository $batches, JobRepository $jobs): JsonResponse
    {
        $batch = $batches->find($id);

        if ($batch) {
            $jobs->getJobs($batch->failedJobIds)
                ->reject(function ($job) {
                    $payload = json_decode($job->payload);

                    return isset($payload->retry_of);
                })
                ->each(function ($job) {
                    dispatch(new RetryFailedJob($job->id));
                });
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * 待处理任务列表
     */
    public function pendingJobs(Request $request, JobRepository $jobs): JsonResponse
    {
        $startingAt = (int) $request->query('starting_at', -1);

        $list = $jobs->getPending($startingAt)
            ->map(function ($job) {
                $job->payload = json_decode($job->payload);

                return $job;
            })
            ->values();

        return response()->json([
            'jobs' => $list,
            'total' => $jobs->countPending(),
        ]);
    }

    /**
     * 已完成任务列表
     */
    public function completedJobs(Request $request, JobRepository $jobs): JsonResponse
    {
        $startingAt = (int) $request->query('starting_at', -1);

        $list = $jobs->getCompleted($startingAt)
            ->map(function ($job) {
                $job->payload = json_decode($job->payload);

                return $job;
            })
            ->values();

        return response()->json([
            'jobs' => $list,
            'total' => $jobs->countCompleted(),
        ]);
    }

    /**
     * 静默任务列表
     */
    public function silencedJobs(Request $request, JobRepository $jobs): JsonResponse
    {
        $startingAt = (int) $request->query('starting_at', -1);

        $list = $jobs->getSilenced($startingAt)
            ->map(function ($job) {
                $job->payload = json_decode($job->payload);

                return $job;
            })
            ->values();

        return response()->json([
            'jobs' => $list,
            'total' => $jobs->countSilenced(),
        ]);
    }

    /**
     * 失败任务列表（可按标签筛选）
     */
    public function failedJobs(Request $request, JobRepository $jobs, TagRepository $tags): JsonResponse
    {
        $tag = $request->query('tag');

        if ($tag) {
            $jobIds = $tags->paginate(
                'failed:'.$tag,
                ((int) $request->query('starting_at', -1)) + 1,
                50
            );
            $startingAt = (int) $request->query('starting_at', 0);
            $list = $jobs->getJobs($jobIds, $startingAt)
                ->map(fn ($job) => $this->decodeFailedJob($job))
                ->values();
            $total = $tags->count('failed:'.$tag);
        } else {
            $list = $jobs->getFailed((int) $request->query('starting_at', -1))
                ->map(fn ($job) => $this->decodeFailedJob($job))
                ->values();
            $total = $jobs->countFailed();
        }

        return response()->json([
            'jobs' => $list,
            'total' => $total,
        ]);
    }

    /**
     * 失败任务详情
     */
    public function failedJobDetail(string $id, JobRepository $jobs): JsonResponse
    {
        $job = $jobs->getJobs([$id])
            ->map(fn ($j) => $this->decodeFailedJob($j))
            ->first();

        if (! $job) {
            abort(404, 'Job not found');
        }

        return response()->json($job);
    }

    /**
     * 重试失败任务
     */
    public function retryJob(string $id): JsonResponse
    {
        dispatch(new RetryFailedJob($id));

        return response()->json(['message' => 'ok']);
    }

    /**
     * 最近任务详情（已完成/失败/待处理均可）
     */
    public function jobDetail(string $id, JobRepository $jobs): JsonResponse
    {
        $job = $jobs->getJobs([$id])
            ->map(function ($j) {
                $j->payload = json_decode($j->payload);

                return $j;
            })
            ->first();

        if (! $job) {
            abort(404, 'Job not found');
        }

        return response()->json($job);
    }

    // ----------------------------------------------------------------
    //  内部辅助方法
    // ----------------------------------------------------------------

    /**
     * 获取所有监督器的进程总数
     */
    private function totalProcessCount(): int
    {
        $supervisors = app(SupervisorRepository::class)->all();

        return collect($supervisors)
            ->reduce(fn ($carry, $supervisor) => $carry + collect($supervisor->processes)->sum(), 0);
    }

    /**
     * 获取 Horizon 当前状态
     */
    private function currentStatus(): string
    {
        $masters = app(MasterSupervisorRepository::class)->all();

        if (! $masters) {
            return 'inactive';
        }

        return collect($masters)->every(fn ($master) => $master->status === 'paused') ? 'paused' : 'running';
    }

    /**
     * 获取已暂停的主监督器数量
     */
    private function totalPausedMasters(): int
    {
        $masters = app(MasterSupervisorRepository::class)->all();

        if (! $masters) {
            return 0;
        }

        return collect($masters)
            ->filter(fn ($master) => $master->status === 'paused')
            ->count();
    }

    /**
     * 搜索批处理
     */
    private function searchBatches(Request $request, BatchRepository $batches): array
    {
        $query = str_replace(['%', '_'], ['\%', '\_'], $request->query('query'));

        return DB::connection(config('queue.batching.database'))
            ->table(config('queue.batching.table', 'job_batches'))
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('id', 'like', "%{$query}%");
            })
            ->orderByDesc('id')
            ->limit(50)
            ->when($request->query('before_id'), fn ($q, $beforeId) => $q->where('id', '<', $beforeId))
            ->pluck('id')
            ->map(fn ($id) => $batches->find($id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * 解码失败任务（含异常栈与重试历史）
     */
    private function decodeFailedJob(object $job): object
    {
        $job->payload = json_decode($job->payload);
        $job->exception = mb_convert_encoding($job->exception, 'UTF-8');
        $job->context = json_decode($job->context ?? '');
        $job->retried_by = collect(! is_null($job->retried_by) ? json_decode($job->retried_by) : [])
            ->sortByDesc('retried_at')
            ->values();

        return $job;
    }
}
