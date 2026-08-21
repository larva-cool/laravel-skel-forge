<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AuditLog\AuditLogIndexRequest;
use App\Http\Resources\Admin\AuditLogResource;
use App\Models\System\AuditLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台操作审计日志控制器（只读）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AuditLogController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:audit-logs.index')->only(['index', 'show']);
    }

    /**
     * 审计日志列表（分页）
     */
    public function index(AuditLogIndexRequest $request): AnonymousResourceCollection
    {
        $logs = AuditLog::query()
            ->filter($request->filters())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return AuditLogResource::collection($logs);
    }

    /**
     * 审计日志详情
     */
    public function show(int $id): AuditLogResource
    {
        return new AuditLogResource(AuditLog::query()->findOrFail($id));
    }
}
