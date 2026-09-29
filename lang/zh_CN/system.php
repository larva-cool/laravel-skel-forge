<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | 系统语言包
    |--------------------------------------------------------------------------
    |
    | 以下语言行用于系统级别的通用提示消息。
    |
    */

    'successful_operation' => '操作成功',
    'server_busy' => '服务器繁忙，请稍后再试',
    'wechat_fallback_busy' => '[Doge]系统繁忙，请稍后再试...',
    'cache_clear_success' => '缓存清理成功',
    'upload_failed' => '上传失败，请重试',

    // 反馈
    'feedback_submit_success' => '反馈提交成功，感谢您的反馈！',

    // 点赞/收藏
    'like_duplicate' => '已经点赞过该资源',
    'collection_duplicate' => '已经收藏过该资源',

    // App 版本
    'version_published_cannot_delete' => '已发布的版本不可删除，请先撤回或废弃',
    'version_only_published_withdraw' => '仅已发布状态的版本可以撤回',
    'version_already_obsolete' => '版本已处于废弃状态',

    // 用户分群
    'segment_task_submitted' => '任务已提交，完成后将通过站内信通知。',
    'segment_batch_attach_task_submitted' => '笛卡尔批量任务已提交，完成后将通过站内信通知。',
    'segment_batch_detach_task_submitted' => '笛卡尔批量解标任务已提交，完成后将通过站内信通知。',

    // 实名认证
    'identity_pending_exists' => '当前已有待审核的认证记录，请勿重复提交',
    'identity_submit_success' => '认证申请已提交，请耐心等待审核',
    'identity_approve_success' => '审核已通过',
    'identity_reject_success' => '已驳回',
    'identity_already_reviewed' => '该记录已审核完成，无法再次审核',
    'identity_verified' => '已通过实名认证',
    'identity_not_verified' => '未通过实名认证',
    'identity_unverified' => '已撤销实名认证',
];
