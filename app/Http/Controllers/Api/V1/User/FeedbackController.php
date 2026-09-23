<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\Feedback\FeedbackStoreRequest;
use App\Http\Resources\Api\V1\FeedbackResource;
use App\Models\System\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 用户反馈接口（用户端）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class FeedbackController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // 用户反馈接口允许游客提交，若已登录则自动绑定用户
        $this->middleware('auth:sanctum')->only(['my']);
    }

    /**
     * 提交用户反馈
     */
    public function store(FeedbackStoreRequest $request): JsonResponse
    {
        $feedback = Feedback::create($request->validated());

        return response()->json([
            'code' => $feedback->code,
            'message' => __('system.feedback_submit_success'),
        ], 201);
    }

    /**
     * 我的反馈列表（登录用户）
     */
    public function my(Request $request): JsonResponse
    {
        $perPage = per_page($request);
        $user = $request->user();

        $items = Feedback::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        return FeedbackResource::collection($items)->response();
    }
}
