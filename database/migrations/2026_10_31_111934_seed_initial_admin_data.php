<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Enums\AdminStatus;
use App\Enums\MenuType;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\System\Permission;
use App\Models\System\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('admin_users') || ! Schema::hasTable('admin_menus')) {
            return;
        }

        // 1. 创建超级管理员角色
        $superRole = Role::create([
            'name' => 'super_admin',
            'display_name' => '超级管理员',
            'guard_name' => AdminMenu::GUARD_NAME,
        ]);

        // 2. 创建默认管理员账号
        $admin = Admin::query()->firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => '超级管理员',
                'email' => 'admin@example.com',
                'phone' => '13800000000',
                'password' => Hash::make('12345678'),
                'status' => AdminStatus::ACTIVE,
                'login_count' => 0,
            ]
        );

        $admin->assignRole($superRole);

        // 3. 初始化菜单与权限
        $this->seedMenus();

        // 4. 将所有权限赋给超级管理员角色
        $permissions = Permission::where('guard_name', AdminMenu::GUARD_NAME)->pluck('name');
        $superRole->syncPermissions($permissions);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('admin_users')) {
            return;
        }

        // 仅删除初始化创建的管理员账号，不删除角色和菜单
        Admin::query()->where('username', 'admin')->forceDelete();
    }

    /**
     * 初始化菜单数据
     */
    private function seedMenus(): void
    {
        $menus = $this->menus();

        foreach ($menus as $menu) {
            $this->createMenuRecursively($menu, null);
        }
    }

    /**
     * 递归创建菜单及其子菜单
     *
     * @param  array<string, mixed>  $menuData
     */
    private function createMenuRecursively(array $menuData, ?int $parentId): void
    {
        $children = $menuData['children'] ?? [];
        $buttons = $menuData['buttons'] ?? [];
        unset($menuData['children'], $menuData['buttons'], $menuData['key']);

        $menuData['parent_id'] = $parentId;

        /** @var AdminMenu $model */
        $model = AdminMenu::query()->create($menuData);

        foreach ($children as $child) {
            $this->createMenuRecursively($child, $model->id);
        }

        $this->createButtons($model->id, $buttons);
    }

    /**
     * 创建按钮（权限）子项
     *
     * @param  array<int, array<string, mixed>>  $buttons
     */
    private function createButtons(int $parentId, array $buttons): void
    {
        foreach ($buttons as $i => $btn) {
            AdminMenu::query()->create(array_merge([
                'parent_id' => $parentId,
                'type' => MenuType::BUTTON,
                'sort' => $i + 1,
                'is_enable' => true,
            ], $btn));
        }
    }

    /**
     * 菜单数据
     *
     * @return array<int, array<string, mixed>>
     */
    private function menus(): array
    {
        return [
            // ========== Dashboard ==========
            [
                'key' => 'dashboard',
                'path' => '/dashboard',
                'name' => 'Dashboard',
                'component' => '/index/index',
                'title' => '仪表盘',
                'icon' => 'ri:pie-chart-line',
                'type' => MenuType::DIRECTORY,
                'sort' => 1,
                'children' => [
                    [
                        'key' => 'dashboard.console',
                        'path' => 'console',
                        'name' => 'Console',
                        'component' => '/dashboard/console',
                        'title' => '工作台',
                        'icon' => 'ri:home-smile-2-line',
                        'type' => MenuType::MENU,
                        'sort' => 1,
                        'keep_alive' => false,
                        'fixed_tab' => true,
                    ],
                ],
            ],

            // ========== 系统管理 ==========
            [
                'key' => 'system',
                'path' => '/system',
                'name' => 'System',
                'component' => '/index/index',
                'title' => '系统管理',
                'icon' => 'ri:settings-3-line',
                'type' => MenuType::DIRECTORY,
                'sort' => 10,
                'children' => [
                    [
                        'key' => 'system.config',
                        'path' => 'config',
                        'name' => 'SystemConfig',
                        'component' => '/system/config/index',
                        'title' => '系统设置',
                        'icon' => 'ri:settings-4-line',
                        'type' => MenuType::MENU,
                        'sort' => 0,
                        'keep_alive' => true,
                        'permission' => 'settings.index',
                        'buttons' => [
                            ['title' => '保存系统设置', 'permission' => 'settings.edit'],
                        ],
                    ],
                    [
                        'key' => 'system.admin',
                        'path' => 'admin',
                        'name' => 'AdminUser',
                        'component' => '/system/admin/index',
                        'title' => '管理员管理',
                        'icon' => 'ri:admin-line',
                        'type' => MenuType::MENU,
                        'sort' => 1,
                        'keep_alive' => true,
                        'permission' => 'admins.index',
                        'buttons' => [
                            ['title' => '新增管理员', 'permission' => 'admins.create'],
                            ['title' => '编辑管理员', 'permission' => 'admins.edit'],
                            ['title' => '删除管理员', 'permission' => 'admins.delete'],
                            ['title' => '重置管理员密码', 'permission' => 'admins.edit'],
                            ['title' => '管理员分配角色', 'permission' => 'admins.edit'],
                        ],
                    ],
                    [
                        'key' => 'system.role',
                        'path' => 'role',
                        'name' => 'Role',
                        'component' => '/system/role/index',
                        'title' => '角色管理',
                        'icon' => 'ri:shield-user-line',
                        'type' => MenuType::MENU,
                        'sort' => 2,
                        'keep_alive' => true,
                        'permission' => 'roles.index',
                        'buttons' => [
                            ['title' => '新增角色', 'permission' => 'roles.create'],
                            ['title' => '编辑角色', 'permission' => 'roles.edit'],
                            ['title' => '删除角色', 'permission' => 'roles.delete'],
                            ['title' => '分配角色权限', 'permission' => 'roles.edit'],
                        ],
                    ],
                    [
                        'key' => 'system.user-center',
                        'path' => 'user-center',
                        'name' => 'UserCenter',
                        'component' => '/system/user-center/index',
                        'title' => '个人中心',
                        'icon' => 'ri:user-3-line',
                        'type' => MenuType::MENU,
                        'sort' => 6,
                        'is_hide' => true,
                        'is_hide_tab' => true,
                        'keep_alive' => true,
                    ],
                    [
                        'key' => 'system.menu',
                        'path' => 'menu',
                        'name' => 'Menu',
                        'component' => '/system/menu/index',
                        'title' => '菜单管理',
                        'icon' => 'ri:menu-line',
                        'type' => MenuType::MENU,
                        'sort' => 3,
                        'keep_alive' => true,
                        'permission' => 'menus.index',
                        'buttons' => [
                            ['title' => '新增菜单', 'permission' => 'menus.create'],
                            ['title' => '编辑菜单', 'permission' => 'menus.edit'],
                            ['title' => '删除菜单', 'permission' => 'menus.delete'],
                        ],
                    ],
                    [
                        'key' => 'system.setting',
                        'path' => 'setting',
                        'name' => 'Setting',
                        'component' => '/system/setting/index',
                        'title' => '设置项管理',
                        'icon' => 'ri:settings-2-line',
                        'type' => MenuType::MENU,
                        'sort' => 4,
                        'keep_alive' => true,
                        'permission' => 'settings.index',
                        'buttons' => [
                            ['title' => '新增配置', 'permission' => 'settings.create'],
                            ['title' => '编辑配置', 'permission' => 'settings.edit'],
                            ['title' => '删除配置', 'permission' => 'settings.delete'],
                        ],
                    ],
                    [
                        'key' => 'system.dict',
                        'path' => 'dict',
                        'name' => 'Dict',
                        'component' => '/system/dict/index',
                        'title' => '字典管理',
                        'icon' => 'ri:book-open-line',
                        'type' => MenuType::MENU,
                        'sort' => 5,
                        'keep_alive' => true,
                        'permission' => 'dicts.index',
                        'buttons' => [
                            ['title' => '新增字典', 'permission' => 'dicts.create'],
                            ['title' => '编辑字典', 'permission' => 'dicts.edit'],
                            ['title' => '删除字典', 'permission' => 'dicts.delete'],
                            ['title' => '字典数据管理', 'permission' => 'dict-items.index'],
                            ['title' => '新增字典数据', 'permission' => 'dict-items.create'],
                            ['title' => '编辑字典数据', 'permission' => 'dict-items.edit'],
                            ['title' => '删除字典数据', 'permission' => 'dict-items.delete'],
                        ],
                    ],
                    [
                        'key' => 'system.area',
                        'path' => 'area',
                        'name' => 'Area',
                        'component' => '/system/area/index',
                        'title' => '地区管理',
                        'icon' => 'ri:map-pin-line',
                        'type' => MenuType::MENU,
                        'sort' => 6,
                        'keep_alive' => true,
                        'permission' => 'areas.index',
                        'buttons' => [
                            ['title' => '新增地区', 'permission' => 'areas.create'],
                            ['title' => '编辑地区', 'permission' => 'areas.edit'],
                            ['title' => '删除地区', 'permission' => 'areas.delete'],
                        ],
                    ],
                    [
                        'key' => 'system.phone-code',
                        'path' => 'phone-code',
                        'name' => 'PhoneCode',
                        'component' => '/system/phone-code/index',
                        'title' => '短信验证码',
                        'icon' => 'ri:message-2-line',
                        'type' => MenuType::MENU,
                        'sort' => 7,
                        'keep_alive' => true,
                        'permission' => 'phone-codes.index',
                    ],
                    [
                        'key' => 'system.mail-code',
                        'path' => 'mail-code',
                        'name' => 'MailCode',
                        'component' => '/system/mail-code/index',
                        'title' => '邮件验证码',
                        'icon' => 'ri:mail-line',
                        'type' => MenuType::MENU,
                        'sort' => 8,
                        'keep_alive' => true,
                        'permission' => 'mail-codes.index',
                    ],
                    [
                        'key' => 'system.notification',
                        'path' => 'notification',
                        'name' => 'Notification',
                        'component' => '/system/notification/index',
                        'title' => '通知管理',
                        'icon' => 'ri:notification-3-line',
                        'type' => MenuType::MENU,
                        'sort' => 9,
                        'keep_alive' => true,
                    ],
                    [
                        'key' => 'system.attachment',
                        'path' => 'attachment',
                        'name' => 'Attachment',
                        'component' => '/system/attachment/index',
                        'title' => '附件管理',
                        'icon' => 'ri:folder-image-line',
                        'type' => MenuType::MENU,
                        'sort' => 10,
                        'keep_alive' => true,
                        'permission' => 'attachments.index',
                        'buttons' => [
                            ['title' => '登记附件', 'permission' => 'attachments.create'],
                            ['title' => '编辑附件', 'permission' => 'attachments.edit'],
                            ['title' => '删除附件', 'permission' => 'attachments.delete'],
                        ],
                    ],
                    [
                        'key' => 'system.app',
                        'path' => 'app',
                        'name' => 'AppRelease',
                        'component' => '/system/app/index',
                        'title' => '应用管理',
                        'icon' => 'ri:apps-2-line',
                        'type' => MenuType::MENU,
                        'sort' => 11,
                        'keep_alive' => true,
                        'permission' => 'apps.index',
                        'buttons' => [
                            ['title' => '新增应用', 'permission' => 'apps.create'],
                            ['title' => '编辑应用', 'permission' => 'apps.edit'],
                            ['title' => '删除应用', 'permission' => 'apps.delete'],
                            ['title' => '版本管理', 'permission' => 'app-versions.index'],
                        ],
                    ],
                    [ // 应用版本管理 隐藏在应用管理下
                        'key' => 'system.app-version',
                        'path' => 'app/:id/versions',
                        'name' => 'AppVersion',
                        'component' => '/system/app/version',
                        'title' => '版本管理',
                        'icon' => 'ri:git-branch-line',
                        'type' => MenuType::MENU,
                        'sort' => 12,
                        'keep_alive' => true,
                        'is_hide' => true,
                        'is_hide_tab' => true,
                        'buttons' => [
                            ['title' => '新增版本', 'permission' => 'app-versions.create'],
                            ['title' => '编辑版本', 'permission' => 'app-versions.edit'],
                            ['title' => '删除版本', 'permission' => 'app-versions.delete'],
                        ],
                    ],
                ],
            ],

            // ========== 用户管理 ==========
            [
                'key' => 'user',
                'path' => '/user',
                'name' => 'User',
                'component' => '/index/index',
                'title' => '用户管理',
                'icon' => 'ri:user-line',
                'type' => MenuType::DIRECTORY,
                'sort' => 20,
                'children' => [
                    [
                        'key' => 'user.list',
                        'path' => 'list',
                        'name' => 'UserList',
                        'component' => '/user/list/index',
                        'title' => '用户列表',
                        'icon' => 'ri:user-search-line',
                        'type' => MenuType::MENU,
                        'sort' => 1,
                        'keep_alive' => true,
                        'permission' => 'users.index',
                        'buttons' => [
                            ['title' => '编辑用户', 'permission' => 'users.edit'],
                            ['title' => '删除用户', 'permission' => 'users.delete'],
                            ['title' => '冻结/启用用户', 'permission' => 'users.edit'],
                            ['title' => '重置用户密码', 'permission' => 'users.edit'],
                            ['title' => '重置联系方式', 'permission' => 'users.edit'],
                            ['title' => '调整余额', 'permission' => 'users.edit'],
                            ['title' => '延长VIP', 'permission' => 'users.edit'],
                            ['title' => '导出用户', 'permission' => 'users.export'],
                        ],
                    ],
                    [
                        'key' => 'user.segment-group',
                        'path' => 'segment-group',
                        'name' => 'UserSegmentGroup',
                        'component' => '/index/index',
                        'title' => '用户分群',
                        'icon' => 'ri:group-line',
                        'type' => MenuType::DIRECTORY,
                        'sort' => 99,
                        'children' => [
                            [
                                'key' => 'user.segment',
                                'path' => 'segment',
                                'name' => 'UserSegment',
                                'component' => '/user/segment',
                                'title' => '分群管理',
                                'icon' => 'ri:price-tag-3-line',
                                'type' => MenuType::MENU,
                                'sort' => 1,
                                'keep_alive' => true,
                                'permission' => 'segments.index',
                                'buttons' => [
                                    ['title' => '新增分群', 'permission' => 'segments.create'],
                                    ['title' => '编辑分群', 'permission' => 'segments.edit'],
                                    ['title' => '删除分群', 'permission' => 'segments.delete'],
                                    ['title' => '打标/移标', 'permission' => 'segments.assign'],
                                ],
                            ],
                            [
                                'key' => 'user.segment.member',
                                'path' => 'member',
                                'name' => 'UserSegmentMember',
                                'component' => '/user/segment-member',
                                'title' => '分群成员',
                                'icon' => 'ri:team-line',
                                'type' => MenuType::MENU,
                                'sort' => 2,
                                'keep_alive' => true,
                                'permission' => 'segments.index',
                                'buttons' => [
                                    ['title' => '移出分群', 'permission' => 'segments.assign'],
                                ],
                            ],
                            [
                                'key' => 'user.segment.task',
                                'path' => 'task',
                                'name' => 'UserSegmentTask',
                                'component' => '/user/segment-task',
                                'title' => '分群任务',
                                'icon' => 'ri:time-line',
                                'type' => MenuType::MENU,
                                'sort' => 3,
                                'keep_alive' => false,
                                'permission' => 'segments.task',
                                'buttons' => [],
                            ],
                        ],
                    ],
                    [
                        'key' => 'user.identity',
                        'path' => 'identity',
                        'name' => 'UserIdentity',
                        'component' => '/user/identity',
                        'title' => '实名认证',
                        'icon' => 'ri:shield-user-line',
                        'type' => MenuType::MENU,
                        'sort' => 3,
                        'keep_alive' => true,
                        'permission' => 'identity-verifications.index',
                        'buttons' => [
                            ['title' => '审核通过', 'permission' => 'identity-verifications.edit'],
                        ],
                    ],
                    [
                        'key' => 'user.invite-record',
                        'path' => 'invite-record',
                        'name' => 'UserInviteRecord',
                        'component' => '/user/invite-record/index',
                        'title' => '邀请记录',
                        'icon' => 'ri:user-shared-line',
                        'type' => MenuType::MENU,
                        'sort' => 4,
                        'keep_alive' => true,
                        'permission' => 'invite-records.index',
                        'buttons' => [
                            ['title' => '查看详情', 'permission' => 'invite-records.show'],
                        ],
                    ],
                    [
                        'key' => 'user.open-account',
                        'path' => 'open-account',
                        'name' => 'OpenAccount',
                        'component' => '/user/open-account/index',
                        'title' => '合作方账户',
                        'icon' => 'ri:id-card-line',
                        'type' => MenuType::MENU,
                        'sort' => 5,
                        'keep_alive' => true,
                        'permission' => 'open_accounts.index',
                        'buttons' => [
                            ['title' => '新增账户', 'permission' => 'open_accounts.create'],
                            ['title' => '编辑账户', 'permission' => 'open_accounts.edit'],
                            ['title' => '删除账户', 'permission' => 'open_accounts.delete'],
                            ['title' => '冻结/启用', 'permission' => 'open_accounts.edit'],
                            ['title' => '轮换令牌', 'permission' => 'open_accounts.edit'],
                        ],
                    ],
                    [
                        'key' => 'user.trash',
                        'path' => 'trash',
                        'name' => 'UserTrash',
                        'component' => '/user/trash/index',
                        'title' => '用户回收站',
                        'icon' => 'ri:delete-bin-line',
                        'type' => MenuType::MENU,
                        'sort' => 102,
                        'keep_alive' => true,
                        'permission' => 'users.index',
                        'buttons' => [
                            ['title' => '恢复用户', 'permission' => 'users.edit'],
                            ['title' => '彻底删除用户', 'permission' => 'users.delete'],
                        ],
                    ],
                ],
            ],

            // ========== 内容管理 ==========
            [
                'key' => 'content',
                'path' => '/content',
                'name' => 'Content',
                'component' => '/index/index',
                'title' => '内容管理',
                'icon' => 'ri:article-line',
                'type' => MenuType::DIRECTORY,
                'sort' => 30,
                'children' => [],
            ],

            // ========== 运营管理 ==========
            [
                'key' => 'operation',
                'path' => '/operation',
                'name' => 'Operation',
                'component' => '/index/index',
                'title' => '运营管理',
                'icon' => 'ri:bar-chart-box-line',
                'type' => MenuType::DIRECTORY,
                'sort' => 40,
                'children' => [
                    [
                        'key' => 'operation.comment',
                        'path' => 'comment',
                        'name' => 'Comment',
                        'component' => '/operation/comment',
                        'title' => '评论管理',
                        'icon' => 'ri:chat-3-line',
                        'type' => MenuType::MENU,
                        'sort' => 1,
                        'keep_alive' => true,
                        'permission' => 'comments.index',
                        'buttons' => [
                            ['title' => '审核评论', 'permission' => 'comments.edit'],
                            ['title' => '删除评论', 'permission' => 'comments.delete'],
                        ],
                    ],
                    [
                        'key' => 'operation.feedback',
                        'path' => 'feedback',
                        'name' => 'Feedback',
                        'component' => '/operation/feedback',
                        'title' => '反馈管理',
                        'icon' => 'ri:feedback-line',
                        'type' => MenuType::MENU,
                        'sort' => 2,
                        'keep_alive' => true,
                        'permission' => 'feedbacks.index',
                        'buttons' => [
                            ['title' => '处理反馈', 'permission' => 'feedbacks.edit'],
                            ['title' => '删除反馈', 'permission' => 'feedbacks.delete'],
                            ['title' => '导出反馈', 'permission' => 'feedbacks.export'],
                        ],
                    ],
                    [
                        'key' => 'operation.banner',
                        'path' => 'banner',
                        'name' => 'Banner',
                        'component' => '/operation/banner',
                        'title' => '轮播图管理',
                        'icon' => 'ri:image-2-line',
                        'type' => MenuType::MENU,
                        'sort' => 3,
                        'keep_alive' => true,
                        'permission' => 'banners.index',
                        'buttons' => [
                            ['title' => '新增轮播图', 'permission' => 'banners.create'],
                            ['title' => '编辑轮播图', 'permission' => 'banners.edit'],
                            ['title' => '删除轮播图', 'permission' => 'banners.delete'],
                        ],
                    ],
                    [
                        'key' => 'operation.announcement',
                        'path' => 'announcement',
                        'name' => 'Announcement',
                        'component' => '/operation/announcement',
                        'title' => '公告管理',
                        'icon' => 'ri:notification-3-line',
                        'type' => MenuType::MENU,
                        'sort' => 4,
                        'keep_alive' => true,
                        'permission' => 'announcements.index',
                        'buttons' => [
                            ['title' => '新增公告', 'permission' => 'announcements.create'],
                            ['title' => '编辑公告', 'permission' => 'announcements.edit'],
                            ['title' => '删除公告', 'permission' => 'announcements.delete'],
                        ],
                    ],
                    [
                        'key' => 'operation.agreement',
                        'path' => 'agreement',
                        'name' => 'Agreement',
                        'component' => '/operation/agreement/index',
                        'title' => '协议管理',
                        'icon' => 'ri:file-text-line',
                        'type' => MenuType::MENU,
                        'sort' => 4,
                        'keep_alive' => true,
                        'permission' => 'agreements.index',
                        'buttons' => [
                            ['title' => '新增协议', 'permission' => 'agreements.create'],
                            ['title' => '编辑协议', 'permission' => 'agreements.edit'],
                            ['title' => '删除协议', 'permission' => 'agreements.delete'],
                        ],
                    ],
                    [
                        'key' => 'operation.sensitive-word',
                        'path' => 'sensitive-word',
                        'name' => 'SensitiveWord',
                        'component' => '/operation/sensitive-word',
                        'title' => '敏感词管理',
                        'icon' => 'ri:shield-keyhole-line',
                        'type' => MenuType::MENU,
                        'sort' => 5,
                        'keep_alive' => true,
                        'permission' => 'sensitive-words.index',
                        'buttons' => [
                            ['title' => '新增敏感词', 'permission' => 'sensitive-words.create'],
                            ['title' => '编辑敏感词', 'permission' => 'sensitive-words.edit'],
                            ['title' => '删除敏感词', 'permission' => 'sensitive-words.delete'],
                            ['title' => '批量导入', 'permission' => 'sensitive-words.create'],
                            ['title' => '刷新字典', 'permission' => 'sensitive-words.edit'],
                        ],
                    ],
                    [
                        'key' => 'operation.report',
                        'path' => 'report',
                        'name' => 'ReportManage',
                        'component' => '/operation/report',
                        'title' => '举报管理',
                        'icon' => 'ri:flag-2-line',
                        'type' => MenuType::MENU,
                        'sort' => 5,
                        'keep_alive' => true,
                        'permission' => 'reports.index',
                        'buttons' => [
                            ['title' => '处理举报', 'permission' => 'reports.edit'],
                            ['title' => '导出举报', 'permission' => 'reports.export'],
                        ],
                    ],
                    [
                        'key' => 'operation.task',
                        'path' => 'task',
                        'name' => 'TaskManage',
                        'component' => '',
                        'title' => '任务管理',
                        'icon' => 'ri:task-line',
                        'type' => MenuType::DIRECTORY,
                        'sort' => 6,
                        'children' => [
                            [
                                'key' => 'operation.task.group',
                                'path' => 'group',
                                'name' => 'TaskGroup',
                                'component' => '/operation/task-group',
                                'title' => '任务分组',
                                'icon' => 'ri:folder-3-line',
                                'type' => MenuType::MENU,
                                'sort' => 1,
                                'keep_alive' => true,
                                'permission' => 'task-groups.index',
                                'buttons' => [
                                    ['title' => '新增分组', 'permission' => 'task-groups.create'],
                                    ['title' => '编辑分组', 'permission' => 'task-groups.edit'],
                                    ['title' => '删除分组', 'permission' => 'task-groups.delete'],
                                ],
                            ],
                            [
                                'key' => 'operation.task.list',
                                'path' => 'list',
                                'name' => 'TaskList',
                                'component' => '/operation/task',
                                'title' => '任务列表',
                                'icon' => 'ri:list-check-2',
                                'type' => MenuType::MENU,
                                'sort' => 2,
                                'keep_alive' => true,
                                'permission' => 'tasks.index',
                                'buttons' => [
                                    ['title' => '新增任务', 'permission' => 'tasks.create'],
                                    ['title' => '编辑任务', 'permission' => 'tasks.edit'],
                                    ['title' => '删除任务', 'permission' => 'tasks.delete'],
                                ],
                            ],
                            [
                                'key' => 'operation.task.log',
                                'path' => 'log',
                                'name' => 'TaskLog',
                                'component' => '/operation/task-log',
                                'title' => '任务记录',
                                'icon' => 'ri:file-list-3-line',
                                'type' => MenuType::MENU,
                                'sort' => 3,
                                'keep_alive' => true,
                                'permission' => 'task-logs.index',
                                'buttons' => [],
                            ],
                        ],
                    ],
                    [
                        'key' => 'operation.wallet',
                        'path' => 'wallet',
                        'name' => 'Wallet',
                        'component' => '/operation/wallet',
                        'title' => '钱包流水',
                        'icon' => 'ri:wallet-3-line',
                        'type' => MenuType::MENU,
                        'sort' => 6,
                        'keep_alive' => true,
                        'permission' => 'wallet.index',
                        'buttons' => [
                            ['title' => '调账', 'permission' => 'wallet.adjust'],
                            ['title' => '导出流水', 'permission' => 'wallet.export'],
                        ],
                    ],
                    [
                        'key' => 'operation.withdraw',
                        'path' => 'withdraw',
                        'name' => 'Withdraw',
                        'component' => '/operation/withdraw',
                        'title' => '提现审核',
                        'icon' => 'ri:money-dollar-circle-line',
                        'type' => MenuType::MENU,
                        'sort' => 7,
                        'keep_alive' => true,
                        'permission' => 'wallet.withdraws',
                        'buttons' => [
                            ['title' => '审核通过', 'permission' => 'wallet.withdraws'],
                            ['title' => '审核驳回', 'permission' => 'wallet.withdraws'],
                            ['title' => '标记打款', 'permission' => 'wallet.withdraws'],
                            ['title' => '标记失败', 'permission' => 'wallet.withdraws'],
                            ['title' => '导出提现申请', 'permission' => 'wallet.export'],
                        ],
                    ],
                    [
                        'key' => 'operation.recharge',
                        'path' => 'recharge',
                        'name' => 'Recharge',
                        'component' => '/operation/recharge',
                        'title' => '充值订单',
                        'icon' => 'ri:bank-card-line',
                        'type' => MenuType::MENU,
                        'sort' => 8,
                        'keep_alive' => true,
                        'permission' => 'wallet.orders',
                        'buttons' => [
                            ['title' => '确认到账', 'permission' => 'wallet.orders'],
                            ['title' => '导出充值订单', 'permission' => 'wallet.export'],
                        ],
                    ],
                ],
            ],

            // ========== 监控管理 ==========
            [
                'key' => 'monitor',
                'path' => '/monitor',
                'name' => 'Monitor',
                'component' => '/index/index',
                'title' => '系统监控',
                'icon' => 'ri:computer-line',
                'type' => MenuType::DIRECTORY,
                'sort' => 50,
                'children' => [
                    [
                        'key' => 'monitor.horizon',
                        'path' => 'horizon',
                        'name' => 'Horizon',
                        'component' => '/monitor/horizon/index',
                        'title' => '队列监控',
                        'icon' => 'ri:flow-chart',
                        'type' => MenuType::MENU,
                        'sort' => 0,
                        'keep_alive' => true,
                        'permission' => 'horizon.index',
                        'buttons' => [
                            ['title' => '重试失败任务', 'permission' => 'horizon.index'],
                            ['title' => '重试批次', 'permission' => 'horizon.index'],
                            ['title' => '管理标签监控', 'permission' => 'horizon.index'],
                        ],
                    ],
                    [
                        'key' => 'monitor.pulse',
                        'path' => 'pulse',
                        'name' => 'Pulse',
                        'component' => '/monitor/pulse/index',
                        'title' => '性能监控',
                        'icon' => 'ri:pulse-line',
                        'type' => MenuType::MENU,
                        'sort' => 1,
                        'keep_alive' => false,
                        'permission' => 'pulse.index',
                    ],
                    [
                        'key' => 'monitor.debug',
                        'path' => 'debug',
                        'name' => 'Debug',
                        'component' => '/monitor/debug/index',
                        'title' => '调试面板',
                        'icon' => 'ri:bug-line',
                        'type' => MenuType::MENU,
                        'sort' => 2,
                        'keep_alive' => false,
                        'permission' => 'debug.index',
                        'buttons' => [
                            ['title' => '管理调试记录', 'permission' => 'debug.manage'],
                        ],
                    ],
                    [
                        'key' => 'monitor.schedule-log',
                        'path' => 'schedule-log',
                        'name' => 'ScheduleLog',
                        'component' => '/monitor/schedule-log/index',
                        'title' => '调度日志',
                        'icon' => 'ri:time-line',
                        'type' => MenuType::MENU,
                        'sort' => 3,
                        'keep_alive' => true,
                        'permission' => 'schedule-logs.index',
                    ],
                    [
                        'key' => 'monitor.audit-log',
                        'path' => 'audit-log',
                        'name' => 'AuditLog',
                        'component' => '/monitor/audit-log/index',
                        'title' => '操作日志',
                        'icon' => 'ri:file-list-3-line',
                        'type' => MenuType::MENU,
                        'sort' => 4,
                        'keep_alive' => true,
                        'permission' => 'audit-logs.index',
                    ],
                    [
                        'key' => 'monitor.login-history',
                        'path' => 'login-history',
                        'name' => 'LoginHistory',
                        'component' => '/monitor/login-history/index',
                        'title' => '登录日志',
                        'icon' => 'ri:login-box-line',
                        'type' => MenuType::MENU,
                        'sort' => 5,
                        'keep_alive' => true,
                        'permission' => 'login-histories.index',
                    ],
                ],
            ],

            // ========== AI 助手 ==========
            [
                'key' => 'ai',
                'path' => '/ai',
                'name' => 'AI',
                'component' => '/index/index',
                'title' => 'AI 助手',
                'icon' => 'ri:robot-line',
                'type' => MenuType::DIRECTORY,
                'sort' => 60,
                'children' => [
                    [
                        'key' => 'ai.chat',
                        'path' => 'chat',
                        'name' => 'AIChat',
                        'component' => '/ai/chat/index',
                        'title' => 'AI 聊天',
                        'icon' => 'ri:chat-3-line',
                        'type' => MenuType::MENU,
                        'sort' => 1,
                        'keep_alive' => false,
                    ],
                ],
            ],

            // ========== 外部页面示例 ==========
            [
                'key' => 'outside',
                'path' => '/outside',
                'name' => 'Outside',
                'component' => '/index/index',
                'title' => '外部页面',
                'icon' => 'ri:links-line',
                'type' => MenuType::DIRECTORY,
                'sort' => 70,
                'children' => [
                    [
                        'key' => 'outside.iframe',
                        'path' => '/outside/iframe/element',
                        'name' => 'ElementUI',
                        'component' => '',
                        'title' => '内嵌文档',
                        'icon' => 'ri:apps-2-line',
                        'type' => MenuType::IFRAME,
                        'link' => 'https://element-plus.org/zh-CN/component/overview.html',
                        'is_iframe' => true,
                        'sort' => 1,
                    ],
                    [
                        'key' => 'outside.docs',
                        'path' => 'https://laravel.com/docs',
                        'name' => 'LaravelDocs',
                        'component' => '',
                        'title' => 'Laravel 文档',
                        'icon' => 'ri:book-2-line',
                        'type' => MenuType::LINK,
                        'link' => 'https://laravel.com/docs',
                        'sort' => 2,
                    ],
                ],
            ],
        ];
    }
};
