<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Resources\Admin\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * 通知接口
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class NotificationController extends Controller
{
    /**
     * NotificationController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * 通知列表
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = \per_page($request);

        $query = $request->user()->notifications();

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        $notifications = $query->paginate($perPage);

        return NotificationResource::collection($notifications);
    }

    /**
     * 未读通知列表
     */
    public function unread(Request $request): AnonymousResourceCollection
    {
        $perPage = \per_page($request);

        $query = $request->user()->unreadNotifications();

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        $notifications = $query->paginate($perPage);

        return NotificationResource::collection($notifications);
    }

    /**
     * 标记所有未读通知为已读
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => Carbon::now()]);

        return response()->json(['message' => __('admin.notification_mark_all_read_success')]);
    }

    /**
     * 标记指定未读通知为已读
     *
     * @codeCoverageIgnore
     */
    public function markAsRead(Request $request): JsonResponse
    {
        if ($request->has('id')) {
            $request->user()->unreadNotifications()->where('id',
                $request->post('id'))->update(['read_at' => Carbon::now()]);
        }

        return response()->json(['message' => __('admin.notification_mark_read_success')]);
    }

    /**
     * 清空所有已读通知
     *
     * @codeCoverageIgnore
     */
    public function clearRead(Request $request)
    {
        $request->user()->readNotifications()->delete();

        return response()->noContent();
    }
}
