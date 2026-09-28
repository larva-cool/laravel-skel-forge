# 后台菜单与权限变更规范

## 核心原则

如果功能需要增加或修改后台菜单、权限（角色权限、菜单项、按钮权限等），**直接编辑 `database/migrations/2026_07_31_111934_seed_initial_admin_data.php` 迁移文件来处理**，不用新建迁移文件。

## 变更流程

1. 变更前先通读该迁移文件，理解现有的菜单/权限组织方式（如父子菜单结构、`MenuType` 枚举、`Permission` 模型等），保持新增内容的命名、层级、风格与现有保持一致
2. 编辑后需在本地执行 `php artisan migrate:fresh`（或回滚后重新迁移）以验证迁移文件可正确执行
3. 涉及菜单的 path、component、permission key 等需要前后端联动的字段时，同步检查 `laravel-skel-admin` 前端项目中的对应实现
