<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\LoginHistory\LoginHistoryIndexRequest;
use App\Http\Resources\Admin\GlobalLoginHistoryResource;
use App\Models\System\LoginHistory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台全局登录历史控制器（只读）
 *
 * 跨管理员与前台用户的登录日志查询，支持按归属主体、IP、结果、时间范围筛选。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class LoginHistoryController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:login-histories.index')->only(['index']);
    }

    /**
     * 全局登录历史列表（分页）
     */
    public function index(LoginHistoryIndexRequest $request): AnonymousResourceCollection
    {
        $histories = LoginHistory::query()
            ->with('user')
            ->filter($request->filters())
            ->orderByDesc('login_at')
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return GlobalLoginHistoryResource::collection($histories);
    }
}
