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
     * 评论
     */
    Route::apiResource('comments', \App\Http\Controllers\Api\V1\Content\CommentController::class)->only(['index', 'store', 'destroy']); // 评论列表 / 发布评论 / 删除评论

    /**
     * 收藏
     */
    Route::apiResource('collections', \App\Http\Controllers\Api\V1\Content\CollectionController::class)->only(['index', 'store', 'destroy']); // 收藏列表 / 添加收藏 / 取消收藏

    /**
     * 点赞
     */
    Route::apiResource('likes', \App\Http\Controllers\Api\V1\Content\LikeController::class)->only(['index', 'store', 'destroy']); // 点赞列表 / 添加点赞 / 取消点赞

    /**
     * 访问记录
     */
    Route::post('visits', [\App\Http\Controllers\Api\V1\VisitController::class, 'record'])->name('visits.record'); // 记录一次访问（需登录）

    /**
     * 邀请
     */
    Route::group(['prefix' => 'invite', 'as' => 'invite.'], function (Registrar $registrar) {
        $registrar->get('records', [\App\Http\Controllers\Api\V1\InviteController::class, 'records'])->name('records'); // 我的邀请记录
        $registrar->get('stats', [\App\Http\Controllers\Api\V1\InviteController::class, 'stats'])->name('stats'); // 邀请统计
    });

    /**
     * Banner / 轮播图（公开，无需认证）
     */
    Route::get('banners', [\App\Http\Controllers\Api\V1\BannerController::class, 'index'])->name('banners.index');

    /**
     * 公告（公开，无需认证）
     */
    Route::get('announcements', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('announcements/{id}', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'show'])->name('announcements.show');

    /**
     * 协议（公开，无需认证）
     *
     * 前端传类别编码（如 user_agreement），直接返回该类别最新已发布版本。
     */
    Route::get('agreements/{category}', [\App\Http\Controllers\Api\V1\AgreementController::class, 'showByCategory'])->name('agreements.show');

    /**
     * 任务中心
     */
    Route::group(['prefix' => 'tasks', 'as' => 'tasks.'], function (Registrar $registrar) {
        $registrar->get('', [\App\Http\Controllers\Api\V1\TaskController::class, 'index'])->name('index'); // 任务中心列表
        $registrar->post('{taskId}/claim', [\App\Http\Controllers\Api\V1\TaskController::class, 'claim'])->name('claim'); // 领取任务奖励
    });

    /**
     * 用户接口
     */
    Route::group(['prefix' => 'user', 'as' => 'user.'], function (Registrar $registrar) {
        /**
         * 通知
         */
        $registrar->group(['prefix' => 'notifications', 'as' => 'notifications.'], function (Registrar $registrar) {
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
         * 用户反馈（需登录，提交与查看均绑定当前用户）
         */
        $registrar->post('feedbacks', [\App\Http\Controllers\Api\V1\User\FeedbackController::class, 'store'])->name('feedbacks.store'); // 提交反馈（需登录）
        $registrar->get('feedbacks/my', [\App\Http\Controllers\Api\V1\User\FeedbackController::class, 'my'])->name('feedbacks.my'); // 我的反馈列表（需登录）

        /**
         * 用户举报（需登录，提交与查看均绑定当前用户）
         */
        $registrar->post('reports', [\App\Http\Controllers\Api\V1\ReportController::class, 'store'])->name('reports.store'); // 提交举报
        $registrar->get('reports/my', [\App\Http\Controllers\Api\V1\ReportController::class, 'index'])->name('reports.index'); // 我的举报列表

        $registrar->get('', [\App\Http\Controllers\Api\V1\UserController::class, 'baseProfile'])->name('profile'); // 获取基本信息
        $registrar->post('profile', [\App\Http\Controllers\Api\V1\UserController::class, 'modifyProfile'])->name('modify_profile'); // 修改个人资料
        $registrar->post('username', [\App\Http\Controllers\Api\V1\UserController::class, 'modifyUsername'])->name('modify_username'); // 修改账号
        $registrar->post('email', [\App\Http\Controllers\Api\V1\UserController::class, 'modifyEMail'])->name('modify_email'); // 修改邮箱
        $registrar->post('phone', [\App\Http\Controllers\Api\V1\UserController::class, 'modifyPhone'])->name('modify_phone'); // 修改手机号码
        $registrar->post('avatar', [\App\Http\Controllers\Api\V1\UserController::class, 'modifyAvatar'])->name('modify_avatar'); // 修改头像
        $registrar->post('password', [\App\Http\Controllers\Api\V1\UserController::class, 'modifyPassword'])->name('modify_password'); // 修改密码
        $registrar->post('verification-mail', [\App\Http\Controllers\Api\V1\UserController::class, 'sendVerificationMail'])->name('send_verification_mail'); // 发送验证邮件
        $registrar->get('login-histories', [\App\Http\Controllers\Api\V1\UserController::class, 'loginHistories'])->name('login_histories'); // 获取登录历史
        $registrar->get('points', [\App\Http\Controllers\Api\V1\UserController::class, 'points'])->name('points'); // 获取用户积分记录
        $registrar->get('coins', [\App\Http\Controllers\Api\V1\UserController::class, 'coins'])->name('coins'); // 获取用户金币记录
        $registrar->get('socials', [\App\Http\Controllers\Api\V1\UserController::class, 'socials'])->name('socials'); // 获取当前用户绑定的第三方账号列表
        $registrar->get('visits', [\App\Http\Controllers\Api\V1\VisitController::class, 'myVisits'])->name('visits'); // 我的访问记录
        $registrar->delete('', [\App\Http\Controllers\Api\V1\UserController::class, 'destroy'])->name('destroy'); // 注销并删除自己的账户

        /**
         * 钱包
         */
        $registrar->group(['prefix' => 'wallet', 'as' => 'wallet.'], function (Registrar $registrar) {
            $registrar->get('', [\App\Http\Controllers\Api\V1\Wallet\WalletController::class, 'index'])->name('index'); // 钱包概览
            $registrar->get('trades', [\App\Http\Controllers\Api\V1\Wallet\WalletController::class, 'trades'])->name('trades'); // 钱包流水

            // 充值
            $registrar->post('recharges', [\App\Http\Controllers\Api\V1\Wallet\RechargeController::class, 'store'])->name('recharges.store'); // 创建充值订单
            $registrar->get('recharges', [\App\Http\Controllers\Api\V1\Wallet\RechargeController::class, 'index'])->name('recharges.index'); // 我的充值订单
            $registrar->get('recharges/{recharge}', [\App\Http\Controllers\Api\V1\Wallet\RechargeController::class, 'show'])->name('recharges.show'); // 充值订单详情

            // 提现
            $registrar->post('withdraws', [\App\Http\Controllers\Api\V1\Wallet\WithdrawController::class, 'store'])->name('withdraws.store'); // 提交提现申请
            $registrar->get('withdraws', [\App\Http\Controllers\Api\V1\Wallet\WithdrawController::class, 'index'])->name('withdraws.index'); // 我的提现列表
            $registrar->get('withdraws/{withdraw}', [\App\Http\Controllers\Api\V1\Wallet\WithdrawController::class, 'show'])->name('withdraws.show'); // 提现详情

            // 收款账户
            $registrar->get('withdraw-accounts', [\App\Http\Controllers\Api\V1\Wallet\WithdrawAccountController::class, 'index'])->name('withdraw-accounts.index'); // 收款账户列表
            $registrar->post('withdraw-accounts', [\App\Http\Controllers\Api\V1\Wallet\WithdrawAccountController::class, 'store'])->name('withdraw-accounts.store'); // 添加收款账户
            $registrar->put('withdraw-accounts/{withdrawAccount}', [\App\Http\Controllers\Api\V1\Wallet\WithdrawAccountController::class, 'update'])->name('withdraw-accounts.update'); // 修改收款账户
            $registrar->delete('withdraw-accounts/{withdrawAccount}', [\App\Http\Controllers\Api\V1\Wallet\WithdrawAccountController::class, 'destroy'])->name('withdraw-accounts.destroy'); // 删除收款账户
            $registrar->patch('withdraw-accounts/{withdrawAccount}/default', [\App\Http\Controllers\Api\V1\Wallet\WithdrawAccountController::class, 'setDefault'])->name('withdraw-accounts.default'); // 设为默认
        });

        /**
         * 实名认证
         */
        $registrar->get('identity', [\App\Http\Controllers\Api\V1\User\IdentityController::class, 'show'])->name('identity.show'); // 当前用户的最新认证记录
        $registrar->get('identity/status', [\App\Http\Controllers\Api\V1\User\IdentityController::class, 'status'])->name('identity.status'); // 简要状态
        $registrar->post('identity', [\App\Http\Controllers\Api\V1\User\IdentityController::class, 'submit'])->name('identity.submit'); // 提交/重新提交
    });

    /**
     * 上传管理
     */
    Route::group(['prefix' => 'uploader', 'as' => 'uploader.'], function (Registrar $registrar) {
        $registrar->post('file', [\App\Http\Controllers\Api\V1\UploaderController::class, 'file'])->name('file');
        $registrar->post('image', [\App\Http\Controllers\Api\V1\UploaderController::class, 'image'])->name('image');
        $registrar->post('video', [\App\Http\Controllers\Api\V1\UploaderController::class, 'video'])->name('video');
        $registrar->post('token', [\App\Http\Controllers\Api\V1\UploaderController::class, 'uploadToken'])->name('token');
    });
});
