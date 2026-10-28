<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Automation\AutomationFestivalIndexRequest;
use App\Http\Requests\Admin\Automation\AutomationFestivalSaveRequest;
use App\Http\Resources\Admin\AutomationFestivalResource;
use App\Models\Automation\AutomationFestival;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台自动化运营节日管理控制器（运营管理）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationFestivalController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:automation-festivals.index')->only(['index', 'show', 'options']);
        $this->middleware('permission:automation-festivals.create')->only(['store']);
        $this->middleware('permission:automation-festivals.edit')->only(['update', 'toggleStatus']);
        $this->middleware('permission:automation-festivals.delete')->only(['destroy']);
    }

    /**
     * 节日列表（分页，支持关键词/日期类型/状态筛选）
     */
    public function index(AutomationFestivalIndexRequest $request): AnonymousResourceCollection
    {
        $items = AutomationFestival::query()
            ->filter($request->validated())
            ->orderBy('sort')
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return AutomationFestivalResource::collection($items);
    }

    /**
     * 启用节日选项（供规则表单选择节日范围）
     */
    public function options(): JsonResponse
    {
        $items = AutomationFestival::query()
            ->active()
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'date_type', 'month', 'day']);

        return response()->json($items->map(fn (AutomationFestival $festival) => [
            'id' => $festival->id,
            'name' => $festival->name,
            'date_type' => $festival->date_type->value,
            'date_type_label' => $festival->date_type->label(),
            'month' => $festival->month,
            'day' => $festival->day,
        ]));
    }

    /**
     * 创建节日
     */
    public function store(AutomationFestivalSaveRequest $request): AutomationFestivalResource
    {
        $festival = AutomationFestival::query()->create($request->validated());

        return new AutomationFestivalResource($festival);
    }

    /**
     * 节日详情
     */
    public function show(int $id): AutomationFestivalResource
    {
        $festival = AutomationFestival::query()->findOrFail($id);

        return new AutomationFestivalResource($festival);
    }

    /**
     * 更新节日
     */
    public function update(AutomationFestivalSaveRequest $request, int $id): AutomationFestivalResource
    {
        $festival = AutomationFestival::query()->findOrFail($id);
        $festival->update($request->validated());

        return new AutomationFestivalResource($festival->fresh());
    }

    /**
     * 切换节日启用状态
     */
    public function toggleStatus(int $id): AutomationFestivalResource
    {
        $festival = AutomationFestival::query()->findOrFail($id);
        $festival->update(['status' => ! $festival->status]);

        return new AutomationFestivalResource($festival->fresh());
    }

    /**
     * 删除节日
     */
    public function destroy(int $id): JsonResponse
    {
        $festival = AutomationFestival::query()->findOrFail($id);
        $festival->delete();

        return response()->json(status: 204);
    }
}
