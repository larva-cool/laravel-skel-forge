<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\ModifyMailRequest;
use App\Http\Requests\Api\V1\User\ModifyPasswordRequest;
use App\Http\Requests\Api\V1\User\ModifyPhoneRequest;
use App\Http\Requests\Api\V1\User\ModifyProfileRequest;
use App\Http\Requests\Api\V1\User\ModifyUsernameRequest;
use App\Http\Resources\Api\V1\CoinResource;
use App\Http\Resources\Api\V1\LoginHistoryResource;
use App\Http\Resources\Api\V1\PointResource;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * 用户控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UserController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * 获取基本资料
     */
    public function baseProfile(Request $request): UserResource
    {
        $user = $request->user();
        $user->load('inviteCode');
        return new UserResource($user);
    }

    /**
     * 修改用户名
     */
    public function modifyUsername(ModifyUsernameRequest $request): JsonResponse
    {
        $request->user()->resetUsername($request->username);

        return response()->json(['message' => __('user.username_modification_completed')]);
    }

    /**
     * 修改邮箱
     */
    public function modifyEMail(ModifyMailRequest $request): JsonResponse
    {
        $request->user()->resetEmail($request->email);

        return response()->json(['message' => __('user.email_modification_completed')]);
    }

    /**
     * 修改手机号码
     */
    public function modifyPhone(ModifyPhoneRequest $request): JsonResponse
    {
        $request->user()->resetPhone($request->phone);

        return response()->json(['message' => __('user.phone_modification_completed')]);
    }

    /**
     * 修改个人资料
     */
    public function modifyProfile(ModifyProfileRequest $request): UserResource
    {
        $request->user()->update(['name' => $request->name]);
        $request->user()->profile->update($request->except(['name']));

        return new UserResource($request->user());
    }

    /**
     * 修改密码接口
     */
    public function modifyPassword(ModifyPasswordRequest $request): JsonResponse
    {
        $request->user()->resetPassword($request->password);

        return response()->json(['message' => __('user.password_reset_complete')]);
    }

    /**
     * 发送验证邮件
     */
    public function sendVerificationMail(Request $request): JsonResponse
    {
        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => __('user.send_complete')]);
    }

    /**
     * 获取登录历史
     */
    public function loginHistories(Request $request): AnonymousResourceCollection
    {
        $items = $request->user()->loginHistories()->orderByDesc('id')->paginate(per_page($request));

        return LoginHistoryResource::collection($items);
    }

    /**
     * 获取用户积分记录
     */
    public function points(Request $request): AnonymousResourceCollection
    {
        $items = $request->user()->points()->orderByDesc('id')->paginate(per_page($request));

        return PointResource::collection($items);
    }

    /**
     * 获取用户金币记录
     */
    public function coins(Request $request): AnonymousResourceCollection
    {
        $items = $request->user()->coins()->orderByDesc('id')->paginate(per_page($request));

        return CoinResource::collection($items);
    }

    /**
     * 注销并删除自己的账户
     */
    public function destroy(Request $request): Response
    {
        $request->user()->delete();

        return response()->noContent();
    }
}
