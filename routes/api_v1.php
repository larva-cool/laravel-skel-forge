<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Support\Facades\Route;

/**
 * RESTFul API version 1.
 *
 * Define the version of the interface that conforms to most of the
 * REST ful specification.
 */
Route::group(['prefix' => 'v1', 'as' => 'api.v1.'], function () {
    /**
     * 公共接口
     */
    Route::group(['prefix' => 'common', 'as' => 'common.'], function (Registrar $registrar) {
        $registrar->post('fpm', [\App\Http\Controllers\Api\V1\CommonController::class, 'fpm'])->name('fpm'); // reload fpm
        $registrar->post('sms-captcha', [\App\Http\Controllers\Api\V1\CommonController::class, 'smsCaptcha'])->name('sms_captcha'); // 短信验证码
        $registrar->post('mail-captcha', [\App\Http\Controllers\Api\V1\CommonController::class, 'mailCaptcha'])->name('mail_captcha'); // 邮件验证码
        // 增加缓存Header
        $registrar->group(['middleware' => 'cache.headers:public;max_age=2628000;etag'], function (Registrar $registrar) {
            $registrar->get('area', [\App\Http\Controllers\Api\V1\CommonController::class, 'area'])->name('area'); // 地区列表
            $registrar->get('dict-items/{code}', [\App\Http\Controllers\Api\V1\CommonController::class, 'dictItems'])->name('dict_items'); // 公开字典项（白名单）
        });
    });

    /**
     * 注册接口
     */
    Route::group(['prefix' => 'register', 'as' => 'register.'], function (Registrar $registrar) {
        $registrar->post('exists', [\App\Http\Controllers\Api\V1\RegisterController::class, 'exists'])->name('exists'); // 账号邮箱手机号检查
        $registrar->post('phone-register', [\App\Http\Controllers\Api\V1\RegisterController::class, 'phoneRegister'])->name('phone'); // 手机号注册
        $registrar->post('', [\App\Http\Controllers\Api\V1\RegisterController::class, 'emailRegister'])->name('email'); // 邮箱注册
    });

    /**
     * 登录认证授权
     */
    Route::group(['prefix' => 'auth', 'as' => 'auth.'], function (Registrar $registrar) {
        $registrar->post('login', [\App\Http\Controllers\Api\V1\AuthController::class, 'passwordLogin'])->name('password_login'); // 密码授权
        $registrar->post('phone-login', [\App\Http\Controllers\Api\V1\AuthController::class, 'phoneLogin'])->name('phone_login'); // 短信验证码授权
        $registrar->post('wx-login', [\App\Http\Controllers\Api\V1\AuthController::class, 'wxLogin'])->name('wx_login'); // 微信公众号授权登录
        $registrar->post('apple-login', [\App\Http\Controllers\Api\V1\AuthController::class, 'appleLogin'])->name('apple_login'); // Apple 登录授权
        $registrar->post('refresh-token', [\App\Http\Controllers\Api\V1\AuthController::class, 'refreshToken'])->name('refresh_token'); // 重新签发个人访问令牌
        $registrar->get('tokens', [\App\Http\Controllers\Api\V1\AuthController::class, 'tokens'])->name('tokens'); // 查询已经签发的所有令牌
        $registrar->delete('tokens/{tokenId}', [\App\Http\Controllers\Api\V1\AuthController::class, 'destroyToken'])->name('destroy_token'); // 销毁指定的 Token
        $registrar->post('phone-reset-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'resetPasswordByPhone'])->name('reset_password_by_phone'); // 通过手机重置用户登录密码
        $registrar->delete('logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout'])->name('logout'); // 退出登录
    });

    /**
     * App 版本管理
     */
    Route::group(['prefix' => 'apps', 'as' => 'apps.'], function (Registrar $registrar) {
        $registrar->post('check-update', [\App\Http\Controllers\Api\V1\AppController::class, 'checkUpdate'])->name('check_update'); // 检查版本更新
    });

    /**
     * 用户反馈（公开提交，登录用户可查看自己的反馈）
     */
    Route::post('feedbacks', [\App\Http\Controllers\Api\V1\FeedbackController::class, 'store'])->name('feedbacks.store'); // 提交反馈
    Route::get('feedbacks/my', [\App\Http\Controllers\Api\V1\FeedbackController::class, 'my'])->name('feedbacks.my'); // 我的反馈列表（需登录）

    /**
     * 评论
     */
    Route::apiResource('comments', \App\Http\Controllers\Api\V1\Content\CommentController::class)->only(['index', 'store', 'destroy']); // 评论列表 / 发布评论 / 删除评论

    /**
     * 用户接口
     */
    Route::group(['prefix' => 'user', 'as' => 'user.'], function (Registrar $registrar) {
        /**
         * 通知
         */
        Route::group(['prefix' => 'notifications', 'as' => 'notifications.'], function (Registrar $registrar) {
            $registrar->get('', [\App\Http\Controllers\Api\V1\User\NotificationController::class, 'index'])->name('index'); // 通知列表
            $registrar->get('unread', [\App\Http\Controllers\Api\V1\User\NotificationController::class, 'unread'])->name('unread'); // 未读通知列表
            $registrar->post('mark-all-read', [\App\Http\Controllers\Api\V1\User\NotificationController::class, 'markAllAsRead'])->name('mark_all_as_read'); // 标记所有未读通知为已读
            $registrar->post('mark-read', [\App\Http\Controllers\Api\V1\User\NotificationController::class, 'markAsRead'])->name('mark_as_read'); // 标记指定未读通知为已读
            $registrar->delete('clear-read', [\App\Http\Controllers\Api\V1\User\NotificationController::class, 'clearRead'])->name('clear_read'); // 清空所有已读通知
        });

        /**
         * 收货地址
         */
        $registrar->put('address/{address}/default', [\App\Http\Controllers\Api\V1\User\AddressController::class, 'setDefault']); // 设为默认地址
        $registrar->apiResource('address', \App\Http\Controllers\Api\V1\User\AddressController::class); // 收货地址

        /**
         * 收藏
         */
        $registrar->apiResource('collections', \App\Http\Controllers\Api\V1\User\CollectionController::class)
            ->only(['index', 'store', 'destroy']); // 收藏列表 / 添加收藏 / 取消收藏

        /**
         * 点赞
         */
        $registrar->apiResource('likes', \App\Http\Controllers\Api\V1\User\LikeController::class)
            ->only(['index', 'store', 'destroy']); // 点赞列表 / 添加点赞 / 取消点赞

        $registrar->get('', [\App\Http\Controllers\Api\V1\UserController::class, 'baseProfile'])->name('profile'); // 获取基本信息
        $registrar->post('profile', [\App\Http\Controllers\Api\V1\UserController::class, 'modifyProfile'])->name('modify_profile'); // 修改个人资料
        $registrar->post('username', [\App\Http\Controllers\Api\V1\UserController::class, 'modifyUsername'])->name('modify_username'); // 修改账号
        $registrar->post('email', [\App\Http\Controllers\Api\V1\UserController::class, 'modifyEMail'])->name('modify_email'); // 修改邮箱
        $registrar->post('phone', [\App\Http\Controllers\Api\V1\UserController::class, 'modifyPhone'])->name('modify_phone'); // 修改手机号码
        // $registrar->post('avatar', [\App\Http\Controllers\Api\V1\UserController::class, 'modifyAvatar'])->name('modify_avatar'); // 修改头像
        $registrar->post('password', [\App\Http\Controllers\Api\V1\UserController::class, 'modifyPassword'])->name('modify_password'); // 修改密码
        $registrar->post('verification-mail', [\App\Http\Controllers\Api\V1\UserController::class, 'sendVerificationMail'])->name('send_verification_mail'); // 发送验证邮件
        $registrar->get('login-histories', [\App\Http\Controllers\Api\V1\UserController::class, 'loginHistories'])->name('login_histories'); // 获取登录历史
        $registrar->get('points', [\App\Http\Controllers\Api\V1\UserController::class, 'points'])->name('points'); // 获取用户积分记录
        $registrar->get('coins', [\App\Http\Controllers\Api\V1\UserController::class, 'coins'])->name('coins'); // 获取用户金币记录
        $registrar->delete('', [\App\Http\Controllers\Api\V1\UserController::class, 'destroy'])->name('destroy'); // 注销并删除自己的账户
    });
});
