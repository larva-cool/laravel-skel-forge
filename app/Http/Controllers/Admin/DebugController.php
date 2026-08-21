<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Debug\DebugIndexRequest;
use App\Http\Requests\Admin\Debug\DebugTagRequest;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Laravel\Telescope\Contracts\ClearableRepository;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\EntryUpdate;
use Laravel\Telescope\Storage\EntryQueryOptions;
use Laravel\Telescope\Watchers;

/**
 * 后台调试面板控制器
 *
 * 将 Laravel Telescope 采集的数据以统一的 GET/JSON 形式暴露给前后端分离的管理后台，
 * 替代官方基于 session 的 Blade SPA 接口。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class DebugController extends Controller
{
    /**
     * 缓存中标记暂停记录的键名
     */
    private const string PAUSE_KEY = 'telescope:pause-recording';

    /**
     * 条目类型与 Watcher 的映射，用于返回采集状态
     *
     * @var array<string, class-string>
     */
    private const array WATCHERS = [
        EntryType::BATCH => Watchers\BatchWatcher::class,
        EntryType::CACHE => Watchers\CacheWatcher::class,
        EntryType::CLIENT_REQUEST => Watchers\ClientRequestWatcher::class,
        EntryType::COMMAND => Watchers\CommandWatcher::class,
        EntryType::DUMP => Watchers\DumpWatcher::class,
        EntryType::EVENT => Watchers\EventWatcher::class,
        EntryType::EXCEPTION => Watchers\ExceptionWatcher::class,
        EntryType::GATE => Watchers\GateWatcher::class,
        EntryType::JOB => Watchers\JobWatcher::class,
        EntryType::LOG => Watchers\LogWatcher::class,
        EntryType::MAIL => Watchers\MailWatcher::class,
        EntryType::MODEL => Watchers\ModelWatcher::class,
        EntryType::NOTIFICATION => Watchers\NotificationWatcher::class,
        EntryType::QUERY => Watchers\QueryWatcher::class,
        EntryType::REDIS => Watchers\RedisWatcher::class,
        EntryType::REQUEST => Watchers\RequestWatcher::class,
        EntryType::SCHEDULED_TASK => Watchers\ScheduleWatcher::class,
        EntryType::VIEW => Watchers\ViewWatcher::class,
    ];

    /**
     * Constructor.
     */
    public function __construct(protected EntriesRepository $entries)
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:debug.index')->only(['index', 'show', 'tags']);
        $this->middleware('permission:debug.manage')->only(['resolve', 'monitor', 'unmonitor', 'toggleRecording', 'destroy']);
    }

    /**
     * 获取指定类型的条目列表
     *
     * 采用 Telescope 原生的游标分页：以 sequence 倒序，通过 before 参数向后翻页。
     */
    public function index(DebugIndexRequest $request): JsonResponse
    {
        $type = (string) $request->validated('type');

        $entries = $this->entries->get($type, $request->toQueryOptions());

        return response()->json([
            'type' => $type,
            'status' => $this->status($type),
            'entries' => $entries,
            'next_before' => $entries->last()?->sequence,
        ]);
    }

    /**
     * 获取条目详情及其所属批次的全部条目
     */
    public function show(string $id): JsonResponse
    {
        $entry = $this->findEntry($id);

        return response()->json([
            'entry' => $entry,
            'batch' => $this->entries->get(null, EntryQueryOptions::forBatchId($entry->batchId)->limit(-1)),
        ]);
    }

    /**
     * 将异常标记为已解决
     */
    public function resolve(string $id): JsonResponse
    {
        $entry = $this->findEntry($id);

        if ($entry->type !== EntryType::EXCEPTION) {
            abort(422, __('admin.debug_entry_not_found'));
        }

        $this->entries->update(collect([
            new EntryUpdate($entry->id, $entry->type, [
                'resolved_at' => Carbon::now()->toDateTimeString(),
            ]),
        ]));

        return response()->json([
            'message' => __('admin.debug_resolve_success'),
            'entry' => $this->entries->find($id),
        ]);
    }

    /**
     * 获取正在监控的标签列表
     */
    public function tags(): JsonResponse
    {
        return response()->json(['tags' => $this->entries->monitoring()]);
    }

    /**
     * 开始监控指定标签
     */
    public function monitor(DebugTagRequest $request): JsonResponse
    {
        $this->entries->monitor([$request->validated('tag')]);

        return response()->json([
            'message' => __('admin.debug_monitor_success'),
            'tags' => $this->entries->monitoring(),
        ]);
    }

    /**
     * 停止监控指定标签
     */
    public function unmonitor(DebugTagRequest $request): JsonResponse
    {
        $this->entries->stopMonitoring([$request->validated('tag')]);

        return response()->json([
            'message' => __('admin.debug_unmonitor_success'),
            'tags' => $this->entries->monitoring(),
        ]);
    }

    /**
     * 切换记录开关（暂停/恢复采集）
     */
    public function toggleRecording(CacheRepository $cache): JsonResponse
    {
        $paused = (bool) $cache->get(self::PAUSE_KEY);

        if ($paused) {
            $cache->forget(self::PAUSE_KEY);
        } else {
            $cache->put(self::PAUSE_KEY, true, now()->addDays(30));
        }

        return response()->json([
            'message' => __($paused ? 'admin.debug_recording_resumed' : 'admin.debug_recording_paused'),
            'paused' => ! $paused,
        ]);
    }

    /**
     * 清空全部调试记录
     */
    public function destroy(ClearableRepository $storage): JsonResponse
    {
        $storage->clear();

        return response()->json(['message' => __('admin.debug_clear_success')]);
    }

    /**
     * 查询条目，不存在时返回 404
     */
    private function findEntry(string $id): EntryResult
    {
        try {
            return $this->entries->find($id)->generateAvatar();
        } catch (ModelNotFoundException) {
            abort(404, __('admin.debug_entry_not_found'));
        }
    }

    /**
     * 判断指定类型的采集状态
     *
     * 与官方面板保持一致：disabled（总开关关闭）、paused（暂停记录）、
     * off（对应 watcher 未启用）、enabled（正常采集）。
     */
    private function status(string $type): string
    {
        if (! config('telescope.enabled', false)) {
            return 'disabled';
        }

        if (cache(self::PAUSE_KEY, false)) {
            return 'paused';
        }

        $watcher = config('telescope.watchers.'.self::WATCHERS[$type]);

        if (! $watcher || (is_array($watcher) && ! ($watcher['enabled'] ?? false))) {
            return 'off';
        }

        return 'enabled';
    }
}
