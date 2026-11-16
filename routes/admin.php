<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Support\Facades\Route;

// 认证路由
Route::group(['prefix' => 'auth'], function (Registrar $registrar) {
    $registrar->post('login', [\App\Http\Controllers\Admin\Auth\AuthController::class, 'login'])->name('login');
    $registrar->post('wx-login', [\App\Http\Controllers\Admin\Auth\AuthController::class, 'wxLogin'])->name('wx_login'); // 微信公众号授权登录
    $registrar->post('refresh-token', [\App\Http\Controllers\Admin\Auth\AuthController::class, 'refreshToken'])->name('refresh_token'); // 重新签发个人访问令牌
    $registrar->get('tokens', [\App\Http\Controllers\Admin\Auth\AuthController::class, 'tokens'])->name('tokens'); // 查询已经签发的所有令牌
    $registrar->delete('tokens/{tokenId}', [\App\Http\Controllers\Admin\Auth\AuthController::class, 'destroyToken'])->name('destroy_token'); // 销毁指定的 Token
    $registrar->post('logout', [\App\Http\Controllers\Admin\Auth\AuthController::class, 'logout'])->name('logout');
    $registrar->get('info', [\App\Http\Controllers\Admin\Auth\AuthController::class, 'info'])->name('info');
});

// 角色管理
Route::group(['prefix' => 'roles', 'as' => 'roles.'], function (Registrar $registrar) {
    $registrar->get('permissions', [\App\Http\Controllers\Admin\System\RoleController::class, 'allPermissions'])->name('permissions');
    $registrar->get('{role}/permissions', [\App\Http\Controllers\Admin\System\RoleController::class, 'permissions'])->name('get-permissions');
    $registrar->put('{role}/permissions', [\App\Http\Controllers\Admin\System\RoleController::class, 'assignPermissions'])->name('assign-permissions');
});
Route::apiResource('roles', \App\Http\Controllers\Admin\System\RoleController::class);

// 应用管理
Route::apiResource('apps', \App\Http\Controllers\Admin\App\AppReleaseController::class);

// 版本管理
Route::group(['prefix' => 'apps/{app}/versions', 'as' => 'apps.versions.'], function (Registrar $registrar) {
    $registrar->get('', [\App\Http\Controllers\Admin\App\AppVersionController::class, 'index'])->name('index');
    $registrar->post('', [\App\Http\Controllers\Admin\App\AppVersionController::class, 'store'])->name('store');
    $registrar->get('{version}', [\App\Http\Controllers\Admin\App\AppVersionController::class, 'show'])->name('show');
    $registrar->put('{version}', [\App\Http\Controllers\Admin\App\AppVersionController::class, 'update'])->name('update');
    $registrar->delete('{version}', [\App\Http\Controllers\Admin\App\AppVersionController::class, 'destroy'])->name('destroy');
    $registrar->post('{version}/publish', [\App\Http\Controllers\Admin\App\AppVersionController::class, 'publish'])->name('publish');
    $registrar->post('{version}/withdraw', [\App\Http\Controllers\Admin\App\AppVersionController::class, 'withdraw'])->name('withdraw');
    $registrar->post('{version}/obsolete', [\App\Http\Controllers\Admin\App\AppVersionController::class, 'obsolete'])->name('obsolete');
});

// 前端路由配置
Route::get('routes', [\App\Http\Controllers\Admin\System\MainController::class, 'routes'])->name('routes');

// 公共选项接口
Route::get('common/source-types', [\App\Http\Controllers\Admin\System\CommonController::class, 'sourceTypes'])->name('common.source-types');

// 清理应用缓存
Route::post('cache/clear', [\App\Http\Controllers\Admin\System\MainController::class, 'clearCache'])->name('cache.clear');

// 数据概览
Route::get('dashboard/stats', [\App\Http\Controllers\Admin\System\DashboardController::class, 'stats'])->name('dashboard.stats');

