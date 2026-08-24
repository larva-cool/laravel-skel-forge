<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\MainController::class, 'index']);
Route::get('redirect', [\App\Http\Controllers\MainController::class, 'redirect']);

// 微信公众号相关路由
Route::prefix('wechat')->name('wechat.')->group(function () {
    // 网络通信检测
    Route::post('check', [App\Http\Controllers\WechatController::class, 'check'])->name('check');

    // 网页授权
    Route::get('auth', [App\Http\Controllers\WechatController::class, 'auth'])->name('auth');
    Route::get('auth-callback', [App\Http\Controllers\WechatController::class, 'authCallback'])->name('auth-callback');

    // 授权重定向（支持自定义 redirect_uri）
    Route::get('authorize-redirect/{redirect_uri?}', [App\Http\Controllers\WechatController::class, 'authorizeRedirect'])->name('authorize-redirect');
    Route::get('authorize-proxy/{redirect_uri?}', [App\Http\Controllers\WechatController::class, 'authorizeProxy'])->name('authorize-proxy');

    // 通过 Code 获取用户资料
    Route::get('user-info', [App\Http\Controllers\WechatController::class, 'getUserInfo'])->name('user-info');

    // 微信服务端消息回调
    Route::any('serve', [App\Http\Controllers\WechatController::class, 'serve'])->name('serve');
});

// 认证失败时的默认跳转路由（保证 auth:sanctum 或 api 认证失败时返回 JSON 401）
// TODO
Route::get('login', function (): JsonResponse {
    return response()->json([
        'code' => 401,
        'message' => '登录已过期，请重新登录',
        'data' => null,
    ], 401);
})->name('login');
//TODO 验证码验证路由
Route::get('verification/verify', function (): JsonResponse {
    return response()->json([
        'code' => 401,
        'message' => '登录已过期，请重新登录',
        'data' => null,
    ], 401);
})->name('verification.verify');
