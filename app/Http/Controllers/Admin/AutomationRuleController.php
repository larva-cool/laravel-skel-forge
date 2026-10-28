<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AutomationChannel;
use App\Enums\AutomationRewardType;
use App\Enums\AutomationTriggerType;
use App\Http\Requests\Admin\Automation\AutomationRuleIndexRequest;
use App\Http\Requests\Admin\Automation\AutomationRuleSaveRequest;
use App\Http\Resources\Admin\AutomationRuleResource;
use App\Models\Automation\AutomationRule;
use App\Services\Automation\AutomationEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * 后台自动化运营规则管理控制器（运营管理）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationRuleController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:automation-rules.index')->only(['index', 'show', 'meta']);
        $this->middleware('permission:automation-rules.create')->only(['store']);
        $this->middleware('permission:automation-rules.edit')->only(['update', 'toggleStatus', 'trigger']);
        $this->middleware('permission:automation-rules.delete')->only(['destroy']);
    }

    /**
     * 规则表单元数据（触发类型/奖励类型/触达渠道选项）
     */
    public function meta(): JsonResponse
    {
        return response()->json([
            'trigger_types' => AutomationTriggerType::cases(),
            'reward_types' => AutomationRewardType::cases(),
            'channels' => AutomationChannel::cases(),
        ]);
    }

    /**
     * 规则列表（分页，支持关键词/类型/状态筛选）
     */
    public function index(AutomationRuleIndexRequest $request): AnonymousResourceCollection
    {
        $items = AutomationRule::query()
            ->with(['segment:id,name', 'admin:id,name'])
            ->filter($request->validated())
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return AutomationRuleResource::collection($items);
    }

    /**
     * 创建规则
     */
    public function store(AutomationRuleSaveRequest $request): AutomationRuleResource
    {
        $rule = AutomationRule::query()->create($request->validated());

        return new AutomationRuleResource($rule);
    }

    /**
     * 规则详情
     */
    public function show(int $id): AutomationRuleResource
    {
        $rule = AutomationRule::query()->with(['segment:id,name', 'admin:id,name'])->findOrFail($id);

        return new AutomationRuleResource($rule);
    }

    /**
     * 更新规则
     */
    public function update(AutomationRuleSaveRequest $request, int $id): AutomationRuleResource
    {
        $rule = AutomationRule::query()->findOrFail($id);
        $rule->update($request->validated());

        return new AutomationRuleResource($rule->fresh());
    }

    /**
     * 切换规则启用状态
     */
    public function toggleStatus(int $id): AutomationRuleResource
    {
        $rule = AutomationRule::query()->findOrFail($id);
        $rule->update(['status' => ! $rule->status]);

        return new AutomationRuleResource($rule->fresh());
    }

    /**
     * 手动触发规则（忽略 send_time 时间窗，可指定运行日期补跑）
     */
    public function trigger(int $id, AutomationEngineService $engine): JsonResponse
    {
        $rule = AutomationRule::query()->active()->findOrFail($id);

        $date = request()->input('date');
        $runDate = null;
        if (is_string($date) && $date !== '') {
            try {
                $runDate = Carbon::createFromFormat('Y-m-d', $date);
            } catch (\Throwable) {
                $runDate = null;
            }

            if (! $runDate instanceof Carbon || $runDate->format('Y-m-d') !== $date) {
                validation_exception('date', '日期格式错误，应为 Y-m-d');
            }
        }

        $summaries = $engine->run($runDate, $rule->id, true);

        return response()->json([
            'summary' => $summaries[0] ?? null,
        ]);
    }

    /**
     * 删除规则
     */
    public function destroy(int $id): JsonResponse
    {
        $rule = AutomationRule::query()->findOrFail($id);
        $rule->delete();

        return response()->json(status: 204);
    }
}
