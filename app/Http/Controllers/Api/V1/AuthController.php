<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\User\LoginSucceeded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\AppleLoginRequest;
use App\Http\Requests\Api\V1\Auth\PasswordLoginRequest;
use App\Http\Requests\Api\V1\Auth\PhoneLoginRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordByPhoneRequest;
use App\Http\Requests\Api\V1\Auth\WechatMpLoginRequest;
use App\Http\Resources\Api\V1\TokenResource;
use App\Http\Resources\Api\V1\UserInfoResource;
use App\Jobs\User\DeleteAccessTokenJob;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

/**
 * 认证授权 <第一方调用，直接用个人令牌>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AuthController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['passwordLogin', 'phoneLogin']);
        // 登录限速
        $throttle = 'throttle:'.settings('user.login_throttle', '6,1');
        $this->middleware($throttle)->only(['passwordLogin', 'phoneLogin']);
    }

    /**
     * 密码登录
     */
    public function passwordLogin(PasswordLoginRequest $request): JsonResponse
    {
        $this->authorize('passwordLogin', User::class);
        $user = $request->authenticate();
        $token = $user->createToken('user-token', ['user']);
        Event::dispatch(new Login('sanctum', $user, false));
        Event::dispatch(new LoginSucceeded($user, $request->ip(), $request->server('REMOTE_PORT'), $request->userAgent()));

        return response()->json([
            'access_token' => $token->plainTextToken,
            'expires_in' => (int) $token->accessToken->expires_at?->diffInSeconds(Carbon::now()),
            'user' => new UserInfoResource($user),
        ]);
    }

    /**
     * 手机登录
     */
    public function phoneLogin(PhoneLoginRequest $request): JsonResponse
    {
        $this->authorize('phoneLogin', User::class);
        $user = $request->authenticate();
        $token = $user->createToken('user-token', ['user']);
        Event::dispatch(new Login('sanctum', $user, false));
        Event::dispatch(new LoginSucceeded($user, $request->ip(), $request->server('REMOTE_PORT'), $request->userAgent()));

        return response()->json([
            'access_token' => $token->plainTextToken,
            'expires_in' => (int) $token->accessToken->expires_at?->diffInSeconds(Carbon::now()),
            'user' => new UserInfoResource($user),
        ]);
    }

    /**
     * 微信公众号登录
     */
    public function wxLogin(WechatMpLoginRequest $request): JsonResponse
    {
        $this->authorize('wechatLogin', User::class);
        $user = $request->authenticate();
        $token = $user->createToken('user-token', ['user']);
        Event::dispatch(new Login('sanctum', $user, false));
        Event::dispatch(new LoginSucceeded($user, $request->ip(), $request->server('REMOTE_PORT'), $request->userAgent()));

        return response()->json([
            'access_token' => $token->plainTextToken,
            'expires_in' => (int) $token->accessToken->expires_at?->diffInSeconds(Carbon::now()),
            'user' => new UserInfoResource($user),
        ]);
    }

    /**
     * 苹果账号登录
     */
    public function appleLogin(AppleLoginRequest $request): JsonResponse
    {
        $this->authorize('appleLogin', User::class);
        $user = $request->authenticate();
        $token = $user->createToken('user-token', ['user']);
        Event::dispatch(new Login('sanctum', $user, false));
        Event::dispatch(new LoginSucceeded($user, $request->ip(), $request->server('REMOTE_PORT'), $request->userAgent()));

        return response()->json([
            'access_token' => $token->plainTextToken,
            'expires_in' => (int) $token->accessToken->expires_at?->diffInSeconds(Carbon::now()),
            'user' => new UserInfoResource($user),
        ]);
    }

    /**
     * 通过短信重置密码
     */
    public function resetPasswordByPhone(ResetPasswordByPhoneRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = User::query()->where('phone', $request->phone)->first();
        // 清理掉该用户所有的 Token
        $user->tokens()->delete();
        $user->resetPassword($request->password);

        return response()->json(['message' => __('user.password_reset_complete')]);
    }

    /**
     * 重新签发访问令牌
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $tokenModel = $request->user()->currentAccessToken();
        if ($request->user()->status->isFrozen()) {// 禁止掉的用户不允许登录
            $request->user()->tokens()->delete();
            validation_exception('account', __('auth.blocked'));
        }
        $token = $request->user()->createToken($tokenModel->name, $tokenModel->abilities);
        // 一分钟后删除当前这个Token
        DeleteAccessTokenJob::dispatch($tokenModel->token)->delay(now()->addMinutes(1));

        return response()->json([
            'access_token' => $token->plainTextToken,
            'expires_in' => (int) $token->accessToken->expires_at?->diffInSeconds(Carbon::now()),
            'user' => new UserInfoResource($request->user()),
        ]);
    }

    /**
     * 获取已经签发的所有 Token
     */
    public function tokens(Request $request): AnonymousResourceCollection
    {
        $items = $request->user()->tokens()->orderByDesc('id')->paginate(per_page($request));

        return TokenResource::collection($items);
    }

    /**
     * 撤销指定的 Token
     */
    public function destroyToken(Request $request, $tokenId): Response
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->first();
        if (! $token) {
            return response()->noContent(404);
        }
        $token->delete();

        return response()->noContent();
    }

    /**
     * 退出登录
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(status: 204);
    }
}
