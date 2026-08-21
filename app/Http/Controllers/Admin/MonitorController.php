<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\CacheInteractions as CacheInteractionsRecorder;
use Laravel\Pulse\Recorders\Concerns\Thresholds;
use Laravel\Pulse\Recorders\Exceptions as ExceptionsRecorder;
use Laravel\Pulse\Recorders\Queues as QueuesRecorder;
use Laravel\Pulse\Recorders\SlowJobs as SlowJobsRecorder;
use Laravel\Pulse\Recorders\SlowOutgoingRequests as SlowOutgoingRequestsRecorder;
use Laravel\Pulse\Recorders\SlowQueries as SlowQueriesRecorder;
use Laravel\Pulse\Recorders\SlowRequests as SlowRequestsRecorder;
use Laravel\Pulse\Support\CacheStoreResolver;
use Laravel\Reverb\Pulse\Recorders\ReverbConnections as ReverbConnectionsRecorder;
use Laravel\Reverb\Pulse\Recorders\ReverbMessages as ReverbMessagesRecorder;

/**
 * 后台性能监控控制器
 *
 * 将 Laravel Pulse 采集的数据以 JSON 形式暴露给前后端分离的管理后台，
 * 数据来源为 Pulse 的预聚合表（pulse_aggregates / pulse_values）。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class MonitorController extends Controller
{
    use Thresholds;

    /** @var list<string> 允许的统计周期 */
    private const array PERIODS = ['1_hour', '6_hours', '24_hours', '7_days'];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:pulse.index');
    }

    /**
     * 服务器资源（CPU / 内存 / 磁盘）
     */
    public function servers(Request $request): JsonResponse
    {
        $period = $this->period($request);

        $servers = $this->remember('servers', $period, function (CarbonInterval $interval) {
            $graphs = Pulse::graph(['cpu', 'memory'], 'avg', $interval);

            return Pulse::values('system')
                ->map(function (object $system, string $slug) use ($graphs): array {
                    $values = json_decode($system->value, flags: JSON_THROW_ON_ERROR);
                    $updatedAt = CarbonImmutable::createFromTimestamp($system->timestamp);

                    return [
                        'slug' => $slug,
                        'name' => (string) $values->name,
                        'cpu_current' => (int) $values->cpu,
                        'cpu' => $this->series($graphs->get($slug)?->get('cpu')),
                        'memory_current' => (int) $values->memory_used,
                        'memory_total' => (int) $values->memory_total,
                        'memory' => $this->series($graphs->get($slug)?->get('memory')),
                        'storage' => collect($values->storage)
                            ->map(fn (object $disk): array => [
                                'directory' => (string) $disk->directory,
                                'total' => (int) $disk->total,
                                'used' => (int) $disk->used,
                            ])
                            ->values()
                            ->all(),
                        'updated_at' => $updatedAt->toDateTimeString(),
                        'recently_reported' => $updatedAt->isAfter(now()->subSeconds(30)),
                    ];
                })
                ->sortBy('name')
                ->values()
                ->all();
        });

        return $this->respond($period, ['servers' => $servers]);
    }

    /**
     * 队列吞吐（入队 / 处理中 / 已处理 / 已重试 / 失败）
     */
    public function queues(Request $request): JsonResponse
    {
        $period = $this->period($request);
        $types = ['queued', 'processing', 'processed', 'released', 'failed'];

        $queues = $this->remember('queues', $period, function (CarbonInterval $interval) use ($types) {
            return Pulse::graph($types, 'count', $interval)
                ->map(function (Collection $readings, string $key) use ($types): array {
                    [$connection, $queue] = array_pad(explode(':', $key, 2), 2, '');

                    return [
                        'key' => $key,
                        'connection' => $connection,
                        'queue' => $queue,
                        ...collect($types)
                            ->mapWithKeys(fn (string $type): array => [
                                $type => $this->series($readings->get($type)),
                            ])
                            ->all(),
                    ];
                })
                ->values()
                ->all();
        });

        return $this->respond($period, [
            'queues' => $queues,
            'config' => $this->recorderConfig(QueuesRecorder::class),
        ]);
    }

    /**
     * Reverb WebSocket 连接数（平均 / 峰值）
     */
    public function reverbConnections(Request $request): JsonResponse
    {
        $period = $this->period($request);

        $apps = $this->remember('reverb-connections', $period, function (CarbonInterval $interval): array {
            $max = Pulse::graph(['reverb_connections'], 'max', $interval);
            $avg = Pulse::graph(['reverb_connections'], 'avg', $interval);

            return $max->map(function (Collection $readings, string $app) use ($avg): array {
                $current = $avg->get($app)?->get('reverb_connections')?->last();

                return [
                    'app_id' => $app,
                    'current' => $current === null ? null : (float) $current,
                    'peak' => (float) ($readings->get('reverb_connections')?->max() ?? 0),
                    'avg' => $this->series($avg->get($app)?->get('reverb_connections')),
                    'max' => $this->series($readings->get('reverb_connections')),
                ];
            })->values()->all();
        });

        return $this->respond($period, [
            'apps' => $apps,
            'config' => $this->recorderConfig(ReverbConnectionsRecorder::class),
        ]);
    }

    /**
     * Reverb 消息吞吐（发送 / 接收）
     */
    public function reverbMessages(Request $request): JsonResponse
    {
        $period = $this->period($request);

        $apps = $this->remember('reverb-messages', $period, function (CarbonInterval $interval): array {
            return Pulse::graph(
                ['reverb_message:sent', 'reverb_message:received'],
                'count',
                $interval,
            )->map(function (Collection $readings, string $app): array {
                $sent = $readings->get('reverb_message:sent');
                $received = $readings->get('reverb_message:received');

                return [
                    'app_id' => $app,
                    'sent_total' => (int) ($sent?->sum() ?? 0),
                    'received_total' => (int) ($received?->sum() ?? 0),
                    'sent' => $this->series($sent),
                    'received' => $this->series($received),
                ];
            })->values()->all();
        });

        return $this->respond($period, [
            'apps' => $apps,
            'config' => $this->recorderConfig(ReverbMessagesRecorder::class),
        ]);
    }

    /**
     * 缓存命中率
     */
    public function cache(Request $request): JsonResponse
    {
        $period = $this->period($request);

        $data = $this->remember('cache', $period, function (CarbonInterval $interval): array {
            $totals = Pulse::aggregateTotal(['cache_hit', 'cache_miss'], 'count', $interval);

            $keys = Pulse::aggregateTypes(['cache_hit', 'cache_miss'], 'count', $interval)
                ->map(fn (object $row): array => [
                    'key' => $row->key,
                    'hits' => (int) ($row->cache_hit ?? 0),
                    'misses' => (int) ($row->cache_miss ?? 0),
                ])
                ->values()
                ->all();

            return [
                'all' => [
                    'hits' => (int) ($totals['cache_hit'] ?? 0),
                    'misses' => (int) ($totals['cache_miss'] ?? 0),
                ],
                'keys' => $keys,
            ];
        });

        return $this->respond($period, [
            ...$data,
            'config' => $this->recorderConfig(CacheInteractionsRecorder::class),
        ]);
    }

    /**
     * 异常统计
     */
    public function exceptions(Request $request): JsonResponse
    {
        $period = $this->period($request);
        $orderBy = $this->orderBy($request, ['count', 'latest'], 'count');

        $exceptions = $this->remember("exceptions:{$orderBy}", $period, function (CarbonInterval $interval) use ($orderBy) {
            return Pulse::aggregate('exception', ['max', 'count'], $interval, $orderBy === 'latest' ? 'max' : 'count')
                ->map(function (object $row): array {
                    [$class, $location] = json_decode($row->key, flags: JSON_THROW_ON_ERROR);

                    return [
                        'class' => $class,
                        'location' => $location,
                        'latest' => CarbonImmutable::createFromTimestamp((int) $row->max)->toDateTimeString(),
                        'count' => (int) $row->count,
                    ];
                })
                ->values()
                ->all();
        });

        return $this->respond($period, [
            'order_by' => $orderBy,
            'exceptions' => $exceptions,
            'config' => $this->recorderConfig(ExceptionsRecorder::class),
        ]);
    }

    /**
     * 慢查询
     */
    public function slowQueries(Request $request): JsonResponse
    {
        $period = $this->period($request);
        $orderBy = $this->orderBy($request, ['slowest', 'count'], 'slowest');

        $slowQueries = $this->remember("slow-queries:{$orderBy}", $period, function (CarbonInterval $interval) use ($orderBy) {
            return Pulse::aggregate('slow_query', ['max', 'count'], $interval, $orderBy === 'count' ? 'count' : 'max')
                ->map(function (object $row): array {
                    [$sql, $location] = json_decode($row->key, flags: JSON_THROW_ON_ERROR);

                    return [
                        'sql' => $sql,
                        'location' => $location,
                        'slowest' => (int) $row->max,
                        'count' => (int) $row->count,
                        'threshold' => $this->threshold($sql, SlowQueriesRecorder::class),
                    ];
                })
                ->values()
                ->all();
        });

        return $this->respond($period, [
            'order_by' => $orderBy,
            'slow_queries' => $slowQueries,
            'config' => $this->recorderConfig(SlowQueriesRecorder::class),
        ]);
    }

    /**
     * 慢请求
     */
    public function slowRequests(Request $request): JsonResponse
    {
        $period = $this->period($request);
        $orderBy = $this->orderBy($request, ['slowest', 'count'], 'slowest');

        $slowRequests = $this->remember("slow-requests:{$orderBy}", $period, function (CarbonInterval $interval) use ($orderBy) {
            return Pulse::aggregate('slow_request', ['max', 'count'], $interval, $orderBy === 'count' ? 'count' : 'max')
                ->map(function (object $row): array {
                    [$method, $uri, $action] = json_decode($row->key, flags: JSON_THROW_ON_ERROR);

                    return [
                        'method' => $method,
                        'uri' => $uri,
                        'action' => $action,
                        'slowest' => (int) $row->max,
                        'count' => (int) $row->count,
                        'threshold' => $this->threshold($uri, SlowRequestsRecorder::class),
                    ];
                })
                ->values()
                ->all();
        });

        return $this->respond($period, [
            'order_by' => $orderBy,
            'slow_requests' => $slowRequests,
            'config' => $this->recorderConfig(SlowRequestsRecorder::class),
        ]);
    }

    /**
     * 慢任务
     */
    public function slowJobs(Request $request): JsonResponse
    {
        $period = $this->period($request);
        $orderBy = $this->orderBy($request, ['slowest', 'count'], 'slowest');

        $slowJobs = $this->remember("slow-jobs:{$orderBy}", $period, function (CarbonInterval $interval) use ($orderBy) {
            return Pulse::aggregate('slow_job', ['max', 'count'], $interval, $orderBy === 'count' ? 'count' : 'max')
                ->map(fn (object $row): array => [
                    'job' => $row->key,
                    'slowest' => (int) $row->max,
                    'count' => (int) $row->count,
                    'threshold' => $this->threshold($row->key, SlowJobsRecorder::class),
                ])
                ->values()
                ->all();
        });

        return $this->respond($period, [
            'order_by' => $orderBy,
            'slow_jobs' => $slowJobs,
            'config' => $this->recorderConfig(SlowJobsRecorder::class),
        ]);
    }

    /**
     * 慢外部请求
     */
    public function slowOutgoingRequests(Request $request): JsonResponse
    {
        $period = $this->period($request);
        $orderBy = $this->orderBy($request, ['slowest', 'count'], 'slowest');

        $requests = $this->remember("slow-outgoing-requests:{$orderBy}", $period, function (CarbonInterval $interval) use ($orderBy) {
            return Pulse::aggregate('slow_outgoing_request', ['max', 'count'], $interval, $orderBy === 'count' ? 'count' : 'max')
                ->map(function (object $row): array {
                    [$method, $uri] = json_decode($row->key, flags: JSON_THROW_ON_ERROR);

                    return [
                        'method' => $method,
                        'uri' => $uri,
                        'slowest' => (int) $row->max,
                        'count' => (int) $row->count,
                        'threshold' => $this->threshold($uri, SlowOutgoingRequestsRecorder::class),
                    ];
                })
                ->values()
                ->all();
        });

        return $this->respond($period, [
            'order_by' => $orderBy,
            'slow_outgoing_requests' => $requests,
            'config' => $this->recorderConfig(SlowOutgoingRequestsRecorder::class),
        ]);
    }

    /**
     * 用户使用量排行（请求数 / 慢请求数 / 任务数）
     */
    public function usage(Request $request): JsonResponse
    {
        $period = $this->period($request);

        $validated = $request->validate([
            'type' => ['sometimes', 'in:requests,slow_requests,jobs'],
        ]);
        $type = $validated['type'] ?? 'requests';

        $users = $this->remember("usage:{$type}", $period, function (CarbonInterval $interval) use ($type) {
            $counts = Pulse::aggregate(match ($type) {
                'slow_requests' => 'slow_user_request',
                'jobs' => 'user_job',
                default => 'user_request',
            }, 'count', $interval, limit: 10);

            $users = Pulse::resolveUsers($counts->pluck('key'));

            return $counts->map(function (object $row) use ($users): array {
                $user = $users->find($row->key);

                return [
                    'key' => $row->key,
                    'name' => $user->name ?? null,
                    'extra' => $user->extra ?? null,
                    'avatar' => $user->avatar ?? null,
                    'count' => (int) $row->count,
                ];
            })
                ->values()
                ->all();
        });

        return $this->respond($period, [
            'type' => $type,
            'users' => $users,
        ]);
    }

    /**
     * 解析并校验统计周期
     */
    private function period(Request $request): string
    {
        $validated = $request->validate([
            'period' => ['sometimes', 'in:'.implode(',', self::PERIODS)],
        ]);

        return $validated['period'] ?? '1_hour';
    }

    /**
     * 解析并校验排序方式
     *
     * @param  list<string>  $allowed
     */
    private function orderBy(Request $request, array $allowed, string $default): string
    {
        $validated = $request->validate([
            'order_by' => ['sometimes', 'in:'.implode(',', $allowed)],
        ]);

        return $validated['order_by'] ?? $default;
    }

    /**
     * 将周期转换为时间间隔
     */
    private function periodAsInterval(string $period): CarbonInterval
    {
        return CarbonInterval::hours(match ($period) {
            '6_hours' => 6,
            '24_hours' => 24,
            '7_days' => 168,
            default => 1,
        });
    }

    /**
     * 缓存查询结果，避免面板轮询压垮数据库
     *
     * 与 Pulse 官方面板保持一致，默认缓存 5 秒。
     */
    private function remember(string $key, string $period, callable $query): mixed
    {
        return app(CacheStoreResolver::class)->store()->remember(
            "laravel:pulse:admin-monitor:{$key}:{$period}",
            5,
            fn () => $query($this->periodAsInterval($period)),
        );
    }

    /**
     * 将图表读数转换为前端友好的时间序列
     *
     * @param  Collection<string, int|float|null>|null  $readings
     * @return list<array{time: string, value: float|null}>
     */
    private function series(?Collection $readings): array
    {
        return collect($readings)
            ->map(fn ($value, string $time): array => [
                'time' => $time,
                'value' => $value === null ? null : (float) $value,
            ])
            ->values()
            ->all();
    }

    /**
     * 读取采集器配置（阈值、采样率）
     *
     * @return array<string, mixed>
     */
    private function recorderConfig(string $recorder): array
    {
        $config = Config::get("pulse.recorders.{$recorder}", []);

        return [
            'threshold' => $config['threshold'] ?? null,
            'sample_rate' => $config['sample_rate'] ?? null,
        ];
    }

    /**
     * 统一响应结构
     *
     * @param  array<string, mixed>  $data
     */
    private function respond(string $period, array $data): JsonResponse
    {
        return response()->json([
            'period' => $period,
            ...$data,
        ]);
    }
}
