<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Support\Facades\Route;

// 认证路由
Route::group(['prefix' => 'auth'], function (Registrar $registrar) {
    $registrar->post('login', [\App\Http\Controllers\Admin\AuthController::class, 'login'])->name('login');
    $registrar->post('wx-login', [\App\Http\Controllers\Admin\AuthController::class, 'wxLogin'])->name('wx_login'); // 微信公众号授权登录
    $registrar->post('refresh-token', [\App\Http\Controllers\Admin\AuthController::class, 'refreshToken'])->name('refresh_token'); // 重新签发个人访问令牌
    $registrar->get('tokens', [\App\Http\Controllers\Admin\AuthController::class, 'tokens'])->name('tokens'); // 查询已经签发的所有令牌
    $registrar->delete('tokens/{tokenId}', [\App\Http\Controllers\Admin\AuthController::class, 'destroyToken'])->name('destroy_token'); // 销毁指定的 Token
    $registrar->post('logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('logout');
    $registrar->get('info', [\App\Http\Controllers\Admin\AuthController::class, 'info'])->name('info');
});

// 角色管理
Route::group(['prefix' => 'roles', 'as' => 'roles.'], function (Registrar $registrar) {
    $registrar->get('permissions', [\App\Http\Controllers\Admin\RoleController::class, 'allPermissions'])->name('permissions');
    $registrar->get('{role}/permissions', [\App\Http\Controllers\Admin\RoleController::class, 'permissions'])->name('get-permissions');
    $registrar->put('{role}/permissions', [\App\Http\Controllers\Admin\RoleController::class, 'assignPermissions'])->name('assign-permissions');
});
Route::apiResource('roles', \App\Http\Controllers\Admin\RoleController::class);

// 应用管理
Route::apiResource('apps', \App\Http\Controllers\Admin\AppReleaseController::class);

// 版本管理
Route::group(['prefix' => 'apps/{app}/versions', 'as' => 'apps.versions.'], function (Registrar $registrar) {
    $registrar->get('', [\App\Http\Controllers\Admin\AppVersionController::class, 'index'])->name('index');
    $registrar->post('', [\App\Http\Controllers\Admin\AppVersionController::class, 'store'])->name('store');
    $registrar->get('{version}', [\App\Http\Controllers\Admin\AppVersionController::class, 'show'])->name('show');
    $registrar->put('{version}', [\App\Http\Controllers\Admin\AppVersionController::class, 'update'])->name('update');
    $registrar->delete('{version}', [\App\Http\Controllers\Admin\AppVersionController::class, 'destroy'])->name('destroy');
    $registrar->post('{version}/publish', [\App\Http\Controllers\Admin\AppVersionController::class, 'publish'])->name('publish');
    $registrar->post('{version}/withdraw', [\App\Http\Controllers\Admin\AppVersionController::class, 'withdraw'])->name('withdraw');
    $registrar->post('{version}/obsolete', [\App\Http\Controllers\Admin\AppVersionController::class, 'obsolete'])->name('obsolete');
});

// 前端路由配置
Route::get('routes', [\App\Http\Controllers\Admin\MainController::class, 'routes'])->name('routes');

// 公共选项接口
Route::get('common/source-types', [\App\Http\Controllers\Admin\CommonController::class, 'sourceTypes'])->name('common.source-types');

// 清理应用缓存
Route::post('cache/clear', [\App\Http\Controllers\Admin\MainController::class, 'clearCache'])->name('cache.clear');

// 数据概览
Route::get('dashboard/stats', [\App\Http\Controllers\Admin\DashboardController::class, 'stats'])->name('dashboard.stats');

// 通知管理
Route::group(['prefix' => 'notifications', 'as' => 'notifications.'], function (Registrar $registrar) {
    $registrar->get('', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('index');
    $registrar->get('unread', [\App\Http\Controllers\Admin\NotificationController::class, 'unread'])->name('unread');
    $registrar->put('mark-all-read', [\App\Http\Controllers\Admin\NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
    $registrar->put('mark-read', [\App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])->name('mark-read');
    $registrar->delete('clear-read', [\App\Http\Controllers\Admin\NotificationController::class, 'clearRead'])->name('clear-read');
});

// 菜单管理
Route::apiResource('menus', \App\Http\Controllers\Admin\MenuController::class);

// 管理员管理
Route::group(['prefix' => 'admins', 'as' => 'admins.'], function (Registrar $registrar) {
    $registrar->get('profile', [\App\Http\Controllers\Admin\AdminController::class, 'profile'])->name('profile');
    $registrar->put('profile', [\App\Http\Controllers\Admin\AdminController::class, 'updateProfile'])->name('update-profile');
    $registrar->post('avatar', [\App\Http\Controllers\Admin\AdminController::class, 'updateAvatar'])->name('update-avatar');
    $registrar->get('{admin}/roles', [\App\Http\Controllers\Admin\AdminController::class, 'roles'])->name('roles');
    $registrar->get('{admin}/login-histories', [\App\Http\Controllers\Admin\AdminController::class, 'loginHistories'])->name('login-histories');
    $registrar->put('{admin}/roles', [\App\Http\Controllers\Admin\AdminController::class, 'assignRoles'])->name('assign-roles');
    $registrar->put('{admin}/toggle-status', [\App\Http\Controllers\Admin\AdminController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->put('{admin}/reset-password', [\App\Http\Controllers\Admin\AdminController::class, 'resetPassword'])->name('reset-password');
    $registrar->put('change-password', [\App\Http\Controllers\Admin\AdminController::class, 'changePassword'])->name('change-password');
});
Route::apiResource('admins', \App\Http\Controllers\Admin\AdminController::class);

// 用户管理
Route::group(['prefix' => 'users', 'as' => 'users.'], function (Registrar $registrar) {
    $registrar->get('trash', [\App\Http\Controllers\Admin\UserController::class, 'trash'])->name('trash');
    $registrar->put('{user}/restore', [\App\Http\Controllers\Admin\UserController::class, 'restore'])->name('restore');
    $registrar->delete('{user}/force-delete', [\App\Http\Controllers\Admin\UserController::class, 'forceDelete'])->name('force-delete');
    $registrar->get('{user}/login-histories', [\App\Http\Controllers\Admin\UserController::class, 'loginHistories'])->name('login-histories');
    $registrar->get('{user}/socials', [\App\Http\Controllers\Admin\UserController::class, 'socials'])->name('socials');
    $registrar->get('{user}/point-trades', [\App\Http\Controllers\Admin\UserController::class, 'pointTrades'])->name('point-trades');
    $registrar->get('{user}/coin-trades', [\App\Http\Controllers\Admin\UserController::class, 'coinTrades'])->name('coin-trades');
    $registrar->get('{user}/wallet', [\App\Http\Controllers\Admin\WalletController::class, 'userWallet'])->name('wallet');
    $registrar->get('{user}/wallet-trades', [\App\Http\Controllers\Admin\WalletController::class, 'userTrades'])->name('wallet-trades');
    $registrar->get('{user}/withdraw-accounts', [\App\Http\Controllers\Admin\WalletController::class, 'userWithdrawAccounts'])->name('withdraw-accounts');
    $registrar->put('{user}/adjust-wallet', [\App\Http\Controllers\Admin\WalletController::class, 'adjust'])->name('adjust-wallet');
    $registrar->get('{user}/point-trades/export', [\App\Http\Controllers\Admin\UserController::class, 'exportPointTrades'])->name('point-trades.export');
    $registrar->get('{user}/coin-trades/export', [\App\Http\Controllers\Admin\UserController::class, 'exportCoinTrades'])->name('coin-trades.export');
    $registrar->put('{user}/toggle-status', [\App\Http\Controllers\Admin\UserController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->put('{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('reset-password');
    $registrar->put('{user}/reset-contact', [\App\Http\Controllers\Admin\UserController::class, 'resetContact'])->name('reset-contact');
    $registrar->put('{user}/adjust-balance', [\App\Http\Controllers\Admin\UserController::class, 'adjustBalance'])->name('adjust-balance');
    $registrar->put('{user}/extend-vip', [\App\Http\Controllers\Admin\UserController::class, 'extendVip'])->name('extend-vip');
    $registrar->get('export', [\App\Http\Controllers\Admin\UserController::class, 'export'])->name('export');
});
Route::apiResource('users', \App\Http\Controllers\Admin\UserController::class)->except(['store']);

// 钱包管理
Route::group(['prefix' => 'wallet', 'as' => 'wallet.'], function (Registrar $registrar) {
    $registrar->get('stats', [\App\Http\Controllers\Admin\WalletController::class, 'stats'])->name('stats'); // 钱包统计
    $registrar->get('trades/export', [\App\Http\Controllers\Admin\WalletController::class, 'export'])->name('trades.export'); // 导出钱包流水
    $registrar->get('trades', [\App\Http\Controllers\Admin\WalletController::class, 'index'])->name('trades.index'); // 全局流水列表
    $registrar->get('trades/{trade}', [\App\Http\Controllers\Admin\WalletController::class, 'show'])->name('trades.show'); // 流水详情

    // 充值订单
    $registrar->get('recharges/stats', [\App\Http\Controllers\Admin\RechargeController::class, 'stats'])->name('recharges.stats'); // 充值统计
    $registrar->get('recharges/export', [\App\Http\Controllers\Admin\RechargeController::class, 'export'])->name('recharges.export'); // 导出充值订单
    $registrar->get('recharges', [\App\Http\Controllers\Admin\RechargeController::class, 'index'])->name('recharges.index'); // 充值订单列表
    $registrar->get('recharges/{recharge}', [\App\Http\Controllers\Admin\RechargeController::class, 'show'])->name('recharges.show'); // 充值订单详情
    $registrar->post('recharges/{recharge}/confirm', [\App\Http\Controllers\Admin\RechargeController::class, 'confirm'])->name('recharges.confirm'); // 手动确认到账
    $registrar->post('recharges/{recharge}/close', [\App\Http\Controllers\Admin\RechargeController::class, 'close'])->name('recharges.close'); // 关闭订单

    // 提现申请
    $registrar->get('withdraws/stats', [\App\Http\Controllers\Admin\WithdrawController::class, 'stats'])->name('withdraws.stats'); // 提现统计
    $registrar->get('withdraws/export', [\App\Http\Controllers\Admin\WithdrawController::class, 'export'])->name('withdraws.export'); // 导出提现申请
    $registrar->get('withdraws', [\App\Http\Controllers\Admin\WithdrawController::class, 'index'])->name('withdraws.index'); // 提现申请列表
    $registrar->get('withdraws/{withdraw}', [\App\Http\Controllers\Admin\WithdrawController::class, 'show'])->name('withdraws.show'); // 提现申请详情
    $registrar->post('withdraws/{withdraw}/approve', [\App\Http\Controllers\Admin\WithdrawController::class, 'approve'])->name('withdraws.approve'); // 审核通过
    $registrar->post('withdraws/{withdraw}/reject', [\App\Http\Controllers\Admin\WithdrawController::class, 'reject'])->name('withdraws.reject'); // 审核驳回
    $registrar->post('withdraws/{withdraw}/transfer', [\App\Http\Controllers\Admin\WithdrawController::class, 'transfer'])->name('withdraws.transfer'); // 标记已打款
    $registrar->post('withdraws/{withdraw}/fail', [\App\Http\Controllers\Admin\WithdrawController::class, 'fail'])->name('withdraws.fail'); // 标记打款失败
});

// 配置管理
Route::group(['prefix' => 'settings', 'as' => 'settings.'], function (Registrar $registrar) {
    $registrar->get('groups', [\App\Http\Controllers\Admin\SettingController::class, 'groups'])->name('groups');
    $registrar->get('input-types', [\App\Http\Controllers\Admin\SettingController::class, 'inputTypes'])->name('input-types');
    $registrar->put('batch', [\App\Http\Controllers\Admin\SettingController::class, 'batchUpdate'])->name('batch');
});
Route::apiResource('settings', \App\Http\Controllers\Admin\SettingController::class);

// 地区管理
Route::apiResource('areas', \App\Http\Controllers\Admin\AreaController::class);

// 手机验证码管理（仅列表、详情，只读）
Route::get('phone-codes', [\App\Http\Controllers\Admin\PhoneCodeController::class, 'index'])->name('phone-codes.index');
Route::get('phone-codes/{id}', [\App\Http\Controllers\Admin\PhoneCodeController::class, 'show'])->name('phone-codes.show');

// 邮件验证码管理（仅列表、详情，只读）
Route::get('mail-codes', [\App\Http\Controllers\Admin\MailCodeController::class, 'index'])->name('mail-codes.index');
Route::get('mail-codes/{id}', [\App\Http\Controllers\Admin\MailCodeController::class, 'show'])->name('mail-codes.show');

// 上传管理
Route::group(['prefix' => 'uploader', 'as' => 'uploader.'], function (Registrar $registrar) {
    $registrar->post('file', [\App\Http\Controllers\Admin\UploaderController::class, 'file'])->name('file');
    $registrar->post('image', [\App\Http\Controllers\Admin\UploaderController::class, 'image'])->name('image');
    $registrar->post('video', [\App\Http\Controllers\Admin\UploaderController::class, 'video'])->name('video');
    $registrar->post('apk', [\App\Http\Controllers\Admin\UploaderController::class, 'apk'])->name('apk');
    $registrar->post('token', [\App\Http\Controllers\Admin\UploaderController::class, 'uploadToken'])->name('token');
});

// 附件管理
Route::group(['prefix' => 'attachments', 'as' => 'attachments.'], function (Registrar $registrar) {
    $registrar->get('disks', [\App\Http\Controllers\Admin\AttachmentController::class, 'disks'])->name('disks');
    $registrar->post('register', [\App\Http\Controllers\Admin\AttachmentController::class, 'register'])->name('register');
    $registrar->delete('', [\App\Http\Controllers\Admin\AttachmentController::class, 'batchDestroy'])->name('batch-destroy');
    $registrar->get('{id}/download', [\App\Http\Controllers\Admin\AttachmentController::class, 'download'])->name('download');
    $registrar->get('{id}/temporary-url', [\App\Http\Controllers\Admin\AttachmentController::class, 'temporaryUrl'])->name('temporary-url');
    $registrar->put('{id}/rename', [\App\Http\Controllers\Admin\AttachmentController::class, 'rename'])->name('rename');
    $registrar->put('{id}/move', [\App\Http\Controllers\Admin\AttachmentController::class, 'move'])->name('move');
    $registrar->get('', [\App\Http\Controllers\Admin\AttachmentController::class, 'index'])->name('index');
    $registrar->get('{id}', [\App\Http\Controllers\Admin\AttachmentController::class, 'show'])->name('show');
    $registrar->delete('{id}', [\App\Http\Controllers\Admin\AttachmentController::class, 'destroy'])->name('destroy');
});

// 性能监控（Laravel Pulse 数据）
Route::group(['prefix' => 'monitor', 'as' => 'monitor.'], function (Registrar $registrar) {
    $registrar->get('servers', [\App\Http\Controllers\Admin\MonitorController::class, 'servers'])->name('servers');
    $registrar->get('queues', [\App\Http\Controllers\Admin\MonitorController::class, 'queues'])->name('queues');
    $registrar->get('reverb/connections', [\App\Http\Controllers\Admin\MonitorController::class, 'reverbConnections'])->name('reverb.connections');
    $registrar->get('reverb/messages', [\App\Http\Controllers\Admin\MonitorController::class, 'reverbMessages'])->name('reverb.messages');
    $registrar->get('cache', [\App\Http\Controllers\Admin\MonitorController::class, 'cache'])->name('cache');
    $registrar->get('exceptions', [\App\Http\Controllers\Admin\MonitorController::class, 'exceptions'])->name('exceptions');
    $registrar->get('slow-queries', [\App\Http\Controllers\Admin\MonitorController::class, 'slowQueries'])->name('slow-queries');
    $registrar->get('slow-requests', [\App\Http\Controllers\Admin\MonitorController::class, 'slowRequests'])->name('slow-requests');
    $registrar->get('slow-jobs', [\App\Http\Controllers\Admin\MonitorController::class, 'slowJobs'])->name('slow-jobs');
    $registrar->get('slow-outgoing-requests', [\App\Http\Controllers\Admin\MonitorController::class, 'slowOutgoingRequests'])->name('slow-outgoing-requests');
    $registrar->get('usage', [\App\Http\Controllers\Admin\MonitorController::class, 'usage'])->name('usage');
});

// 调试面板（Laravel Telescope 数据）
Route::group(['prefix' => 'debug', 'as' => 'debug.'], function (Registrar $registrar) {
    $registrar->get('tags', [\App\Http\Controllers\Admin\DebugController::class, 'tags'])->name('tags');
    $registrar->post('tags', [\App\Http\Controllers\Admin\DebugController::class, 'monitor'])->name('monitor');
    $registrar->delete('tags', [\App\Http\Controllers\Admin\DebugController::class, 'unmonitor'])->name('unmonitor');
    $registrar->post('toggle-recording', [\App\Http\Controllers\Admin\DebugController::class, 'toggleRecording'])->name('toggle-recording');
    $registrar->delete('entries', [\App\Http\Controllers\Admin\DebugController::class, 'destroy'])->name('destroy');
    $registrar->get('entries', [\App\Http\Controllers\Admin\DebugController::class, 'index'])->name('index');
    $registrar->get('entries/{id}', [\App\Http\Controllers\Admin\DebugController::class, 'show'])->name('show');
    $registrar->put('entries/{id}/resolve', [\App\Http\Controllers\Admin\DebugController::class, 'resolve'])->name('resolve');
});

// 调度任务日志（仅列表、详情，只读）
Route::get('schedule-logs', [\App\Http\Controllers\Admin\ScheduleLogController::class, 'index'])->name('schedule-logs.index');
Route::get('schedule-logs/{id}', [\App\Http\Controllers\Admin\ScheduleLogController::class, 'show'])->name('schedule-logs.show');

// 操作审计日志（仅列表、详情，只读）
Route::group(['prefix' => 'audit-logs', 'as' => 'audit-logs.'], function (Registrar $registrar) {
    $registrar->get('', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('index');
    $registrar->get('export', [\App\Http\Controllers\Admin\AuditLogController::class, 'export'])->name('export');
    $registrar->get('{id}', [\App\Http\Controllers\Admin\AuditLogController::class, 'show'])->name('show');
});

// 全局登录历史（跨管理员与前台用户，只读）
Route::get('login-histories', [\App\Http\Controllers\Admin\LoginHistoryController::class, 'index'])->name('login-histories.index');

// 邀请记录（只读）
Route::get('invite-records', [\App\Http\Controllers\Admin\InviteRecordController::class, 'index'])->name('invite-records.index');
Route::get('invite-records/{inviteRecord}', [\App\Http\Controllers\Admin\InviteRecordController::class, 'show'])->name('invite-records.show');

// 队列管理（Laravel Horizon 数据代理）
Route::group(['prefix' => 'queue', 'as' => 'queue.'], function (Registrar $registrar) {
    $registrar->get('stats', [\App\Http\Controllers\Admin\QueueController::class, 'stats'])->name('stats');
    $registrar->get('workload', [\App\Http\Controllers\Admin\QueueController::class, 'workload'])->name('workload');
    $registrar->get('masters', [\App\Http\Controllers\Admin\QueueController::class, 'masters'])->name('masters');

    // 标签监控
    $registrar->get('monitoring/tags', [\App\Http\Controllers\Admin\QueueController::class, 'monitoringTags'])->name('monitoring.tags');
    $registrar->get('monitoring/jobs', [\App\Http\Controllers\Admin\QueueController::class, 'monitoringJobs'])->name('monitoring.jobs');
    $registrar->post('monitoring/tags', [\App\Http\Controllers\Admin\QueueController::class, 'monitorTag'])->name('monitoring.tag');
    $registrar->delete('monitoring/tags/{tag}', [\App\Http\Controllers\Admin\QueueController::class, 'stopMonitoringTag'])->name('monitoring.tag.destroy');

    // 指标
    $registrar->get('metrics/jobs', [\App\Http\Controllers\Admin\QueueController::class, 'jobMetrics'])->name('metrics.jobs');
    $registrar->get('metrics/jobs/{id}', [\App\Http\Controllers\Admin\QueueController::class, 'jobMetricsDetail'])->name('metrics.jobs.detail');
    $registrar->get('metrics/queues', [\App\Http\Controllers\Admin\QueueController::class, 'queueMetrics'])->name('metrics.queues');
    $registrar->get('metrics/queues/{id}', [\App\Http\Controllers\Admin\QueueController::class, 'queueMetricsDetail'])->name('metrics.queues.detail');

    // 批处理
    $registrar->get('batches', [\App\Http\Controllers\Admin\QueueController::class, 'batches'])->name('batches');
    $registrar->get('batches/{id}', [\App\Http\Controllers\Admin\QueueController::class, 'batchDetail'])->name('batches.detail');
    $registrar->post('batches/{id}/retry', [\App\Http\Controllers\Admin\QueueController::class, 'retryBatch'])->name('batches.retry');

    // 任务列表
    $registrar->get('jobs/pending', [\App\Http\Controllers\Admin\QueueController::class, 'pendingJobs'])->name('jobs.pending');
    $registrar->get('jobs/completed', [\App\Http\Controllers\Admin\QueueController::class, 'completedJobs'])->name('jobs.completed');
    $registrar->get('jobs/silenced', [\App\Http\Controllers\Admin\QueueController::class, 'silencedJobs'])->name('jobs.silenced');
    $registrar->post('jobs/failed/{id}/retry', [\App\Http\Controllers\Admin\QueueController::class, 'retryJob'])->name('jobs.retry');
    $registrar->get('jobs/failed', [\App\Http\Controllers\Admin\QueueController::class, 'failedJobs'])->name('jobs.failed');
    $registrar->get('jobs/failed/{id}', [\App\Http\Controllers\Admin\QueueController::class, 'failedJobDetail'])->name('jobs.failed.detail');
    $registrar->get('jobs/{id}', [\App\Http\Controllers\Admin\QueueController::class, 'jobDetail'])->name('jobs.detail');
});

// 字典管理
Route::get('dicts/by-code/{code}/items', [\App\Http\Controllers\Admin\DictController::class, 'itemsByCode'])->name('dicts.items-by-code');
Route::apiResource('dicts', \App\Http\Controllers\Admin\DictController::class);
Route::apiResource('dict-items', \App\Http\Controllers\Admin\DictItemController::class);

// AI 聊天
Route::group(['prefix' => 'chat', 'as' => 'chat.'], function (Registrar $registrar) {
    $registrar->get('conversations', [\App\Http\Controllers\Admin\ChatController::class, 'conversations'])->name('conversations');
    $registrar->get('conversations/{conversationId}', [\App\Http\Controllers\Admin\ChatController::class, 'conversation'])->name('conversation');
    $registrar->post('', [\App\Http\Controllers\Admin\ChatController::class, 'stream'])->name('send');
    $registrar->post('approve', [\App\Http\Controllers\Admin\ChatController::class, 'approve'])->name('approve');
    $registrar->delete('conversations/{conversationId}', [\App\Http\Controllers\Admin\ChatController::class, 'destroy'])->name('destroy');
});

// 评论管理（运营管理）
Route::group(['prefix' => 'comments', 'as' => 'comments.'], function (Registrar $registrar) {
    $registrar->get('{comment}/replies', [\App\Http\Controllers\Admin\CommentController::class, 'replies'])->name('replies');
    $registrar->post('{comment}/review', [\App\Http\Controllers\Admin\CommentController::class, 'review'])->name('review');
    $registrar->get('export', [\App\Http\Controllers\Admin\CommentController::class, 'export'])->name('export');
});
Route::apiResource('comments', \App\Http\Controllers\Admin\CommentController::class)->except(['store', 'update']);

// 用户反馈
Route::group(['prefix' => 'feedbacks', 'as' => 'feedbacks.'], function (Registrar $registrar) {
    $registrar->get('stats', [\App\Http\Controllers\Admin\FeedbackController::class, 'stats'])->name('stats');
    $registrar->get('export', [\App\Http\Controllers\Admin\FeedbackController::class, 'export'])->name('export');
});
Route::apiResource('feedbacks', \App\Http\Controllers\Admin\FeedbackController::class)->except(['store']);

// Banner / 轮播图管理（运营管理）
Route::group(['prefix' => 'banners', 'as' => 'banners.'], function (Registrar $registrar) {
    $registrar->get('positions', [\App\Http\Controllers\Admin\BannerController::class, 'positions'])->name('positions');
    $registrar->put('{banner}/toggle-status', [\App\Http\Controllers\Admin\BannerController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->post('sort', [\App\Http\Controllers\Admin\BannerController::class, 'sort'])->name('sort');
});
Route::apiResource('banners', \App\Http\Controllers\Admin\BannerController::class);

// 公告管理（运营管理）
Route::group(['prefix' => 'announcements', 'as' => 'announcements.'], function (Registrar $registrar) {
    $registrar->put('{announcement}/toggle-status', [\App\Http\Controllers\Admin\AnnouncementController::class, 'toggleStatus'])->name('toggle-status');
});
Route::apiResource('announcements', \App\Http\Controllers\Admin\AnnouncementController::class);

// 协议管理（运营管理）
Route::group(['prefix' => 'agreements', 'as' => 'agreements.'], function (Registrar $registrar) {
    $registrar->put('{agreement}/toggle-status', [\App\Http\Controllers\Admin\AgreementController::class, 'toggleStatus'])->name('toggle-status');
});
Route::apiResource('agreements', \App\Http\Controllers\Admin\AgreementController::class);

// 敏感词管理（运营管理 / 内容安全）
Route::group(['prefix' => 'sensitive-words', 'as' => 'sensitive-words.'], function (Registrar $registrar) {
    $registrar->post('import', [\App\Http\Controllers\Admin\SensitiveWordController::class, 'import'])->name('import');
    $registrar->post('refresh', [\App\Http\Controllers\Admin\SensitiveWordController::class, 'refresh'])->name('refresh');
    $registrar->put('{sensitive_word}/toggle-status', [\App\Http\Controllers\Admin\SensitiveWordController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->get('options', [\App\Http\Controllers\Admin\SensitiveWordController::class, 'options'])->name('options');
});
Route::apiResource('sensitive-words', \App\Http\Controllers\Admin\SensitiveWordController::class);

// 任务分组管理（运营管理）
Route::group(['prefix' => 'task-groups', 'as' => 'task-groups.'], function (Registrar $registrar) {
    $registrar->get('options', [\App\Http\Controllers\Admin\TaskGroupController::class, 'options'])->name('options');
    $registrar->put('{taskGroup}/toggle-status', [\App\Http\Controllers\Admin\TaskGroupController::class, 'toggleStatus'])->name('toggle-status');
});
Route::apiResource('task-groups', \App\Http\Controllers\Admin\TaskGroupController::class);

// 任务管理（运营管理）
Route::group(['prefix' => 'tasks', 'as' => 'tasks.'], function (Registrar $registrar) {
    $registrar->put('{task}/toggle-status', [\App\Http\Controllers\Admin\TaskController::class, 'toggleStatus'])->name('toggle-status');
});
Route::apiResource('tasks', \App\Http\Controllers\Admin\TaskController::class);

// 任务日志（运营管理）—— 只读
Route::apiResource('task-logs', \App\Http\Controllers\Admin\TaskLogController::class)
    ->only(['index', 'show']);

// 用户分群管理（用户管理）
Route::group(['prefix' => 'user-segments', 'as' => 'user-segments.'], function (Registrar $registrar) {
    $registrar->get('options', [\App\Http\Controllers\Admin\UserSegmentController::class, 'options'])->name('options');
    $registrar->post('batch-attach', [\App\Http\Controllers\Admin\UserSegmentController::class, 'batchAttach'])->name('batch-attach');
    $registrar->post('batch-detach', [\App\Http\Controllers\Admin\UserSegmentController::class, 'batchDetach'])->name('batch-detach');
    $registrar->put('{segment}/toggle-status', [\App\Http\Controllers\Admin\UserSegmentController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->post('{segment}/sync', [\App\Http\Controllers\Admin\UserSegmentController::class, 'sync'])->name('sync');
    $registrar->get('{segment}/preview', [\App\Http\Controllers\Admin\UserSegmentController::class, 'preview'])->name('preview');
    $registrar->get('{segment}/users', [\App\Http\Controllers\Admin\UserSegmentController::class, 'users'])->name('users');
    $registrar->post('{segment}/attach', [\App\Http\Controllers\Admin\UserSegmentController::class, 'attach'])->name('attach');
    $registrar->post('{segment}/detach', [\App\Http\Controllers\Admin\UserSegmentController::class, 'detach'])->name('detach');
});
Route::apiResource('user-segments', \App\Http\Controllers\Admin\UserSegmentController::class)->except(['create', 'edit']);

// 分群异步任务查询（提交人可见自己的任务）
Route::get('segment-tasks', [\App\Http\Controllers\Admin\SegmentTaskController::class, 'index'])->name('segment-tasks.index');
Route::get('segment-tasks/{task}', [\App\Http\Controllers\Admin\SegmentTaskController::class, 'show'])->name('segment-tasks.show');

// 实名认证审核
Route::group(['prefix' => 'identity-verifications', 'as' => 'identity-verifications.'], function (Registrar $registrar) {
    $registrar->get('stats', [\App\Http\Controllers\Admin\IdentityVerificationController::class, 'stats'])->name('stats');
    $registrar->get('', [\App\Http\Controllers\Admin\IdentityVerificationController::class, 'index'])->name('index');
    $registrar->get('{verification}', [\App\Http\Controllers\Admin\IdentityVerificationController::class, 'show'])->name('show');
    $registrar->post('{verification}/approve', [\App\Http\Controllers\Admin\IdentityVerificationController::class, 'approve'])->name('approve');
    $registrar->post('{verification}/reject', [\App\Http\Controllers\Admin\IdentityVerificationController::class, 'reject'])->name('reject');
});

// 开放平台账户管理
Route::group(['prefix' => 'open-accounts', 'as' => 'open-accounts.'], function (Registrar $registrar) {
    $registrar->get('trash', [\App\Http\Controllers\Admin\OpenAccountController::class, 'trash'])->name('trash');
    $registrar->put('{open_account}/restore', [\App\Http\Controllers\Admin\OpenAccountController::class, 'restore'])->name('restore');
    $registrar->delete('{open_account}/force-delete', [\App\Http\Controllers\Admin\OpenAccountController::class, 'forceDelete'])->name('force-delete');
    $registrar->put('{open_account}/toggle-status', [\App\Http\Controllers\Admin\OpenAccountController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->post('{open_account}/rotate-token', [\App\Http\Controllers\Admin\OpenAccountController::class, 'rotateToken'])->name('rotateToken');
});
Route::apiResource('open-accounts', \App\Http\Controllers\Admin\OpenAccountController::class);
