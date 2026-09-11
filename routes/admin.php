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
Route::get('roles/permissions', [\App\Http\Controllers\Admin\RoleController::class, 'allPermissions'])->name('roles.permissions');
Route::get('roles/{role}/permissions', [\App\Http\Controllers\Admin\RoleController::class, 'permissions'])->name('roles.get-permissions');
Route::put('roles/{role}/permissions', [\App\Http\Controllers\Admin\RoleController::class, 'assignPermissions'])->name('roles.assign-permissions');
Route::apiResource('roles', \App\Http\Controllers\Admin\RoleController::class);

// 应用管理
Route::apiResource('apps', \App\Http\Controllers\Admin\AppReleaseController::class);

// 版本管理
Route::get('apps/{app}/versions', [\App\Http\Controllers\Admin\AppVersionController::class, 'index'])->name('apps.versions.index');
Route::post('apps/{app}/versions', [\App\Http\Controllers\Admin\AppVersionController::class, 'store'])->name('apps.versions.store');
Route::get('apps/{app}/versions/{version}', [\App\Http\Controllers\Admin\AppVersionController::class, 'show'])->name('apps.versions.show');
Route::put('apps/{app}/versions/{version}', [\App\Http\Controllers\Admin\AppVersionController::class, 'update'])->name('apps.versions.update');
Route::delete('apps/{app}/versions/{version}', [\App\Http\Controllers\Admin\AppVersionController::class, 'destroy'])->name('apps.versions.destroy');
Route::post('apps/{app}/versions/{version}/publish', [\App\Http\Controllers\Admin\AppVersionController::class, 'publish'])->name('apps.versions.publish');
Route::post('apps/{app}/versions/{version}/withdraw', [\App\Http\Controllers\Admin\AppVersionController::class, 'withdraw'])->name('apps.versions.withdraw');
Route::post('apps/{app}/versions/{version}/obsolete', [\App\Http\Controllers\Admin\AppVersionController::class, 'obsolete'])->name('apps.versions.obsolete');

// 前端路由配置
Route::get('routes', [\App\Http\Controllers\Admin\MainController::class, 'routes'])->name('routes');

// 公共选项接口
Route::get('common/source-types', [\App\Http\Controllers\Admin\CommonController::class, 'sourceTypes'])->name('common.source-types');

// 清理应用缓存
Route::post('cache/clear', [\App\Http\Controllers\Admin\MainController::class, 'clearCache'])->name('cache.clear');

// 数据概览
Route::get('dashboard/stats', [\App\Http\Controllers\Admin\DashboardController::class, 'stats'])->name('dashboard.stats');