// 通知管理
Route::group(['prefix' => 'notifications', 'as' => 'notifications.'], function (Registrar $registrar) {
    $registrar->get('', [\App\Http\Controllers\Admin\System\NotificationController::class, 'index'])->name('index');
    $registrar->get('unread', [\App\Http\Controllers\Admin\System\NotificationController::class, 'unread'])->name('unread');
    $registrar->put('mark-all-read', [\App\Http\Controllers\Admin\System\NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
    $registrar->put('mark-read', [\App\Http\Controllers\Admin\System\NotificationController::class, 'markAsRead'])->name('mark-read');
    $registrar->delete('clear-read', [\App\Http\Controllers\Admin\System\NotificationController::class, 'clearRead'])->name('clear-read');
});

// 菜单管理
Route::apiResource('menus', \App\Http\Controllers\Admin\System\MenuController::class);

// 管理员管理
Route::group(['prefix' => 'admins', 'as' => 'admins.'], function (Registrar $registrar) {
    $registrar->get('profile', [\App\Http\Controllers\Admin\System\AdminController::class, 'profile'])->name('profile');
    $registrar->put('profile', [\App\Http\Controllers\Admin\System\AdminController::class, 'updateProfile'])->name('update-profile');
    $registrar->post('avatar', [\App\Http\Controllers\Admin\System\AdminController::class, 'updateAvatar'])->name('update-avatar');
    $registrar->get('{admin}/roles', [\App\Http\Controllers\Admin\System\AdminController::class, 'roles'])->name('roles');
    $registrar->get('{admin}/login-histories', [\App\Http\Controllers\Admin\System\AdminController::class, 'loginHistories'])->name('login-histories');
    $registrar->put('{admin}/roles', [\App\Http\Controllers\Admin\System\AdminController::class, 'assignRoles'])->name('assign-roles');
    $registrar->put('{admin}/toggle-status', [\App\Http\Controllers\Admin\System\AdminController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->put('{admin}/reset-password', [\App\Http\Controllers\Admin\System\AdminController::class, 'resetPassword'])->name('reset-password');
    $registrar->put('change-password', [\App\Http\Controllers\Admin\System\AdminController::class, 'changePassword'])->name('change-password');
});
Route::apiResource('admins', \App\Http\Controllers\Admin\System\AdminController::class);

// 用户管理
Route::group(['prefix' => 'users', 'as' => 'users.'], function (Registrar $registrar) {
    $registrar->get('trash', [\App\Http\Controllers\Admin\User\UserController::class, 'trash'])->name('trash');
    $registrar->put('{user}/restore', [\App\Http\Controllers\Admin\User\UserController::class, 'restore'])->name('restore');
    $registrar->delete('{user}/force-delete', [\App\Http\Controllers\Admin\User\UserController::class, 'forceDelete'])->name('force-delete');
    $registrar->get('{user}/login-histories', [\App\Http\Controllers\Admin\User\UserController::class, 'loginHistories'])->name('login-histories');
    $registrar->get('{user}/socials', [\App\Http\Controllers\Admin\User\UserController::class, 'socials'])->name('socials');
    $registrar->get('{user}/point-trades', [\App\Http\Controllers\Admin\User\UserController::class, 'pointTrades'])->name('point-trades');
    $registrar->get('{user}/coin-trades', [\App\Http\Controllers\Admin\User\UserController::class, 'coinTrades'])->name('coin-trades');
    $registrar->get('{user}/wallet', [\App\Http\Controllers\Admin\Wallet\WalletController::class, 'userWallet'])->name('wallet');
    $registrar->get('{user}/wallet-trades', [\App\Http\Controllers\Admin\Wallet\WalletController::class, 'userTrades'])->name('wallet-trades');
    $registrar->get('{user}/withdraw-accounts', [\App\Http\Controllers\Admin\Wallet\WalletController::class, 'userWithdrawAccounts'])->name('withdraw-accounts');
    $registrar->put('{user}/adjust-wallet', [\App\Http\Controllers\Admin\Wallet\WalletController::class, 'adjust'])->name('adjust-wallet');
    $registrar->get('{user}/point-trades/export', [\App\Http\Controllers\Admin\User\UserController::class, 'exportPointTrades'])->name('point-trades.export');
    $registrar->get('{user}/coin-trades/export', [\App\Http\Controllers\Admin\User\UserController::class, 'exportCoinTrades'])->name('coin-trades.export');
    $registrar->put('{user}/toggle-status', [\App\Http\Controllers\Admin\User\UserController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->put('{user}/reset-password', [\App\Http\Controllers\Admin\User\UserController::class, 'resetPassword'])->name('reset-password');
    $registrar->put('{user}/reset-contact', [\App\Http\Controllers\Admin\User\UserController::class, 'resetContact'])->name('reset-contact');
    $registrar->put('{user}/adjust-balance', [\App\Http\Controllers\Admin\User\UserController::class, 'adjustBalance'])->name('adjust-balance');
    $registrar->put('{user}/extend-vip', [\App\Http\Controllers\Admin\User\UserController::class, 'extendVip'])->name('extend-vip');
    $registrar->get('export', [\App\Http\Controllers\Admin\User\UserController::class, 'export'])->name('export');
});
Route::apiResource('users', \App\Http\Controllers\Admin\User\UserController::class)->except(['store']);

// 钱包管理
Route::group(['prefix' => 'wallet', 'as' => 'wallet.'], function (Registrar $registrar) {
    $registrar->get('stats', [\App\Http\Controllers\Admin\Wallet\WalletController::class, 'stats'])->name('stats'); // 钱包统计
    $registrar->get('trades/export', [\App\Http\Controllers\Admin\Wallet\WalletController::class, 'export'])->name('trades.export'); // 导出钱包流水
    $registrar->get('trades', [\App\Http\Controllers\Admin\Wallet\WalletController::class, 'index'])->name('trades.index'); // 全局流水列表
    $registrar->get('trades/{trade}', [\App\Http\Controllers\Admin\Wallet\WalletController::class, 'show'])->name('trades.show'); // 流水详情

    // 充值订单
    $registrar->get('recharges/stats', [\App\Http\Controllers\Admin\Wallet\RechargeController::class, 'stats'])->name('recharges.stats'); // 充值统计
    $registrar->get('recharges/export', [\App\Http\Controllers\Admin\Wallet\RechargeController::class, 'export'])->name('recharges.export'); // 导出充值订单
    $registrar->get('recharges', [\App\Http\Controllers\Admin\Wallet\RechargeController::class, 'index'])->name('recharges.index'); // 充值订单列表
    $registrar->get('recharges/{recharge}', [\App\Http\Controllers\Admin\Wallet\RechargeController::class, 'show'])->name('recharges.show'); // 充值订单详情
    $registrar->post('recharges/{recharge}/confirm', [\App\Http\Controllers\Admin\Wallet\RechargeController::class, 'confirm'])->name('recharges.confirm'); // 手动确认到账
    $registrar->post('recharges/{recharge}/close', [\App\Http\Controllers\Admin\Wallet\RechargeController::class, 'close'])->name('recharges.close'); // 关闭订单

    // 提现申请
    $registrar->get('withdraws/stats', [\App\Http\Controllers\Admin\Wallet\WithdrawController::class, 'stats'])->name('withdraws.stats'); // 提现统计
    $registrar->get('withdraws/export', [\App\Http\Controllers\Admin\Wallet\WithdrawController::class, 'export'])->name('withdraws.export'); // 导出提现申请
    $registrar->get('withdraws', [\App\Http\Controllers\Admin\Wallet\WithdrawController::class, 'index'])->name('withdraws.index'); // 提现申请列表
    $registrar->get('withdraws/{withdraw}', [\App\Http\Controllers\Admin\Wallet\WithdrawController::class, 'show'])->name('withdraws.show'); // 提现申请详情
    $registrar->post('withdraws/{withdraw}/approve', [\App\Http\Controllers\Admin\Wallet\WithdrawController::class, 'approve'])->name('withdraws.approve'); // 审核通过
    $registrar->post('withdraws/{withdraw}/reject', [\App\Http\Controllers\Admin\Wallet\WithdrawController::class, 'reject'])->name('withdraws.reject'); // 审核驳回
    $registrar->post('withdraws/{withdraw}/transfer', [\App\Http\Controllers\Admin\Wallet\WithdrawController::class, 'transfer'])->name('withdraws.transfer'); // 标记已打款
    $registrar->post('withdraws/{withdraw}/fail', [\App\Http\Controllers\Admin\Wallet\WithdrawController::class, 'fail'])->name('withdraws.fail'); // 标记打款失败
});

// 配置管理
Route::group(['prefix' => 'settings', 'as' => 'settings.'], function (Registrar $registrar) {
    $registrar->get('groups', [\App\Http\Controllers\Admin\System\SettingController::class, 'groups'])->name('groups');
    $registrar->get('input-types', [\App\Http\Controllers\Admin\System\SettingController::class, 'inputTypes'])->name('input-types');
    $registrar->put('batch', [\App\Http\Controllers\Admin\System\SettingController::class, 'batchUpdate'])->name('batch');
});
Route::apiResource('settings', \App\Http\Controllers\Admin\System\SettingController::class);

// 地区管理
Route::apiResource('areas', \App\Http\Controllers\Admin\System\AreaController::class);

// 手机验证码管理（仅列表、详情，只读）
Route::get('phone-codes', [\App\Http\Controllers\Admin\System\PhoneCodeController::class, 'index'])->name('phone-codes.index');
Route::get('phone-codes/{id}', [\App\Http\Controllers\Admin\System\PhoneCodeController::class, 'show'])->name('phone-codes.show');

// 邮件验证码管理（仅列表、详情，只读）
Route::get('mail-codes', [\App\Http\Controllers\Admin\System\MailCodeController::class, 'index'])->name('mail-codes.index');
Route::get('mail-codes/{id}', [\App\Http\Controllers\Admin\System\MailCodeController::class, 'show'])->name('mail-codes.show');

// 微信公众号配置（只读）
Route::get('wechat', [\App\Http\Controllers\Admin\System\WechatController::class, 'show'])->name('wechat.show');

// 上传管理
Route::group(['prefix' => 'uploader', 'as' => 'uploader.'], function (Registrar $registrar) {
    $registrar->post('file', [\App\Http\Controllers\Admin\Storage\UploaderController::class, 'file'])->name('file');
    $registrar->post('image', [\App\Http\Controllers\Admin\Storage\UploaderController::class, 'image'])->name('image');
    $registrar->post('video', [\App\Http\Controllers\Admin\Storage\UploaderController::class, 'video'])->name('video');
    $registrar->post('apk', [\App\Http\Controllers\Admin\Storage\UploaderController::class, 'apk'])->name('apk');
    $registrar->post('token', [\App\Http\Controllers\Admin\Storage\UploaderController::class, 'uploadToken'])->name('token');
});

// 附件管理
Route::group(['prefix' => 'attachments', 'as' => 'attachments.'], function (Registrar $registrar) {
    $registrar->get('disks', [\App\Http\Controllers\Admin\Storage\AttachmentController::class, 'disks'])->name('disks');
    $registrar->post('register', [\App\Http\Controllers\Admin\Storage\AttachmentController::class, 'register'])->name('register');
    $registrar->delete('', [\App\Http\Controllers\Admin\Storage\AttachmentController::class, 'batchDestroy'])->name('batch-destroy');
    $registrar->get('{id}/download', [\App\Http\Controllers\Admin\Storage\AttachmentController::class, 'download'])->name('download');
    $registrar->get('{id}/temporary-url', [\App\Http\Controllers\Admin\Storage\AttachmentController::class, 'temporaryUrl'])->name('temporary-url');
    $registrar->put('{id}/rename', [\App\Http\Controllers\Admin\Storage\AttachmentController::class, 'rename'])->name('rename');
    $registrar->put('{id}/move', [\App\Http\Controllers\Admin\Storage\AttachmentController::class, 'move'])->name('move');
    $registrar->get('', [\App\Http\Controllers\Admin\Storage\AttachmentController::class, 'index'])->name('index');
    $registrar->get('{id}', [\App\Http\Controllers\Admin\Storage\AttachmentController::class, 'show'])->name('show');
    $registrar->delete('{id}', [\App\Http\Controllers\Admin\Storage\AttachmentController::class, 'destroy'])->name('destroy');
});

// 性能监控（Laravel Pulse 数据）
Route::group(['prefix' => 'monitor', 'as' => 'monitor.'], function (Registrar $registrar) {
    $registrar->get('servers', [\App\Http\Controllers\Admin\Monitor\MonitorController::class, 'servers'])->name('servers');
    $registrar->get('queues', [\App\Http\Controllers\Admin\Monitor\MonitorController::class, 'queues'])->name('queues');
    $registrar->get('reverb/connections', [\App\Http\Controllers\Admin\Monitor\MonitorController::class, 'reverbConnections'])->name('reverb.connections');
    $registrar->get('reverb/messages', [\App\Http\Controllers\Admin\Monitor\MonitorController::class, 'reverbMessages'])->name('reverb.messages');
    $registrar->get('cache', [\App\Http\Controllers\Admin\Monitor\MonitorController::class, 'cache'])->name('cache');
    $registrar->get('exceptions', [\App\Http\Controllers\Admin\Monitor\MonitorController::class, 'exceptions'])->name('exceptions');
    $registrar->get('slow-queries', [\App\Http\Controllers\Admin\Monitor\MonitorController::class, 'slowQueries'])->name('slow-queries');
    $registrar->get('slow-requests', [\App\Http\Controllers\Admin\Monitor\MonitorController::class, 'slowRequests'])->name('slow-requests');
    $registrar->get('slow-jobs', [\App\Http\Controllers\Admin\Monitor\MonitorController::class, 'slowJobs'])->name('slow-jobs');
    $registrar->get('slow-outgoing-requests', [\App\Http\Controllers\Admin\Monitor\MonitorController::class, 'slowOutgoingRequests'])->name('slow-outgoing-requests');
    $registrar->get('usage', [\App\Http\Controllers\Admin\Monitor\MonitorController::class, 'usage'])->name('usage');
});

// 调试面板（Laravel Telescope 数据）
Route::group(['prefix' => 'debug', 'as' => 'debug.'], function (Registrar $registrar) {
    $registrar->get('tags', [\App\Http\Controllers\Admin\Monitor\DebugController::class, 'tags'])->name('tags');
    $registrar->post('tags', [\App\Http\Controllers\Admin\Monitor\DebugController::class, 'monitor'])->name('monitor');
    $registrar->delete('tags', [\App\Http\Controllers\Admin\Monitor\DebugController::class, 'unmonitor'])->name('unmonitor');
    $registrar->post('toggle-recording', [\App\Http\Controllers\Admin\Monitor\DebugController::class, 'toggleRecording'])->name('toggle-recording');
    $registrar->delete('entries', [\App\Http\Controllers\Admin\Monitor\DebugController::class, 'destroy'])->name('destroy');
    $registrar->get('entries', [\App\Http\Controllers\Admin\Monitor\DebugController::class, 'index'])->name('index');
    $registrar->get('entries/{id}', [\App\Http\Controllers\Admin\Monitor\DebugController::class, 'show'])->name('show');
    $registrar->put('entries/{id}/resolve', [\App\Http\Controllers\Admin\Monitor\DebugController::class, 'resolve'])->name('resolve');
});

// 定时任务管理（含调度日志）
Route::group(['prefix' => 'schedules', 'as' => 'schedules.'], function (Registrar $registrar) {
    // 定时任务 CRUD
    $registrar->get('', [\App\Http\Controllers\Admin\Monitor\ScheduleController::class, 'index'])->name('index');
    $registrar->get('commands', [\App\Http\Controllers\Admin\Monitor\ScheduleController::class, 'commands'])->name('commands');
    $registrar->post('', [\App\Http\Controllers\Admin\Monitor\ScheduleController::class, 'store'])->name('store');

    // 调度日志（只读，放在 {id} 之前避免路由冲突）
    $registrar->get('logs', [\App\Http\Controllers\Admin\Monitor\ScheduleLogController::class, 'index'])->name('logs.index');
    $registrar->get('logs/{id}', [\App\Http\Controllers\Admin\Monitor\ScheduleLogController::class, 'show'])->name('logs.show');

    // 定时任务操作（含 {id} 的路由放在静态段之后）
    $registrar->get('{id}', [\App\Http\Controllers\Admin\Monitor\ScheduleController::class, 'show'])->name('show');
    $registrar->put('{id}', [\App\Http\Controllers\Admin\Monitor\ScheduleController::class, 'update'])->name('update');
    $registrar->patch('{id}/toggle', [\App\Http\Controllers\Admin\Monitor\ScheduleController::class, 'toggle'])->name('toggle');
    $registrar->post('{id}/execute', [\App\Http\Controllers\Admin\Monitor\ScheduleController::class, 'execute'])->name('execute');
    $registrar->delete('{id}', [\App\Http\Controllers\Admin\Monitor\ScheduleController::class, 'destroy'])->name('destroy');
});

// 操作审计日志（仅列表、详情，只读）
Route::group(['prefix' => 'audit-logs', 'as' => 'audit-logs.'], function (Registrar $registrar) {
    $registrar->get('', [\App\Http\Controllers\Admin\System\AuditLogController::class, 'index'])->name('index');
    $registrar->get('export', [\App\Http\Controllers\Admin\System\AuditLogController::class, 'export'])->name('export');
    $registrar->get('{id}', [\App\Http\Controllers\Admin\System\AuditLogController::class, 'show'])->name('show');
});

// 全局登录历史（跨管理员与前台用户，只读）
Route::get('login-histories', [\App\Http\Controllers\Admin\System\LoginHistoryController::class, 'index'])->name('login-histories.index');

// 邀请记录（只读）
Route::get('invite-records', [\App\Http\Controllers\Admin\User\InviteRecordController::class, 'index'])->name('invite-records.index');
Route::get('invite-records/{inviteRecord}', [\App\Http\Controllers\Admin\User\InviteRecordController::class, 'show'])->name('invite-records.show');

// 队列管理（Laravel Horizon 数据代理）
Route::group(['prefix' => 'queue', 'as' => 'queue.'], function (Registrar $registrar) {
    $registrar->get('stats', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'stats'])->name('stats');
    $registrar->get('workload', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'workload'])->name('workload');
    $registrar->get('masters', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'masters'])->name('masters');

    // 标签监控
    $registrar->get('monitoring/tags', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'monitoringTags'])->name('monitoring.tags');
    $registrar->get('monitoring/jobs', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'monitoringJobs'])->name('monitoring.jobs');
    $registrar->post('monitoring/tags', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'monitorTag'])->name('monitoring.tag');
    $registrar->delete('monitoring/tags/{tag}', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'stopMonitoringTag'])->name('monitoring.tag.destroy');

    // 指标
    $registrar->get('metrics/jobs', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'jobMetrics'])->name('metrics.jobs');
    $registrar->get('metrics/jobs/{id}', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'jobMetricsDetail'])->name('metrics.jobs.detail');
    $registrar->get('metrics/queues', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'queueMetrics'])->name('metrics.queues');
    $registrar->get('metrics/queues/{id}', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'queueMetricsDetail'])->name('metrics.queues.detail');

    // 批处理
    $registrar->get('batches', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'batches'])->name('batches');
    $registrar->get('batches/{id}', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'batchDetail'])->name('batches.detail');
    $registrar->post('batches/{id}/retry', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'retryBatch'])->name('batches.retry');

    // 任务列表
    $registrar->get('jobs/pending', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'pendingJobs'])->name('jobs.pending');
    $registrar->get('jobs/completed', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'completedJobs'])->name('jobs.completed');
    $registrar->get('jobs/silenced', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'silencedJobs'])->name('jobs.silenced');
    $registrar->post('jobs/failed/{id}/retry', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'retryJob'])->name('jobs.retry');
    $registrar->get('jobs/failed', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'failedJobs'])->name('jobs.failed');
    $registrar->get('jobs/failed/{id}', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'failedJobDetail'])->name('jobs.failed.detail');
    $registrar->get('jobs/{id}', [\App\Http\Controllers\Admin\Monitor\QueueController::class, 'jobDetail'])->name('jobs.detail');
});

