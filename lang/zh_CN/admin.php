<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | 后台管理语言包
    |--------------------------------------------------------------------------
    |
    | 以下语言行用于后台管理模块的各种提示消息。
    |
    */

    // 认证
    'login_success' => '登录成功',
    'logout_success' => '退出成功',
    'blocked' => '该账号已被禁用',

    // 管理员管理
    'admin_create_success' => '管理员创建成功',
    'admin_update_success' => '管理员更新成功',
    'admin_delete_success' => '管理员删除成功',
    'admin_cannot_delete_self' => '不能删除自己',
    'admin_cannot_delete_super' => '超级管理员不可删除',
    'admin_cannot_disable_self' => '不能禁用自己',
    'admin_assign_roles_success' => '角色分配成功',
    'admin_toggle_status_success' => '状态切换成功',
    'admin_reset_password_success' => '密码重置成功',

    // 角色管理
    'role_create_success' => '角色创建成功',
    'role_update_success' => '角色更新成功',
    'role_delete_success' => '角色删除成功',
    'role_assign_permissions_success' => '权限分配成功',
    'role_cannot_modify_super' => '超级管理员角色不可修改',
    'role_cannot_delete_super' => '超级管理员角色不可删除',
    'role_in_use' => '该角色已被管理员使用，无法删除',

    // 菜单管理
    'menu_create_success' => '菜单创建成功',
    'menu_update_success' => '菜单更新成功',
    'menu_delete_success' => '菜单删除成功',
    'menu_has_children' => '存在子菜单，无法删除',
    'menu_invalid_parent' => '父级菜单不能是自身或其下级菜单',

    // 地区管理
    'area_invalid_parent' => '父级地区不能是自身或其下级地区',
    'area_has_children' => '存在下级地区，无法删除',

    // 通知管理
    'notification_mark_all_read_success' => '全部标记为已读',
    'notification_mark_read_success' => '标记为已读成功',

    // 系统设置
    'setting_save_success' => '设置保存成功',
    'setting_save_empty' => '没有可保存的配置项，请检查提交的数据',

    // 附件管理
    'attachment_delete_success' => '附件删除成功',
    'attachment_batch_delete_success' => '批量删除成功',
    'attachment_rename_success' => '附件重命名成功',
    'attachment_move_success' => '附件移动成功',
    'attachment_move_failed' => '附件移动失败',
    'attachment_register_success' => '附件登记成功',
    'attachment_file_missing' => '物理文件不存在',
    'attachment_target_exists' => '目标路径已存在同名文件',
    'attachment_invalid_target_path' => '目标路径不合法',
    'attachment_temporary_url_unsupported' => '当前存储驱动不支持临时访问地址',

    // 调试面板（Telescope）
    'debug_entry_not_found' => '调试记录不存在',
    'debug_resolve_success' => '异常已标记为已解决',
    'debug_monitor_success' => '标签监控已开启',
    'debug_unmonitor_success' => '标签监控已关闭',
    'debug_recording_paused' => '已暂停记录',
    'debug_recording_resumed' => '已恢复记录',
    'debug_clear_success' => '调试记录已清空',
];
