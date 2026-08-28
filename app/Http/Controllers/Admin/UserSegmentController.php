<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SegmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserSegment\SegmentAttachRequest;
use App\Http\Requests\Admin\UserSegment\SegmentBatchAttachRequest;
use App\Http\Requests\Admin\UserSegment\SegmentIndexRequest;
use App\Http\Requests\Admin\UserSegment\SegmentSaveRequest;
use App\Http\Resources\Admin\SegmentListResource;
use App\Http\Resources\Admin\SegmentOptionResource;
use App\Http\Resources\Admin\SegmentResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use App\Models\UserSegment\Membership;
use App\Models\UserSegment\Segment;
use App\Services\Segment\SegmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

/**
 * 后台用户分群管理控制器
 *
 * 14 个端点：list / store / show / update / destroy / toggleStatus /
 *            sync / preview / options / users / attach / detach /
 *            batchAttach / batchDetach
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UserSegmentController extends Controller
{
    public function __construct(private readonly SegmentService $segments)
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:segments.index')->only([
            'index', 'show', 'preview', 'options', 'users',
        ]);
        $this->middleware('permission:segments.create')->only(['store']);
        $this->middleware('permission:segments.edit')->only([
            'update', 'toggleStatus', 'sync', 'attach', 'batchAttach',
        ]);
        $this->middleware('permission:segments.delete')->only(['destroy']);
        $this->middleware('permission:segments.assign')->only(['detach', 'batchDetach']);
    }

    /**
     * 分群列表（分页，支持 keyword/type/group/status 筛选）
     */
    public function index(SegmentIndexRequest $request): AnonymousResourceCollection
    {
        $query = Segment::query();

        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        if ($type = $request->query('type')) {
            $query->where('type', (int) $type);
        }

        if ($group = $request->query('group')) {
            $query->where('group', $group);
        }

        if ($request->has('status')) {
            $query->where('status', (int) $request->query('status'));
        }

        $items = $query->orderBy('sort')->orderByDesc('id')->paginate(per_page($request));

        return SegmentListResource::collection($items);
    }

    /**
     * 新建分群
     */
    public function store(SegmentSaveRequest $request): SegmentResource
    {
        $segment = Segment::query()->create($request->validated());

        return new SegmentResource($segment);
    }

    /**
     * 分群详情（含 members 数）
     */
    public function show(int $id): SegmentResource
    {
        $segment = Segment::query()->withCount('memberships')->findOrFail($id);

        return new SegmentResource($segment);
    }

    /**
     * 更新分群
     *
     * 业务约束：
     *  - 规则分群（type=RULE）禁止修改 code 与 rule
     *  - 不允许从 RULE 改回 MANUAL（如有数据需求可手动迁移）
     */
    public function update(SegmentSaveRequest $request, int $id): SegmentResource
    {
        $segment = Segment::query()->findOrFail($id);
        $data = $request->validated();

        if ($segment->type === SegmentType::RULE) {
            unset($data['code'], $data['rule'], $data['type']);
        }

        $segment->update($data);
        $segment->loadCount('memberships');

        return new SegmentResource($segment);
    }

    /**
     * 删除分群（级联 user_segment_memberships）
     */
    public function destroy(int $id): JsonResponse
    {
        $segment = Segment::query()->findOrFail($id);
        $segment->delete();

        return response()->json(status: 204);
    }

    /**
     * 启用/停用切换
     */
    public function toggleStatus(int $id): SegmentResource
    {
        $segment = Segment::query()->findOrFail($id);
        $segment->update(['status' => ! $segment->status]);
        $segment->loadCount('memberships');

        return new SegmentResource($segment);
    }

    /**
     * 手动触发规则同步
     */
    public function sync(Request $request, int $id): JsonResponse
    {
        $segment = Segment::query()->findOrFail($id);

        try {
            $result = $this->segments->syncRuleSegment($segment);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    /**
     * 规则预览（不写库）
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $segment = Segment::query()->findOrFail($id);

        try {
            $result = $this->segments->previewRuleSegment($segment);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        // sample 里的 ID 转为用户概要（username/name/avatar）
        $users = [];
        if ($result['sample'] !== []) {
            $users = User::query()
                ->whereIn('id', $result['sample'])
                ->get(['id', 'username', 'name', 'avatar'])
                ->toArray();
        }

        return response()->json([
            'total' => $result['total'],
            'sample' => $users,
        ]);
    }

    /**
     * 启用 + 手动分群下拉（公告群发复用）
     */
    public function options(Request $request): AnonymousResourceCollection
    {
        $items = Segment::query()
            ->active()
            ->manual()
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get();

        return SegmentOptionResource::collection($items);
    }

    /**
     * 分群下的用户列表
     */
    public function users(Request $request, int $id): AnonymousResourceCollection
    {
        $segment = Segment::query()->findOrFail($id);

        $perPage = per_page($request);

        $query = User::query()
            ->whereHas('memberships', fn ($q) => $q->where('segment_id', $segment->id))
            ->with(['memberships' => fn ($q) => $q->where('segment_id', $segment->id)]);

        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        $items = $query->orderByDesc('id')->paginate($perPage);

        return UserResource::collection($items)->additional([
            'meta' => [
                'segment_id' => $segment->id,
                'segment_name' => $segment->name,
            ],
        ]);
    }

    /**
     * 单分群批量绑定用户
     *
     * > BATCH_LIMIT 自动入队，返回 202；否则同步执行返回 200。
     */
    public function attach(SegmentAttachRequest $request, int $id): JsonResponse
    {
        $segment = Segment::query()->findOrFail($id);
        $userIds = $request->validated('user_ids');
        $admin = $request->user('admin');

        // 超限：入队 + 返回 202
        if (count($userIds) > \App\Services\Segment\SegmentService::BATCH_LIMIT) {
            $task = $this->segments->dispatchTask(
                adminId: $admin?->id ?? 0,
                type: \App\Enums\SegmentTaskType::ATTACH,
                segmentIds: [$segment->id],
                userIds: $userIds,
            );

            return response()->json([
                'task_id' => $task->id,
                'status' => $task->status->value,
                'mode' => 'async',
                'message' => '任务已提交，完成后将通过站内信通知。',
            ], 202);
        }

        // 同步执行
        try {
            $added = $this->segments->attachUsers($segment, $userIds, Membership::SOURCE_MANUAL);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'added' => $added,
            'requested' => count($userIds),
            'mode' => 'sync',
        ]);
    }

    /**
     * 单分群批量解绑用户
     *
     * > BATCH_LIMIT 自动入队，返回 202；否则同步执行返回 200。
     */
    public function detach(SegmentAttachRequest $request, int $id): JsonResponse
    {
        $segment = Segment::query()->findOrFail($id);
        $userIds = $request->validated('user_ids');
        $admin = $request->user('admin');

        if (count($userIds) > \App\Services\Segment\SegmentService::BATCH_LIMIT) {
            $task = $this->segments->dispatchTask(
                adminId: $admin?->id ?? 0,
                type: \App\Enums\SegmentTaskType::DETACH,
                segmentIds: [$segment->id],
                userIds: $userIds,
            );

            return response()->json([
                'task_id' => $task->id,
                'status' => $task->status->value,
                'mode' => 'async',
                'message' => '任务已提交，完成后将通过站内信通知。',
            ], 202);
        }

        try {
            $removed = $this->segments->detachUsers($segment, $userIds);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'removed' => $removed,
            'requested' => count($userIds),
            'mode' => 'sync',
        ]);
    }

    /**
     * 笛卡尔批量打标
     *
     * 任一维度 > BATCH_LIMIT 自动入队，返回 202；否则同步执行返回 200。
     */
    public function batchAttach(SegmentBatchAttachRequest $request): JsonResponse
    {
        $segmentIds = $request->validated('segment_ids');
        $userIds = $request->validated('user_ids');
        $admin = $request->user('admin');

        if ($this->segments->shouldEnqueue($segmentIds, $userIds)) {
            $task = $this->segments->dispatchTask(
                adminId: $admin?->id ?? 0,
                type: \App\Enums\SegmentTaskType::ATTACH,
                segmentIds: $segmentIds,
                userIds: $userIds,
            );

            return response()->json([
                'task_id' => $task->id,
                'status' => $task->status->value,
                'mode' => 'async',
                'requested' => count($segmentIds) * count($userIds),
                'message' => '笛卡尔批量任务已提交，完成后将通过站内信通知。',
            ], 202);
        }

        try {
            $added = $this->segments->batchAttach($segmentIds, $userIds, Membership::SOURCE_MANUAL);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'added' => $added,
            'requested' => count($segmentIds) * count($userIds),
            'mode' => 'sync',
        ]);
    }

    /**
     * 笛卡尔批量解标
     *
     * 任一维度 > BATCH_LIMIT 自动入队，返回 202；否则同步执行返回 200。
     */
    public function batchDetach(SegmentBatchAttachRequest $request): JsonResponse
    {
        $segmentIds = $request->validated('segment_ids');
        $userIds = $request->validated('user_ids');
        $admin = $request->user('admin');

        if ($this->segments->shouldEnqueue($segmentIds, $userIds)) {
            $task = $this->segments->dispatchTask(
                adminId: $admin?->id ?? 0,
                type: \App\Enums\SegmentTaskType::DETACH,
                segmentIds: $segmentIds,
                userIds: $userIds,
            );

            return response()->json([
                'task_id' => $task->id,
                'status' => $task->status->value,
                'mode' => 'async',
                'requested' => count($segmentIds) * count($userIds),
                'message' => '笛卡尔批量解标任务已提交，完成后将通过站内信通知。',
            ], 202);
        }

        try {
            $removed = $this->segments->batchDetach($segmentIds, $userIds);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'removed' => $removed,
            'requested' => count($segmentIds) * count($userIds),
            'mode' => 'sync',
        ]);
    }
}