// 字典管理
Route::get('dicts/by-code/{code}/items', [\App\Http\Controllers\Admin\System\DictController::class, 'itemsByCode'])->name('dicts.items-by-code');
Route::apiResource('dicts', \App\Http\Controllers\Admin\System\DictController::class);
Route::apiResource('dict-items', \App\Http\Controllers\Admin\System\DictItemController::class);

// AI 聊天
Route::group(['prefix' => 'chat', 'as' => 'chat.'], function (Registrar $registrar) {
    $registrar->post('upload-file', [\App\Http\Controllers\Admin\Ai\ChatController::class, 'uploadFile'])->name('upload_file');
    $registrar->get('meta', [\App\Http\Controllers\Admin\Ai\ChatController::class, 'meta'])->name('meta');
    $registrar->get('conversations', [\App\Http\Controllers\Admin\Ai\ChatController::class, 'conversations'])->name('conversations');
    $registrar->get('conversations/{conversationId}', [\App\Http\Controllers\Admin\Ai\ChatController::class, 'conversation'])->name('conversation');
    $registrar->post('', [\App\Http\Controllers\Admin\Ai\ChatController::class, 'stream'])->name('send');
    $registrar->post('approve', [\App\Http\Controllers\Admin\Ai\ChatController::class, 'approve'])->name('approve');
    $registrar->delete('conversations/{conversationId}', [\App\Http\Controllers\Admin\Ai\ChatController::class, 'destroy'])->name('destroy');
});

