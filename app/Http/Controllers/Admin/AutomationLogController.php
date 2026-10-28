<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Automation\AutomationLogIndexRequest;
use App\Http\Resources\Admin\AutomationLogResource;
use App\Models\Automation\AutomationLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台自动化运营触发日志控制器（运营管理，只读）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationLogController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:automation-logs.index')->only(['index', 'show']);
    }

    /**
     * 触发日志列表（分页，支持规则/用户/状态/事件日期范围筛选）
     */
    public function index(AutomationLogIndexRequest $request): AnonymousResourceCollection
    {
        $items = AutomationLog::query()
            ->with(['rule:id,name,trigger_type', 'user:id,name,username,avatar', 'festival:id,name'])
            ->filter($request->validated())
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return AutomationLogResource::collection($items);
    }

    /**
     * 触发日志详情
     */
    public function show(int $id): AutomationLogResource
    {
        $log = AutomationLog::query()
            ->with(['rule:id,name,trigger_type', 'user:id,name,username,avatar', 'festival:id,name'])
            ->findOrFail($id);

        return new AutomationLogResource($log);
    }
}
