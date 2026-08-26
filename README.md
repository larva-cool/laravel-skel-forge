# Laravel Skel Forge

基于 Laravel 13 的后台管理系统骨架，采用 DDD 架构并遵循 RESTful API 设计规范。

- 前端项目：[laravel-skel-admin](https://codeup.aliyun.com/xutongle/Skel/laravel-skel-admin)
- 产品文档：见 [PRD.md](PRD.md)

## 技术栈

| 分层 | 技术 |
|------|------|
| PHP 版本 | ^8.4 |
| 框架 | Laravel 13.x |
| 认证 | Laravel Sanctum 4.x |
| 权限 | spatie/laravel-permission 8.x |
| 队列 | Laravel Horizon 5.x |
| 实时通信 | Laravel Reverb 1.x / Echo 2.x |
| 应用服务器 | Laravel Octane 2.x |
| 监控 | Laravel Pulse 1.x / Telescope 5.x |
| AI | laravel/ai 0.10.x |
| 缓存/队列 | Redis |
| 前端构建 | Tailwind CSS v4 + Vite 8.x |
| 测试 | PHPUnit 12.x |
| 代码风格 | Laravel Pint 1.x |

## 环境要求

- PHP >= 8.4（pdo、mbstring、openssl、redis、bcmath、fileinfo、tokenizer、xml）
- Composer >= 2.x，Node.js >= 20.x
- MySQL 8.0+ / PostgreSQL / SQLite
- Redis（推荐）

## 安装

```bash
composer run setup
```

该命令会依次执行：安装依赖 → 复制 `.env` → 生成密钥 → 迁移 → 存储链接 → 安装并编译前端资源。

手动安装方式：

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm install && npm run build
```

## 本地开发

推荐使用 [Laravel Herd](https://herd.laravel.com/) 提供本地服务，访问地址为 `http://laravel-skel-forge.test`。

启动完整开发环境（server、queue、日志、Vite）：

```bash
composer run dev
```

默认管理员账号：`admin` / `12345678`（由迁移自动初始化）。

## 常用命令

| 命令 | 说明 |
|------|------|
| `composer run dev` | 启动开发环境 |
| `composer run test` | 运行全部测试 |
| `composer run fix-style` | 修复代码风格 |
| `php artisan route:list --path=admin` | 查看后台路由 |

## 目录结构

```
app/
├── Ai/              # AI Agents & Tools
├── Console/         # Artisan 命令
├── Enums/           # 枚举类
├── Events/          # 事件
├── Http/
│   ├── Controllers/ # Admin/ 后台、Api/V1/ 前台
│   ├── Middleware/  # 中间件（含审计日志）
│   ├── Requests/    # 表单请求验证
│   └── Resources/   # Eloquent API Resources
├── Jobs/            # 队列任务
├── Mail/            # 邮件
├── Models/          # Admin/、App/、Coin/、Content/、Point/、System/、User/
├── Policies/        # 授权策略
├── Providers/       # 服务提供者
├── Services/        # 业务服务
└── Support/         # 辅助类
```

## 路由

| 文件 | 前缀 | 说明 |
|------|------|------|
| `web.php` | - | Web 路由 |
| `admin.php` | `/admin` | 后台管理 API（Sanctum Token 认证） |
| `api_v1.php` | `/api/v1` | 前台 API v1 |
| `channels.php` | - | 广播频道 |
| `console.php` | - | 定时任务 |

## 编码规范

- 遵循 PSR-12，使用 PHP 8.4 特性（构造器属性提升、枚举、match 表达式等）
- 所有 PHP 文件强制 `declare(strict_types=1)` 和文件头 License 注释
- 修改 PHP 文件后必须运行 `vendor/bin/pint --dirty --format agent`
- API 统一使用 Eloquent API Resources，后台 RESTful 响应无外层 `data` 包装
- 数据库操作始终通过迁移
- 控制器建议最长 200 行，方法建议最长 20 行

## 测试

```bash
php artisan test --compact                # 全部测试
php artisan test --compact tests/Feature/ # 单文件
php artisan test --compact --filter=name  # 按名称过滤
```

使用 PHPUnit 12 属性语法（`#[Test]`、`#[CoversClass]`、`#[Group]`、`#[TestDox]`），测试方法使用 `snake_case`。

## 监控面板

| 工具 | 路径 |
|------|------|
| Pulse（性能） | `/pulse` |
| Telescope（调试，仅 local） | `/telescope` |
| Horizon（队列） | `/horizon` |

## 主要功能模块

### 后台管理（`/admin`）

| 模块 | 路由前缀 | 说明 |
|------|----------|------|
| 认证授权 | `/admin/auth` | 管理员登录、登出、当前用户信息、刷新 Token |
| 管理员 | `/admin/admins` | 管理员 CRUD、状态切换、密码重置、角色分配、登录历史 |
| 角色权限 | `/admin/roles`、`/admin/permissions` | RBAC 角色与权限管理 |
| 菜单 | `/admin/menus` | 树形菜单管理（目录/菜单/按钮） |
| 配置 | `/admin/settings`、`/admin/config` | 系统配置项与按分组查看 |
| 地区 | `/admin/areas` | 省市区地区数据（树形） |
| 字典 | `/admin/dicts`、`/admin/dict-items` | 字典类型与字典项管理 |
| 附件 | `/admin/attachments`、`/admin/uploader` | 附件列表、上传（中转/直传）、重命名、移动、删除 |
| 验证码 | `/admin/phone-codes`、`/admin/mail-codes` | 短信/邮件验证码发送记录（只读） |
| 通知 | `/admin/notifications` | 站内通知列表、未读数、标记已读、批量已读、清理已读 |
| 应用版本 | `/admin/apps`、`/admin/apps/{app}/versions` | 客户端应用与版本发布/撤回/废弃 |
| 前台用户 | `/admin/users` | 前台用户 CRUD、状态、积分/金币调整、VIP 延期、登录历史、社交账号、流水、回收站 |
| 反馈管理 | `/admin/feedbacks` | 用户反馈列表、统计、详情、处理、删除 |
| 评论管理 | `/admin/comments` | 评论列表（按审核状态/源类型/关键词/用户过滤）、详情（含 IP 与上级评论摘要）、回复链分页查询、审核（通过/驳回/审核中）、删除 |
| 公共接口 | `/admin/common` | 与业务无关的通用选项（如 `source-types` 多态源类型下拉） |
| 监控 | `/admin/monitor`、`/admin/queue`、`/admin/debug` | Pulse 性能、Horizon 队列、Telescope 调试（仅 local） |
| 审计 | `/admin/audit-logs`、`/admin/schedule-logs` | 操作审计与调度日志 |
| 仪表盘 | `/admin/dashboard` | 核心指标统计与趋势图 |
| AI 聊天 | `/admin/chat` | SSE 流式对话、工具调用审批、会话管理 |

### 前台 API（`/api/v1`）

| 模块 | 路由前缀 | 说明 |
|------|----------|------|
| 认证 | `/api/v1/auth` | 用户注册（手机/邮箱）、登录、登出、当前用户、Token 刷新 |
| 用户中心 | `/api/v1/user` | 资料、联系方式、密码、VIP、积分/金币流水、社交账号、登录历史 |
| 内容互动 | `/api/v1/likes`、`/api/v1/collections`、`/api/v1/comments` | 多态点赞/收藏/评论（支持 `parent_id` 回复、`mentioned_users` 艾特） |
| 通知 | `/api/v1/notifications` | 客户端通知列表、未读数、已读 |
| 地址 | `/api/v1/addresses` | 收货地址管理 |
| 反馈 | `/api/v1/feedbacks` | 用户提交反馈 |
| 微信 | `/api/v1/wechat` | 微信公众号与小程序绑定 |

## 队列与定时任务

- 默认队列驱动 `database`，生产推荐切换 Redis
- 启动 Horizon：`php artisan horizon`
- 定时任务在 `routes/console.php` 定义，生产环境需配置 Cron：
  ```
  * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
  ```
- 调度任务执行情况自动记录到 `schedule_logs` 表，可在后台审计模块查看

## License

MIT