// AI 模型管理
Route::group(['prefix' => 'chat-models', 'as' => 'chat-models.'], function (Registrar $registrar) {
    $registrar->put('{chat_model}/toggle-status', [\App\Http\Controllers\Admin\Ai\ChatModelController::class, 'toggleStatus'])->name('toggle-status');
});
Route::apiResource('chat-models', \App\Http\Controllers\Admin\Ai\ChatModelController::class);

// AI 知识库管理
Route::group(['prefix' => 'knowledge-documents', 'as' => 'knowledge-documents.'], function (Registrar $registrar) {
    $registrar->put('{knowledge_document}/toggle-status', [\App\Http\Controllers\Admin\Ai\KnowledgeDocumentController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->post('{knowledge_document}/reindex', [\App\Http\Controllers\Admin\Ai\KnowledgeDocumentController::class, 'reindex'])->name('reindex');
    $registrar->get('{knowledge_document}/chunks', [\App\Http\Controllers\Admin\Ai\KnowledgeDocumentController::class, 'chunks'])->name('chunks');
    $registrar->post('search', [\App\Http\Controllers\Admin\Ai\KnowledgeDocumentController::class, 'search'])->name('search');
});
Route::apiResource('knowledge-documents', \App\Http\Controllers\Admin\Ai\KnowledgeDocumentController::class);

// 评论管理（运营管理）
Route::group(['prefix' => 'comments', 'as' => 'comments.'], function (Registrar $registrar) {
    $registrar->get('{comment}/replies', [\App\Http\Controllers\Admin\Content\CommentController::class, 'replies'])->name('replies');
    $registrar->post('{comment}/review', [\App\Http\Controllers\Admin\Content\CommentController::class, 'review'])->name('review');
    $registrar->post('{comment}/ai-review', [\App\Http\Controllers\Admin\Content\CommentController::class, 'aiReview'])->name('ai-review');
    $registrar->get('export', [\App\Http\Controllers\Admin\Content\CommentController::class, 'export'])->name('export');
});
Route::apiResource('comments', \App\Http\Controllers\Admin\Content\CommentController::class)->except(['store', 'update']);

// 用户反馈
Route::group(['prefix' => 'feedbacks', 'as' => 'feedbacks.'], function (Registrar $registrar) {
    $registrar->get('stats', [\App\Http\Controllers\Admin\Operation\FeedbackController::class, 'stats'])->name('stats');
    $registrar->get('export', [\App\Http\Controllers\Admin\Operation\FeedbackController::class, 'export'])->name('export');
    $registrar->post('{feedback}/draft-reply', [\App\Http\Controllers\Admin\Operation\FeedbackController::class, 'draftReply'])->name('draft-reply');
});
Route::apiResource('feedbacks', \App\Http\Controllers\Admin\Operation\FeedbackController::class)->except(['store']);

// Banner / 轮播图管理（运营管理）
Route::group(['prefix' => 'banners', 'as' => 'banners.'], function (Registrar $registrar) {
    $registrar->get('positions', [\App\Http\Controllers\Admin\Operation\BannerController::class, 'positions'])->name('positions');
    $registrar->put('{banner}/toggle-status', [\App\Http\Controllers\Admin\Operation\BannerController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->post('sort', [\App\Http\Controllers\Admin\Operation\BannerController::class, 'sort'])->name('sort');
    $registrar->post('ai-copy', [\App\Http\Controllers\Admin\Operation\BannerController::class, 'generateCopy'])->name('ai-copy');
    $registrar->post('ai-image', [\App\Http\Controllers\Admin\Operation\BannerController::class, 'generateImage'])->name('ai-image');
});
Route::apiResource('banners', \App\Http\Controllers\Admin\Operation\BannerController::class);

// 公告管理（运营管理）
Route::group(['prefix' => 'announcements', 'as' => 'announcements.'], function (Registrar $registrar) {
    $registrar->put('{announcement}/toggle-status', [\App\Http\Controllers\Admin\Operation\AnnouncementController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->post('ai-copy', [\App\Http\Controllers\Admin\Operation\AnnouncementController::class, 'generateCopy'])->name('ai-copy');
});
Route::apiResource('announcements', \App\Http\Controllers\Admin\Operation\AnnouncementController::class);

// 协议管理（运营管理）
Route::group(['prefix' => 'agreements', 'as' => 'agreements.'], function (Registrar $registrar) {
    $registrar->put('{agreement}/toggle-status', [\App\Http\Controllers\Admin\Operation\AgreementController::class, 'toggleStatus'])->name('toggle-status');
});
Route::apiResource('agreements', \App\Http\Controllers\Admin\Operation\AgreementController::class);

// 敏感词管理（运营管理 / 内容安全）
Route::group(['prefix' => 'sensitive-words', 'as' => 'sensitive-words.'], function (Registrar $registrar) {
    $registrar->post('import', [\App\Http\Controllers\Admin\Content\SensitiveWordController::class, 'import'])->name('import');
    $registrar->post('refresh', [\App\Http\Controllers\Admin\Content\SensitiveWordController::class, 'refresh'])->name('refresh');
    $registrar->put('{sensitive_word}/toggle-status', [\App\Http\Controllers\Admin\Content\SensitiveWordController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->get('options', [\App\Http\Controllers\Admin\Content\SensitiveWordController::class, 'options'])->name('options');
});
Route::apiResource('sensitive-words', \App\Http\Controllers\Admin\Content\SensitiveWordController::class);

// 举报管理（运营管理 / 内容安全）
Route::group(['prefix' => 'reports', 'as' => 'reports.'], function (Registrar $registrar) {
    $registrar->get('stats', [\App\Http\Controllers\Admin\Content\ReportController::class, 'stats'])->name('stats');
    $registrar->get('export', [\App\Http\Controllers\Admin\Content\ReportController::class, 'export'])->name('export');
    $registrar->post('{report}/ai-triage', [\App\Http\Controllers\Admin\Content\ReportController::class, 'aiTriage'])->name('ai-triage');
});
Route::apiResource('reports', \App\Http\Controllers\Admin\Content\ReportController::class)->except(['store', 'destroy']);

// 任务分组管理（运营管理）
Route::group(['prefix' => 'task-groups', 'as' => 'task-groups.'], function (Registrar $registrar) {
    $registrar->get('options', [\App\Http\Controllers\Admin\Operation\TaskGroupController::class, 'options'])->name('options');
    $registrar->put('{taskGroup}/toggle-status', [\App\Http\Controllers\Admin\Operation\TaskGroupController::class, 'toggleStatus'])->name('toggle-status');
});
Route::apiResource('task-groups', \App\Http\Controllers\Admin\Operation\TaskGroupController::class);

// 任务管理（运营管理）
Route::group(['prefix' => 'tasks', 'as' => 'tasks.'], function (Registrar $registrar) {
    $registrar->put('{task}/toggle-status', [\App\Http\Controllers\Admin\Operation\TaskController::class, 'toggleStatus'])->name('toggle-status');
});
Route::apiResource('tasks', \App\Http\Controllers\Admin\Operation\TaskController::class);

// 任务日志（运营管理）—— 只读
Route::apiResource('task-logs', \App\Http\Controllers\Admin\Operation\TaskLogController::class)
    ->only(['index', 'show']);

// 自动化运营规则管理（运营管理）
Route::group(['prefix' => 'automation-rules', 'as' => 'automation-rules.'], function (Registrar $registrar) {
    $registrar->get('meta', [\App\Http\Controllers\Admin\Automation\AutomationRuleController::class, 'meta'])->name('meta');
    $registrar->put('{id}/toggle-status', [\App\Http\Controllers\Admin\Automation\AutomationRuleController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->post('{id}/trigger', [\App\Http\Controllers\Admin\Automation\AutomationRuleController::class, 'trigger'])->name('trigger');
});
Route::apiResource('automation-rules', \App\Http\Controllers\Admin\Automation\AutomationRuleController::class);

// 自动化运营节日管理（运营管理）
Route::group(['prefix' => 'automation-festivals', 'as' => 'automation-festivals.'], function (Registrar $registrar) {
    $registrar->get('options', [\App\Http\Controllers\Admin\Automation\AutomationFestivalController::class, 'options'])->name('options');
    $registrar->put('{id}/toggle-status', [\App\Http\Controllers\Admin\Automation\AutomationFestivalController::class, 'toggleStatus'])->name('toggle-status');
});
Route::apiResource('automation-festivals', \App\Http\Controllers\Admin\Automation\AutomationFestivalController::class);

// 自动化运营触发日志（运营管理）—— 只读
Route::apiResource('automation-logs', \App\Http\Controllers\Admin\Automation\AutomationLogController::class)
    ->only(['index', 'show']);

// 用户分群管理（用户管理）
Route::group(['prefix' => 'user-segments', 'as' => 'user-segments.'], function (Registrar $registrar) {
    $registrar->get('options', [\App\Http\Controllers\Admin\Segment\UserSegmentController::class, 'options'])->name('options');
    $registrar->post('batch-attach', [\App\Http\Controllers\Admin\Segment\UserSegmentController::class, 'batchAttach'])->name('batch-attach');
    $registrar->post('batch-detach', [\App\Http\Controllers\Admin\Segment\UserSegmentController::class, 'batchDetach'])->name('batch-detach');
    $registrar->put('{segment}/toggle-status', [\App\Http\Controllers\Admin\Segment\UserSegmentController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->post('{segment}/sync', [\App\Http\Controllers\Admin\Segment\UserSegmentController::class, 'sync'])->name('sync');
    $registrar->get('{segment}/preview', [\App\Http\Controllers\Admin\Segment\UserSegmentController::class, 'preview'])->name('preview');
    $registrar->get('{segment}/users', [\App\Http\Controllers\Admin\Segment\UserSegmentController::class, 'users'])->name('users');
    $registrar->post('{segment}/attach', [\App\Http\Controllers\Admin\Segment\UserSegmentController::class, 'attach'])->name('attach');
    $registrar->post('{segment}/detach', [\App\Http\Controllers\Admin\Segment\UserSegmentController::class, 'detach'])->name('detach');
});
Route::apiResource('user-segments', \App\Http\Controllers\Admin\Segment\UserSegmentController::class)->except(['create', 'edit']);

// 分群异步任务查询（提交人可见自己的任务）
Route::get('segment-tasks', [\App\Http\Controllers\Admin\Segment\SegmentTaskController::class, 'index'])->name('segment-tasks.index');
Route::get('segment-tasks/{task}', [\App\Http\Controllers\Admin\Segment\SegmentTaskController::class, 'show'])->name('segment-tasks.show');

// 实名认证审核
Route::group(['prefix' => 'identity-verifications', 'as' => 'identity-verifications.'], function (Registrar $registrar) {
    $registrar->get('stats', [\App\Http\Controllers\Admin\User\IdentityVerificationController::class, 'stats'])->name('stats');
    $registrar->get('', [\App\Http\Controllers\Admin\User\IdentityVerificationController::class, 'index'])->name('index');
    $registrar->get('{verification}', [\App\Http\Controllers\Admin\User\IdentityVerificationController::class, 'show'])->name('show');
    $registrar->post('{verification}/approve', [\App\Http\Controllers\Admin\User\IdentityVerificationController::class, 'approve'])->name('approve');
    $registrar->post('{verification}/reject', [\App\Http\Controllers\Admin\User\IdentityVerificationController::class, 'reject'])->name('reject');
});

// 开放平台账户管理
Route::group(['prefix' => 'open-accounts', 'as' => 'open-accounts.'], function (Registrar $registrar) {
    $registrar->get('trash', [\App\Http\Controllers\Admin\Open\OpenAccountController::class, 'trash'])->name('trash');
    $registrar->put('{open_account}/restore', [\App\Http\Controllers\Admin\Open\OpenAccountController::class, 'restore'])->name('restore');
    $registrar->delete('{open_account}/force-delete', [\App\Http\Controllers\Admin\Open\OpenAccountController::class, 'forceDelete'])->name('force-delete');
    $registrar->put('{open_account}/toggle-status', [\App\Http\Controllers\Admin\Open\OpenAccountController::class, 'toggleStatus'])->name('toggle-status');
    $registrar->post('{open_account}/rotate-token', [\App\Http\Controllers\Admin\Open\OpenAccountController::class, 'rotateToken'])->name('rotateToken');
});
Route::apiResource('open-accounts', \App\Http\Controllers\Admin\Open\OpenAccountController::class);
