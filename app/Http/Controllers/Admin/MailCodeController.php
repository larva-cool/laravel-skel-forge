<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Resources\Admin\MailCodeResource;
use App\Models\System\MailCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台邮件验证码管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class MailCodeController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:mail-codes.index')->only(['index', 'show']);
    }

    /**
     * 验证码列表（分页）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = MailCode::query();

        if ($email = $request->query('email')) {
            $query->where('email', 'like', "%{$email}%");
        }

        if ($state = $request->query('state')) {
            $query->where('state', (int) $state);
        }

        $items = $query->orderByDesc('send_at')->orderByDesc('id')->paginate($perPage);

        return MailCodeResource::collection($items);
    }

    /**
     * 获取验证码详情
     */
    public function show(int $id): MailCodeResource
    {
        return new MailCodeResource(MailCode::findOrFail($id));
    }
}
