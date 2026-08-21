<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | 是否启用操作审计
    |--------------------------------------------------------------------------
    */
    'enabled' => env('AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | 需要记录的请求方法
    |--------------------------------------------------------------------------
    |
    | 仅记录写操作，GET/HEAD/OPTIONS 等读操作不产生审计记录。
    |
    */
    'methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],

    /*
    |--------------------------------------------------------------------------
    | 排除的路由名称
    |--------------------------------------------------------------------------
    |
    | 支持 * 通配符，匹配 Str::is()。高频且无审计价值的接口应在此排除。
    |
    */
    'except' => [
        'admin.login',
        'admin.logout',
        'admin.notifications.*',
        'admin.chat.*',
        'admin.debug.*',
        'admin.queue.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | 敏感字段
    |--------------------------------------------------------------------------
    |
    | 命中的请求参数值将被替换为固定掩码，避免明文入库。
    |
    */
    'sensitive_keys' => [
        'password',
        'password_confirmation',
        'old_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'captcha',
        'code',
    ],

    /*
    |--------------------------------------------------------------------------
    | 请求参数最大长度
    |--------------------------------------------------------------------------
    |
    | payload 序列化后超过该长度将被截断，防止大字段撑爆存储。
    |
    */
    'max_payload_length' => 5000,

    /*
    |--------------------------------------------------------------------------
    | 路由操作描述
    |--------------------------------------------------------------------------
    |
    | 键为路由名称（支持 * 通配符），值为审计列表展示的中文描述。
    | 未匹配时回退为「请求方法 + 路径」。
    |
    */
    'titles' => [
        'admin.users.update' => '编辑用户',
        'admin.users.destroy' => '删除用户',
        'admin.users.toggle-status' => '冻结/启用用户',
        'admin.users.reset-password' => '重置用户密码',
        'admin.users.reset-contact' => '重置用户联系方式',
        'admin.users.adjust-balance' => '调整用户余额',
        'admin.users.extend-vip' => '延长用户VIP',

        'admin.admins.store' => '新增管理员',
        'admin.admins.update' => '编辑管理员',
        'admin.admins.destroy' => '删除管理员',
        'admin.admins.update-profile' => '修改个人资料',
        'admin.admins.update-avatar' => '修改头像',
        'admin.admins.assign-roles' => '分配管理员角色',
        'admin.admins.toggle-status' => '冻结/启用管理员',
        'admin.admins.reset-password' => '重置管理员密码',
        'admin.admins.change-password' => '修改自己的密码',

        'admin.roles.store' => '新增角色',
        'admin.roles.update' => '编辑角色',
        'admin.roles.destroy' => '删除角色',
        'admin.roles.assign-permissions' => '分配角色权限',

        'admin.menus.store' => '新增菜单',
        'admin.menus.update' => '编辑菜单',
        'admin.menus.destroy' => '删除菜单',

        'admin.settings.store' => '新增配置',
        'admin.settings.update' => '编辑配置',
        'admin.settings.destroy' => '删除配置',
        'admin.settings.batch' => '保存系统设置',

        'admin.areas.store' => '新增地区',
        'admin.areas.update' => '编辑地区',
        'admin.areas.destroy' => '删除地区',

        'admin.uploader.*' => '上传文件',
        'admin.attachments.register' => '登记附件',
        'admin.attachments.rename' => '重命名附件',
        'admin.attachments.move' => '移动附件',
        'admin.attachments.destroy' => '删除附件',
        'admin.attachments.batch-destroy' => '批量删除附件',
    ],
];
