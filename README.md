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
├── Models/          # Admin/、App/、Coin/、Content/、Invite/、Operation/、Point/、System/、Task/、User/、UserSegment/、Wallet/
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
| 认证授权 | `/admin/auth` | 管理员登录（带节流限速，失败尝试记入登录历史）、登出、当前用户信息、刷新 Token |
| 管理员 | `/admin/admins` | 管理员 CRUD、状态切换、密码重置、角色分配、登录历史（含成功/失败记录，可按 `result` 过滤），列表可导出 Excel |
| 角色权限 | `/admin/roles`、`/admin/permissions` | RBAC 角色与权限管理 |
| 菜单 | `/admin/menus` | 树形菜单管理（目录/菜单/按钮） |
| 配置 | `/admin/settings`、`/admin/config` | 系统配置项与按分组查看 |
| 地区 | `/admin/areas` | 省市区地区数据（树形） |
| 字典 | `/admin/dicts`、`/admin/dict-items` | 字典类型与字典项管理 |
| 附件 | `/admin/attachments`、`/admin/uploader` | 附件列表、上传（中转/直传）、重命名、移动、删除 |
| 验证码 | `/admin/phone-codes`、`/admin/mail-codes` | 短信/邮件验证码发送记录（只读） |
| 通知 | `/admin/notifications` | 站内通知列表、未读数、标记已读、批量已读、清理已读 |
| 应用版本 | `/admin/apps`、`/admin/apps/{app}/versions` | 客户端应用与版本发布/撤回/废弃 |
| 前台用户 | `/admin/users` | 前台用户 CRUD、状态、积分/金币调整、VIP 延期、登录历史（可按 `result` 过滤）、社交账号、流水、回收站，列表与流水可导出 Excel；含用户钱包查询（余额/流水/调整） |
| 钱包管理 | `/admin/wallet` | 真实货币钱包：钱包统计、全局流水列表/详情（可导出）、充值订单（统计/详情/手动确认到账/关单，可导出）、提现申请（统计/详情/审核通过/驳回/标记打款/打款失败，可导出） |
| 反馈管理 | `/admin/feedbacks` | 用户反馈列表、统计、详情、处理、删除，列表可导出 Excel |
| 实名认证 | `/admin/identity-verifications` | 用户实名认证审核（个人/企业），支持通过/驳回、统计、详情查看证件附件 |
| 评论管理 | `/admin/comments` | 评论列表（按审核状态/源类型/关键词/用户过滤）、详情（含 IP 与上级评论摘要）、回复链分页查询、审核（通过/驳回/审核中）、删除，列表可导出 Excel |
| 举报管理 | `/admin/reports` | 举报列表（按状态/分类/对象类型/时间/关键词过滤）、详情（含被举报对象快照）、处理（resolved/rejected + 联动处置动作）、统计、可导出 Excel。联动处置：hide_comment（隐藏评论）/ delete_comment（删除评论）/ freeze_user（冻结用户，可指定天数或永久） |
| Banner 管理 | `/admin/banners` | 轮播图 CRUD、状态切换、批量排序、展示位置选项（`positions`） |
| 公告管理 | `/admin/announcements` | 公告 CRUD 与状态流转（草稿/已发布/归档），支持状态切换 |
| 任务系统 | `/admin/task-groups`、`/admin/tasks`、`/admin/task-logs` | 任务分组 CRUD（含 options/状态切换）、任务 CRUD（6 种任务类型 + 3 种奖励 + 自动/手动领取模式，含状态切换）、任务完成日志（只读，列表/详情，支持任务/奖励类型/关键词/时间筛选） |
| 用户分群 | `/admin/user-segments`、`/admin/segment-tasks`、`/user/segment`、`/user/segment-task` | 用户分群（segment）CRUD：手动分群与规则分群两种类型，规则分群按 JSON 条件由定时任务每天 02:00 增量同步成员；支持为用户批量打标/移标（含笛卡尔批量，>1000 ID 自动转为异步任务入队，通过 `/admin/segment-tasks` 后端接口 + 前端 `/user/segment-task` 任务列表页轮询查询进度，完成/失败时站内信通知）、按分群筛选用户（AND 语义）、规则预览；公告群发可复用 `options` 接口拉取手动分群作为目标范围 |
| 邀请记录 | `/admin/invite-records` | 邀请关系查询（只读）：分页列表与详情，支持按邀请人/被邀请人（多态类型 + ID）、描述关键词、邀请时间范围筛选。邀请人与被邀请人均为多态关联，可跨 `user`/`admin` 主体查询 |
| 公共接口 | `/admin/common` | 与业务无关的通用选项（如 `source-types` 多态源类型下拉） |
| 监控 | `/admin/monitor`、`/admin/queue`、`/admin/debug` | Pulse 性能、Horizon 队列、Telescope 调试（仅 local） |
| 审计 | `/admin/audit-logs`、`/admin/schedule-logs`、`/admin/login-histories` | 操作审计、调度日志与全局登录历史（跨管理员/前台用户，可按 guard、结果、时间过滤）。**导出支持**：用户/评论/反馈/审计日志/钱包流水/充值订单/提现申请列表与用户积分、金币流水均提供 `GET /export` 端点（同步/异步双通道，大数据量走队列生成并通过站内信推送下载链接） |
| 仪表盘 | `/admin/dashboard` | 核心指标统计与趋势图 |
| AI 聊天 | `/admin/chat` | SSE 流式对话、工具调用审批、会话管理；入口由 `chat.use` 权限点保护，工具按管理员角色权限动态过滤并在执行层兜底校验 |

