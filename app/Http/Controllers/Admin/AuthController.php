<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\Admin\LoginSucceeded;
use App\Http\Requests\Admin\Auth\PasswordLoginRequest;
use App\Http\Requests\Admin\Auth\WechatMpLoginRequest;
use App\Http\Resources\Admin\AdminInfoResource;
use App\Http\Resources\Api\V1\TokenResource;
use App\Http\Resources\Api\V1\UserInfoResource;
use App\Jobs\User\DeleteAccessTokenJob;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

/**
 * 后台认证控制器
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class AuthController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin')->except(['login', 'wxLogin']);
        // 登录限速
        $throttle = 'throttle:'.settings('admin.login_throttle', '10,3');
        $this->middleware($throttle)->only(['login', 'wxLogin']);
    }

    /**
     * 获取当前管理员信息
     */
    public function info(Request $request): AdminInfoResource
    {
        /** @var Admin $admin */
        $admin = $request->user();

        return new AdminInfoResource($admin);
    }

    /**
     * 管理员登录
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(PasswordLoginRequest $request): JsonResponse
    {
        $admin = $request->authenticate();
        $token = $admin->createToken('admin-token', ['admin'], Carbon::now()->addMinutes(1440));
        Event::dispatch(new Login(AdminMenu::GUARD_NAME, $admin, false));
        Event::dispatch(new LoginSucceeded($admin, $request->ip(), (string) $request->server('REMOTE_PORT'), $request->userAgent()));

        return response()->json([
            'access_token' => $token->plainTextToken,
            'user' => new AdminInfoResource($admin),
        ]);
    }

    /**
     * 微信公众号登录
     */
    public function wxLogin(WechatMpLoginRequest $request): JsonResponse
    {
        $admin = $request->authenticate();
        $token = $admin->createToken('admin-token', ['admin'], Carbon::now()->addMinutes(1440));
        Event::dispatch(new Login('sanctum', $admin, false));
        Event::dispatch(new LoginSucceeded($admin, $request->ip(), $request->server('REMOTE_PORT'), $request->userAgent()));

        return response()->json([
            'access_token' => $token->plainTextToken,
            'user' => new AdminInfoResource($admin),
        ]);
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
        $token = $request->user()->createToken($tokenModel->name, $tokenModel->abilities, Carbon::now()->addMinutes(1440));
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
     * 管理员退出登录
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(status: 204);
    }
}
