<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\Wechat\WechatScan;
use App\Events\Wechat\WechatSubscribed;
use App\Events\Wechat\WechatUnsubscribed;
use App\Jobs\Wechat\ReplyWechatTextMessageJob;
use App\Services\WechatService;
use EasyWeChat\OfficialAccount\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * 微信公众号控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WechatController extends Controller
{
    /**
     * 构造函数
     */
    public function __construct(private WechatService $wechatService) {}

    /**
     * 网络通信检测
     */
    public function check(Request $request)
    {
        $response = $this->wechatService->getApp()->getClient()->postJson('cgi-bin/callback/check', [
            'action' => $request->post('action'),
            'check_operator' => $request->post('check_operator'),
        ]);

        return response()->json($response->toArray());
    }

    /**
     * 跳转去微信认证
     *
     * @return RedirectResponse
     */
    public function auth(Request $request)
    {
        $oauth = $this->wechatService->getOauth();
        $redirectUri = route('wechat.auth-callback');
        $redirectUrl = $oauth->scopes(['snsapi_userinfo'])->redirect($redirectUri);

        return redirect()->away($redirectUrl);
    }

    /**
     * 认证回调
     */
    public function authCallback(Request $request): JsonResponse
    {
        $oauth = $this->wechatService->getOAuth();
        try {
            $wUser = $oauth->userFromCode($request->query('code'));

            return response()->json($wUser->toArray());
        } catch (\Exception  $e) {
            Log::warning($e->getMessage(), $e->getTrace());
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }

    /**
     * 微信公众号授权重定向
     */
    public function authorizeRedirect(Request $request, ?string $redirect_uri = null): RedirectResponse
    {
        $params = $request->query();
        if (! $request->has('code') && $request->filled('redirect_uri')) {
            $redirectUri = route('wechat.authorize-redirect', ['redirect_uri' => base64_urlencode($request->query('redirect_uri'))]);
            $scope = $request->query('scope', 'snsapi_base');
            $redirectUrl = $this->wechatService->getOauth()->scopes([$scope])->withState(Str::random(10))->redirect($redirectUri);

            return redirect()->away($redirectUrl);
        } elseif (! empty($redirect_uri)) {
            $redirectUri = base64_urldecode($redirect_uri);
            $params['wx_code'] = $request->query('code');
            unset($params['code']);
            if (str_contains($redirectUri, '?')) {
                $redirectUri .= '&'.http_build_query($params);
            } else {
                $redirectUri .= '?'.http_build_query($params);
            }

            return redirect()->away($redirectUri);
        }

        return redirect()->away(config('app.url'));
    }

    /**
     * 微信网页授权代理
     */
    public function authorizeProxy(Request $request, ?string $redirect_uri = null): RedirectResponse
    {
        $params = $request->query();
        if (! $request->has('code') && $request->filled('redirect_uri')) {
            $params['redirect_uri'] = route('wechat.authorize-proxy', ['redirect_uri' => base64_urlencode($request->query('redirect_uri'))]);
            if (! isset($params['state'])) {
                $params['state'] = Str::random(10);
            }

            return redirect()->away('https://open.weixin.qq.com/connect/oauth2/authorize?'.http_build_query($params).'#wechat_redirect');
        } elseif (! empty($redirect_uri)) {
            $redirectUri = base64_urldecode($redirect_uri);
            if (str_contains($redirectUri, '?')) {
                $redirectUri .= '&'.http_build_query($params);
            } else {
                $redirectUri .= '?'.http_build_query($params);
            }

            return redirect()->away($redirectUri);
        }

        return redirect()->away(config('app.url'));
    }

    /**
     * 通过Code 获取 用户资料
     */
    public function getUserInfo(Request $request): JsonResponse
    {
        try {
            $oauth = $this->wechatService->getOauth();
            $wUser = $oauth->userFromCode($request->query('code'));

            return response()->json($wUser->toArray());
        } catch (\Exception  $e) {
            Log::warning($e->getMessage(), $e->getTrace());

            return response()->json();
        }
    }

    /**
     * 处理微信请求
     */
    public function serve(Request $request): ResponseInterface
    {
        $server = $this->wechatService->getServer();
        // 公众号关注事件
        $server->addEventListener('subscribe', function ($message, \Closure $next) {
            Event::dispatch(new WechatSubscribed($message->toArray()));
            if (! empty($message['EventKey'])) {
                $message['EventKey'] = Str::replace('qrscene_', '', $message['EventKey']);
                Event::dispatch(new WechatScan($message->toArray()));
            }

            return $next($message);
        });
        // 公众号取消关注事件
        $server->addEventListener('unsubscribe', function ($message, \Closure $next) {
            Event::dispatch(new WechatUnsubscribed($message->toArray()));

            return $next($message);
        });
        // 扫码事件 需要切开场景值
        $server->addEventListener('SCAN', function ($message, \Closure $next) {
            Event::dispatch(new WechatScan($message->toArray()));

            return $next($message);
        });

        // 文本消息：异步派发智能客服回复。
        // 被动响应受微信 5 秒超时限制，AI 生成后通过客服消息接口下发；
        // 此处返回 null 使被动响应为空串（success），避免微信超时重推消息。
        $server->addMessageListener('text', function (Message $message) {
            ReplyWechatTextMessageJob::dispatch((string) $message->MsgId, (string) $message->FromUserName, (string) $message->Content);

            return null;
        });

        $server->with(function (Message $message, \Closure $next) {
            return __('system.wechat_fallback_busy');
        });

        return $server->serve();
    }
}