### 前台 API（`/api/v1`）

| 模块 | 路由前缀 | 说明 |
|------|----------|------|
| 认证 | `/api/v1/auth` | 用户注册（手机/邮箱）、登录、登出、当前用户、Token 刷新 |
| 用户中心 | `/api/v1/user` | 资料、头像上传（自动转 WebP）、联系方式、密码、VIP、积分/金币流水、社交账号、登录历史、实名认证（提交/状态查询）、账户注销 |
| 钱包 | `/api/v1/user/wallet` | 钱包概览（余额/冻结余额）、流水列表、充值（创建订单/我的订单/详情）、提现（提交申请/我的提现/详情） |
| 内容互动 | `/api/v1/likes`、`/api/v1/collections`、`/api/v1/follows`、`/api/v1/comments` | 多态点赞/收藏/关注/评论（支持 `parent_id` 回复、`mentioned_users` 艾特） |
| 访问记录 | `/api/v1/visits`、`/api/v1/user/visits` | 多态访问埋点（记录一次访问 + 我的访问记录列表，分页，按 `source_type` 过滤），用于 PV/UV 与"最近浏览"等业务场景 |
| 邀请 | `/api/v1/invite` | 我的邀请记录列表（分页）、邀请统计（邀请人数、是否有邀请人）。用户注册时携带 `invite_code` 即自动建立邀请关系（通过 `InviteRegistered` 事件 + 队列异步处理，不阻塞注册）；每个用户创建时自动分配唯一邀请码，可从用户资料的 `invite_code` 字段获取 |
| 任务中心 | `/api/v1/tasks` | 任务列表（按分组返回，含用户进度）、手动领取任务奖励；完善资料等任务由事件驱动自动判定发奖 |
| Banner | `/api/v1/banners` | 轮播图公开列表（无需认证，必传 `position` 位置参数，仅返回当前生效内容） |
| 公告 | `/api/v1/announcements` | 公告公开列表与详情（无需认证，仅已发布内容） |
| 通知 | `/api/v1/notifications` | 客户端通知列表、未读数、已读 |
| 地址 | `/api/v1/addresses` | 收货地址管理 |
| 反馈 | `/api/v1/user/feedbacks` | 提交反馈、我的反馈列表（均需登录，提交成功返回反馈单号） |
| 举报 | `/api/v1/user/reports` | 提交举报（仅基础提交接口，目标类型支持 `comment` / `user`，需登录）与我的举报列表。同一人对同一目标在未结案前不可重复提交（防刷由后端校验，必要时可在网关层加 `throttle`） |
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

本项目采用 [GNU Affero General Public License v3.0 或更高版本](LICENSE)（AGPL-3.0-or-later）。

这意味着：
- 你可以自由使用、修改、分发本软件
- 如果你修改本软件并通过网络向用户提供服务（例如部署为 Web 服务、SaaS 产品），**必须**向该服务的用户提供对应版本的完整源代码
- 任何基于本软件的衍生作品也必须保持相同的 AGPL-3.0-or-later 协议发布
- 源码分发时必须保留原版权声明与许可声明

详见 [LICENSE](LICENSE) 文件或 <https://www.gnu.org/licenses/agpl-3.0.html>。