// 通知管理
Route::get('notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications.index');
Route::get('notifications/unread', [\App\Http\Controllers\Admin\NotificationController::class, 'unread'])->name('notifications.unread');
Route::put('notifications/mark-all-read', [\App\Http\Controllers\Admin\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
Route::put('notifications/mark-read', [\App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
Route::delete('notifications/clear-read', [\App\Http\Controllers\Admin\NotificationController::class, 'clearRead'])->name('notifications.clear-read');

// 菜单管理
Route::apiResource('menus', \App\Http\Controllers\Admin\MenuController::class);

// 管理员管理
Route::get('admins/profile', [\App\Http\Controllers\Admin\AdminController::class, 'profile'])->name('admins.profile');
Route::put('admins/profile', [\App\Http\Controllers\Admin\AdminController::class, 'updateProfile'])->name('admins.update-profile');
Route::post('admins/avatar', [\App\Http\Controllers\Admin\AdminController::class, 'updateAvatar'])->name('admins.update-avatar');
Route::get('admins/{admin}/roles', [\App\Http\Controllers\Admin\AdminController::class, 'roles'])->name('admins.roles');
Route::get('admins/{admin}/login-histories', [\App\Http\Controllers\Admin\AdminController::class, 'loginHistories'])->name('admins.login-histories');
Route::put('admins/{admin}/roles', [\App\Http\Controllers\Admin\AdminController::class, 'assignRoles'])->name('admins.assign-roles');
Route::put('admins/{admin}/toggle-status', [\App\Http\Controllers\Admin\AdminController::class, 'toggleStatus'])->name('admins.toggle-status');
Route::put('admins/{admin}/reset-password', [\App\Http\Controllers\Admin\AdminController::class, 'resetPassword'])->name('admins.reset-password');
Route::put('admins/change-password', [\App\Http\Controllers\Admin\AdminController::class, 'changePassword'])->name('admins.change-password');
Route::apiResource('admins', \App\Http\Controllers\Admin\AdminController::class);

// 用户管理
Route::get('users/trash', [\App\Http\Controllers\Admin\UserController::class, 'trash'])->name('users.trash');
Route::put('users/{user}/restore', [\App\Http\Controllers\Admin\UserController::class, 'restore'])->name('users.restore');
Route::delete('users/{user}/force-delete', [\App\Http\Controllers\Admin\UserController::class, 'forceDelete'])->name('users.force-delete');
Route::get('users/{user}/login-histories', [\App\Http\Controllers\Admin\UserController::class, 'loginHistories'])->name('users.login-histories');
Route::get('users/{user}/socials', [\App\Http\Controllers\Admin\UserController::class, 'socials'])->name('users.socials');
Route::get('users/{user}/point-trades', [\App\Http\Controllers\Admin\UserController::class, 'pointTrades'])->name('users.point-trades');
Route::get('users/{user}/coin-trades', [\App\Http\Controllers\Admin\UserController::class, 'coinTrades'])->name('users.coin-trades');
Route::get('users/{user}/wallet', [\App\Http\Controllers\Admin\WalletController::class, 'userWallet'])->name('users.wallet');
Route::get('users/{user}/wallet-trades', [\App\Http\Controllers\Admin\WalletController::class, 'userTrades'])->name('users.wallet-trades');
Route::put('users/{user}/adjust-wallet', [\App\Http\Controllers\Admin\WalletController::class, 'adjust'])->name('users.adjust-wallet');
Route::get('users/{user}/point-trades/export', [\App\Http\Controllers\Admin\UserController::class, 'exportPointTrades'])->name('users.point-trades.export');
Route::get('users/{user}/coin-trades/export', [\App\Http\Controllers\Admin\UserController::class, 'exportCoinTrades'])->name('users.coin-trades.export');
Route::put('users/{user}/toggle-status', [\App\Http\Controllers\Admin\UserController::class, 'toggleStatus'])->name('users.toggle-status');
Route::put('users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
Route::put('users/{user}/reset-contact', [\App\Http\Controllers\Admin\UserController::class, 'resetContact'])->name('users.reset-contact');
Route::put('users/{user}/adjust-balance', [\App\Http\Controllers\Admin\UserController::class, 'adjustBalance'])->name('users.adjust-balance');
Route::put('users/{user}/extend-vip', [\App\Http\Controllers\Admin\UserController::class, 'extendVip'])->name('users.extend-vip');
Route::get('users/export', [\App\Http\Controllers\Admin\UserController::class, 'export'])->name('users.export');
Route::apiResource('users', \App\Http\Controllers\Admin\UserController::class)->except(['store']);

// 钱包管理
Route::group(['prefix' => 'wallet', 'as' => 'wallet.'], function (Registrar $registrar) {
    $registrar->get('stats', [\App\Http\Controllers\Admin\WalletController::class, 'stats'])->name('stats'); // 钱包统计
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
Route::get('settings/groups', [\App\Http\Controllers\Admin\SettingController::class, 'groups'])->name('settings.groups');
Route::get('settings/input-types', [\App\Http\Controllers\Admin\SettingController::class, 'inputTypes'])->name('settings.input-types');
Route::put('settings/batch', [\App\Http\Controllers\Admin\SettingController::class, 'batchUpdate'])->name('settings.batch');
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
Route::post('uploader/file', [\App\Http\Controllers\Admin\UploaderController::class, 'file'])->name('uploader.file');
Route::post('uploader/image', [\App\Http\Controllers\Admin\UploaderController::class, 'image'])->name('uploader.image');
Route::post('uploader/video', [\App\Http\Controllers\Admin\UploaderController::class, 'video'])->name('uploader.video');
Route::post('uploader/apk', [\App\Http\Controllers\Admin\UploaderController::class, 'apk'])->name('uploader.apk');
Route::post('uploader/token', [\App\Http\Controllers\Admin\UploaderController::class, 'uploadToken'])->name('uploader.token');

// 附件管理
Route::get('attachments/disks', [\App\Http\Controllers\Admin\AttachmentController::class, 'disks'])->name('attachments.disks');
Route::post('attachments/register', [\App\Http\Controllers\Admin\AttachmentController::class, 'register'])->name('attachments.register');
Route::delete('attachments', [\App\Http\Controllers\Admin\AttachmentController::class, 'batchDestroy'])->name('attachments.batch-destroy');
Route::get('attachments/{id}/download', [\App\Http\Controllers\Admin\AttachmentController::class, 'download'])->name('attachments.download');
Route::get('attachments/{id}/temporary-url', [\App\Http\Controllers\Admin\AttachmentController::class, 'temporaryUrl'])->name('attachments.temporary-url');
Route::put('attachments/{id}/rename', [\App\Http\Controllers\Admin\AttachmentController::class, 'rename'])->name('attachments.rename');
Route::put('attachments/{id}/move', [\App\Http\Controllers\Admin\AttachmentController::class, 'move'])->name('attachments.move');
Route::get('attachments', [\App\Http\Controllers\Admin\AttachmentController::class, 'index'])->name('attachments.index');
Route::get('attachments/{id}', [\App\Http\Controllers\Admin\AttachmentController::class, 'show'])->name('attachments.show');
Route::delete('attachments/{id}', [\App\Http\Controllers\Admin\AttachmentController::class, 'destroy'])->name('attachments.destroy');

// 性能监控（Laravel Pulse 数据）
Route::group(['prefix' => 'monitor'], function (Registrar $registrar) {
    $registrar->get('servers', [\App\Http\Controllers\Admin\MonitorController::class, 'servers'])->name('monitor.servers');
    $registrar->get('queues', [\App\Http\Controllers\Admin\MonitorController::class, 'queues'])->name('monitor.queues');
    $registrar->get('reverb/connections', [\App\Http\Controllers\Admin\MonitorController::class, 'reverbConnections'])->name('monitor.reverb.connections');
    $registrar->get('reverb/messages', [\App\Http\Controllers\Admin\MonitorController::class, 'reverbMessages'])->name('monitor.reverb.messages');
    $registrar->get('cache', [\App\Http\Controllers\Admin\MonitorController::class, 'cache'])->name('monitor.cache');
    $registrar->get('exceptions', [\App\Http\Controllers\Admin\MonitorController::class, 'exceptions'])->name('monitor.exceptions');
    $registrar->get('slow-queries', [\App\Http\Controllers\Admin\MonitorController::class, 'slowQueries'])->name('monitor.slow-queries');
    $registrar->get('slow-requests', [\App\Http\Controllers\Admin\MonitorController::class, 'slowRequests'])->name('monitor.slow-requests');
    $registrar->get('slow-jobs', [\App\Http\Controllers\Admin\MonitorController::class, 'slowJobs'])->name('monitor.slow-jobs');
    $registrar->get('slow-outgoing-requests', [\App\Http\Controllers\Admin\MonitorController::class, 'slowOutgoingRequests'])->name('monitor.slow-outgoing-requests');
    $registrar->get('usage', [\App\Http\Controllers\Admin\MonitorController::class, 'usage'])->name('monitor.usage');
});

// 调试面板（Laravel Telescope 数据）
Route::group(['prefix' => 'debug'], function (Registrar $registrar) {
    $registrar->get('tags', [\App\Http\Controllers\Admin\DebugController::class, 'tags'])->name('debug.tags');
    $registrar->post('tags', [\App\Http\Controllers\Admin\DebugController::class, 'monitor'])->name('debug.monitor');
    $registrar->delete('tags', [\App\Http\Controllers\Admin\DebugController::class, 'unmonitor'])->name('debug.unmonitor');
    $registrar->post('toggle-recording', [\App\Http\Controllers\Admin\DebugController::class, 'toggleRecording'])->name('debug.toggle-recording');
    $registrar->delete('entries', [\App\Http\Controllers\Admin\DebugController::class, 'destroy'])->name('debug.destroy');
    $registrar->get('entries', [\App\Http\Controllers\Admin\DebugController::class, 'index'])->name('debug.index');
    $registrar->get('entries/{id}', [\App\Http\Controllers\Admin\DebugController::class, 'show'])->name('debug.show');
    $registrar->put('entries/{id}/resolve', [\App\Http\Controllers\Admin\DebugController::class, 'resolve'])->name('debug.resolve');
});

// 调度任务日志（仅列表、详情，只读）
Route::get('schedule-logs', [\App\Http\Controllers\Admin\ScheduleLogController::class, 'index'])->name('schedule-logs.index');
Route::get('schedule-logs/{id}', [\App\Http\Controllers\Admin\ScheduleLogController::class, 'show'])->name('schedule-logs.show');

// 操作审计日志（仅列表、详情，只读）
Route::get('audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
Route::get('audit-logs/export', [\App\Http\Controllers\Admin\AuditLogController::class, 'export'])->name('audit-logs.export');
Route::get('audit-logs/{id}', [\App\Http\Controllers\Admin\AuditLogController::class, 'show'])->name('audit-logs.show');

// 全局登录历史（跨管理员与前台用户，只读）
Route::get('login-histories', [\App\Http\Controllers\Admin\LoginHistoryController::class, 'index'])->name('login-histories.index');

// 邀请记录（只读）
Route::get('invite-records', [\App\Http\Controllers\Admin\InviteRecordController::class, 'index'])->name('invite-records.index');
Route::get('invite-records/{inviteRecord}', [\App\Http\Controllers\Admin\InviteRecordController::class, 'show'])->name('invite-records.show');

// 队列管理（Laravel Horizon 数据代理）
Route::group(['prefix' => 'queue'], function (Registrar $registrar) {
    $registrar->get('stats', [\App\Http\Controllers\Admin\QueueController::class, 'stats'])->name('queue.stats');
    $registrar->get('workload', [\App\Http\Controllers\Admin\QueueController::class, 'workload'])->name('queue.workload');
    $registrar->get('masters', [\App\Http\Controllers\Admin\QueueController::class, 'masters'])->name('queue.masters');

    // 标签监控
    $registrar->get('monitoring/tags', [\App\Http\Controllers\Admin\QueueController::class, 'monitoringTags'])->name('queue.monitoring.tags');
    $registrar->get('monitoring/jobs', [\App\Http\Controllers\Admin\QueueController::class, 'monitoringJobs'])->name('queue.monitoring.jobs');
    $registrar->post('monitoring/tags', [\App\Http\Controllers\Admin\QueueController::class, 'monitorTag'])->name('queue.monitoring.tag');
    $registrar->delete('monitoring/tags/{tag}', [\App\Http\Controllers\Admin\QueueController::class, 'stopMonitoringTag'])->name('queue.monitoring.tag.destroy');

    // 指标
    $registrar->get('metrics/jobs', [\App\Http\Controllers\Admin\QueueController::class, 'jobMetrics'])->name('queue.metrics.jobs');
    $registrar->get('metrics/jobs/{id}', [\App\Http\Controllers\Admin\QueueController::class, 'jobMetricsDetail'])->name('queue.metrics.jobs.detail');
    $registrar->get('metrics/queues', [\App\Http\Controllers\Admin\QueueController::class, 'queueMetrics'])->name('queue.metrics.queues');
    $registrar->get('metrics/queues/{id}', [\App\Http\Controllers\Admin\QueueController::class, 'queueMetricsDetail'])->name('queue.metrics.queues.detail');

    // 批处理
    $registrar->get('batches', [\App\Http\Controllers\Admin\QueueController::class, 'batches'])->name('queue.batches');
    $registrar->get('batches/{id}', [\App\Http\Controllers\Admin\QueueController::class, 'batchDetail'])->name('queue.batches.detail');
    $registrar->post('batches/{id}/retry', [\App\Http\Controllers\Admin\QueueController::class, 'retryBatch'])->name('queue.batches.retry');

    // 任务列表
    $registrar->get('jobs/pending', [\App\Http\Controllers\Admin\QueueController::class, 'pendingJobs'])->name('queue.jobs.pending');
    $registrar->get('jobs/completed', [\App\Http\Controllers\Admin\QueueController::class, 'completedJobs'])->name('queue.jobs.completed');
    $registrar->get('jobs/silenced', [\App\Http\Controllers\Admin\QueueController::class, 'silencedJobs'])->name('queue.jobs.silenced');
    $registrar->post('jobs/failed/{id}/retry', [\App\Http\Controllers\Admin\QueueController::class, 'retryJob'])->name('queue.jobs.retry');
    $registrar->get('jobs/failed', [\App\Http\Controllers\Admin\QueueController::class, 'failedJobs'])->name('queue.jobs.failed');
    $registrar->get('jobs/failed/{id}', [\App\Http\Controllers\Admin\QueueController::class, 'failedJobDetail'])->name('queue.jobs.failed.detail');
    $registrar->get('jobs/{id}', [\App\Http\Controllers\Admin\QueueController::class, 'jobDetail'])->name('queue.jobs.detail');
});

// 字典管理
Route::get('dicts/by-code/{code}/items', [\App\Http\Controllers\Admin\DictController::class, 'itemsByCode'])->name('dicts.items-by-code');
Route::apiResource('dicts', \App\Http\Controllers\Admin\DictController::class);
Route::apiResource('dict-items', \App\Http\Controllers\Admin\DictItemController::class);

// AI 聊天
Route::get('chat/conversations', [\App\Http\Controllers\Admin\ChatController::class, 'conversations'])->name('chat.conversations');
Route::get('chat/conversations/{conversationId}', [\App\Http\Controllers\Admin\ChatController::class, 'conversation'])->name('chat.conversation');
Route::post('chat', [\App\Http\Controllers\Admin\ChatController::class, 'stream'])->name('chat.send');
Route::post('chat/approve', [\App\Http\Controllers\Admin\ChatController::class, 'approve'])->name('chat.approve');
Route::delete('chat/conversations/{conversationId}', [\App\Http\Controllers\Admin\ChatController::class, 'destroy'])->name('chat.destroy');

// 评论管理（运营管理）
Route::get('comments/{comment}/replies', [\App\Http\Controllers\Admin\CommentController::class, 'replies'])->name('comments.replies');
Route::post('comments/{comment}/review', [\App\Http\Controllers\Admin\CommentController::class, 'review'])->name('comments.review');
Route::get('comments/export', [\App\Http\Controllers\Admin\CommentController::class, 'export'])->name('comments.export');
Route::apiResource('comments', \App\Http\Controllers\Admin\CommentController::class)->except(['store', 'update']);

// 用户反馈
Route::get('feedbacks/stats', [\App\Http\Controllers\Admin\FeedbackController::class, 'stats'])->name('feedbacks.stats');
Route::get('feedbacks/export', [\App\Http\Controllers\Admin\FeedbackController::class, 'export'])->name('feedbacks.export');
Route::apiResource('feedbacks', \App\Http\Controllers\Admin\FeedbackController::class)->except(['store']);

// Banner / 轮播图管理（运营管理）
Route::put('banners/{banner}/toggle-status', [\App\Http\Controllers\Admin\BannerController::class, 'toggleStatus'])->name('banners.toggle-status');
Route::post('banners/sort', [\App\Http\Controllers\Admin\BannerController::class, 'sort'])->name('banners.sort');
Route::apiResource('banners', \App\Http\Controllers\Admin\BannerController::class);

// 公告管理（运营管理）
Route::put('announcements/{announcement}/toggle-status', [\App\Http\Controllers\Admin\AnnouncementController::class, 'toggleStatus'])->name('announcements.toggle-status');
Route::apiResource('announcements', \App\Http\Controllers\Admin\AnnouncementController::class);

// 任务分组管理（运营管理）
Route::get('task-groups/options', [\App\Http\Controllers\Admin\TaskGroupController::class, 'options'])->name('task-groups.options');
Route::put('task-groups/{taskGroup}/toggle-status', [\App\Http\Controllers\Admin\TaskGroupController::class, 'toggleStatus'])->name('task-groups.toggle-status');
Route::apiResource('task-groups', \App\Http\Controllers\Admin\TaskGroupController::class);

// 任务管理（运营管理）
Route::put('tasks/{task}/toggle-status', [\App\Http\Controllers\Admin\TaskController::class, 'toggleStatus'])->name('tasks.toggle-status');
Route::apiResource('tasks', \App\Http\Controllers\Admin\TaskController::class);

// 任务日志（运营管理）—— 只读
Route::apiResource('task-logs', \App\Http\Controllers\Admin\TaskLogController::class)
    ->only(['index', 'show']);

// 用户分群管理（用户管理）
Route::get('user-segments/options', [\App\Http\Controllers\Admin\UserSegmentController::class, 'options'])->name('user-segments.options');
Route::post('user-segments/batch-attach', [\App\Http\Controllers\Admin\UserSegmentController::class, 'batchAttach'])->name('user-segments.batch-attach');
Route::post('user-segments/batch-detach', [\App\Http\Controllers\Admin\UserSegmentController::class, 'batchDetach'])->name('user-segments.batch-detach');
Route::put('user-segments/{segment}/toggle-status', [\App\Http\Controllers\Admin\UserSegmentController::class, 'toggleStatus'])->name('user-segments.toggle-status');
Route::post('user-segments/{segment}/sync', [\App\Http\Controllers\Admin\UserSegmentController::class, 'sync'])->name('user-segments.sync');
Route::get('user-segments/{segment}/preview', [\App\Http\Controllers\Admin\UserSegmentController::class, 'preview'])->name('user-segments.preview');
Route::get('user-segments/{segment}/users', [\App\Http\Controllers\Admin\UserSegmentController::class, 'users'])->name('user-segments.users');
Route::post('user-segments/{segment}/attach', [\App\Http\Controllers\Admin\UserSegmentController::class, 'attach'])->name('user-segments.attach');
Route::post('user-segments/{segment}/detach', [\App\Http\Controllers\Admin\UserSegmentController::class, 'detach'])->name('user-segments.detach');
Route::apiResource('user-segments', \App\Http\Controllers\Admin\UserSegmentController::class)->except(['create', 'edit']);

// 分群异步任务查询（提交人可见自己的任务）
Route::get('segment-tasks', [\App\Http\Controllers\Admin\SegmentTaskController::class, 'index'])->name('segment-tasks.index');
Route::get('segment-tasks/{task}', [\App\Http\Controllers\Admin\SegmentTaskController::class, 'show'])->name('segment-tasks.show');

// 实名认证审核
Route::get('identity-verifications/stats', [\App\Http\Controllers\Admin\IdentityVerificationController::class, 'stats'])->name('identity-verifications.stats');
Route::get('identity-verifications', [\App\Http\Controllers\Admin\IdentityVerificationController::class, 'index'])->name('identity-verifications.index');
Route::get('identity-verifications/{verification}', [\App\Http\Controllers\Admin\IdentityVerificationController::class, 'show'])->name('identity-verifications.show');
Route::post('identity-verifications/{verification}/approve', [\App\Http\Controllers\Admin\IdentityVerificationController::class, 'approve'])->name('identity-verifications.approve');
Route::post('identity-verifications/{verification}/reject', [\App\Http\Controllers\Admin\IdentityVerificationController::class, 'reject'])->name('identity-verifications.reject');
