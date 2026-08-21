<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Resources\Admin\PhoneCodeResource;
use App\Models\System\PhoneCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 后台手机验证码管理控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class PhoneCodeController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:phone-codes.index')->only(['index', 'show']);
    }

    /**
     * 验证码列表（分页）
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = per_page($request);

        $query = PhoneCode::query();

        if ($phone = $request->query('phone')) {
            $query->where('phone', 'like', "%{$phone}%");
        }

        if ($scene = $request->query('scene')) {
            $query->where('scene', $scene);
        }

        if ($state = $request->query('state')) {
            $query->where('state', (int) $state);
        }

        $items = $query->orderByDesc('send_at')->orderByDesc('id')->paginate($perPage);

        return PhoneCodeResource::collection($items);
    }

    /**
     * 获取验证码详情
     */
    public function show(int $id): PhoneCodeResource
    {
        return new PhoneCodeResource(PhoneCode::findOrFail($id));
    }
}
