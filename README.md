# Laravel Skel Forge

基于 Laravel 13 的后台管理系统骨架，采用 DDD 架构并遵循 RESTful API 设计规范。

- 前端项目：[laravel-skel-admin](https://codeup.aliyun.com/xutongle/Skel/laravel-skel-admin)

## 技术栈

| 分层 | 技术 | 版本 |
|------|------|------|
| PHP | ^8.4 |
| 框架 | Laravel | 13.x |
| 认证 | Laravel Sanctum | 4.x |
| 权限 | spatie/laravel-permission | 8.x |
| 队列 | Laravel Horizon | 5.x |
| 实时通信 | Laravel Reverb / Laravel Echo | 1.x / 2.x |
| 应用服务器 | Laravel Octane | 2.x |
| 监控 | Laravel Pulse / Telescope | 1.x / 5.x |
| 全文搜索 | Laravel Scout | 11.x |
| AI | laravel/ai | 0.10.x |
| 缓存 | Redis (predis) |
| 前端 | Tailwind CSS v4 + Vite 8.x |
| 测试 | PHPUnit | 12.x |
| 代码风格 | Laravel Pint | 1.x |

## 目录结构

```
app/
├── Ai/                     # AI 能力（Agents、Tools）
│   ├── Agents/             # AI Agent（Assistant）
│   └── Tools/              # AI 工具（Audit/Settings/Stats/System/Users/Web）
├── Console/Commands/       # Artisan 命令
├── Enums/                  # 枚举类（AdminStatus、AppPlatform、AttachmentType、DictStatus、DictLabelType、ReleaseStatus、UserStatus 等）
│   └── Traits/HasLabel.php
├── Events/                 # 事件（Admin/LoginSucceeded、User/* 等）
├── Exceptions/             # 自定义异常
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          # 后台控制器（Auth、User、Role、Menu、Setting、Dict、AppRelease、AppVersion、Attachment、Chat、Monitor 等）
│   │   └── Api/V1/         # API v1 控制器（Auth、Register、Common、User、App）
│   ├── Middleware/         # 中间件（RecordAuditLog）
│   ├── Requests/           # 表单请求验证（Admin/、Api/V1/）
│   └── Resources/          # Eloquent API Resources（Admin/、Api/V1/）
├── Jobs/                   # 队列任务（User/DeleteAccessTokenJob、StatUserJob）
├── Listeners/              # 事件监听器（Admin/、System/、User/）
├── Mail/                   # 邮件类（MailVerifyCode）
├── Models/
│   ├── Admin/              # 后台模型（Admin、AdminMenu）
│   ├── App/                # 应用版本模型（AppRelease、AppVersion）
│   ├── Coin/               # 金币模型（CoinTrade）
│   ├── Point/              # 积分模型（PointRecord、PointTrade）
│   ├── System/             # 系统模型（Area、Attachment、AuditLog、Dict、DictItem、Setting、Role、Permission 等）
│   ├── User/               # 前台用户模型（UserExtra、UserProfile、UserStat）
│   ├── Traits/             # 模型 Trait（DateTimeFormatter、HasSettingsProperty、HasUser）
│   ├── Model.php           # 模型基类
│   └── User.php            # 前台用户模型
├── Observers/              # 模型观察者（UserObserver）
├── Policies/               # 授权策略（UserPolicy）
├── Providers/              # 服务提供者（App、DynamicConfig、Horizon、Telescope）
├── Rules/                  # 自定义验证规则（PhoneRule、MailCaptchaRule 等）
├── Services/               # 业务服务（Attachment、SmsCaptcha、WechatService 等）
├── Sms/                    # 短信类（VerifyCodeMessage）
└── Support/                # 辅助类（CoinHelper、PointHelper、UserHelper）

config/                     # 配置文件
database/
├── factories/              # 模型工厂
├── migrations/             # 数据库迁移
└── seeders/                # 数据填充
routes/
├── web.php                 # Web 路由
├── admin.php               # 后台路由（前缀 /admin，通过 bootstrap/app.php 注册）
├── api_v1.php              # API v1 路由
├── channels.php            # 广播频道
└── console.php             # 定时任务
lang/                       # 语言文件（zh_CN、en）
bootstrap/helpers.php       # 全局辅助函数
stubs/                      # 自定义 Artisan stub（agent、tool）
```

## 环境要求

- PHP >= 8.4
- PHP 扩展：`pdo`、`mbstring`、`openssl`、`redis`、`bcmath`、`ctype`、`json`、`fileinfo`、`tokenizer`、`xml`
- Composer >= 2.x
- Node.js >= 20.x
- MySQL 8.0+ / PostgreSQL / SQLite
- Redis（推荐，用于缓存、队列、会话）

## 安装

### 方式一：一键安装（推荐）

```bash
composer run setup
```

该命令会依次执行：`composer install` → 复制 `.env` → `key:generate` → `migrate --force` → `storage:link` → `npm install` → `npm run build`。

### 方式二：手动安装

```bash
# 1. 安装依赖
composer install

# 2. 复制环境配置
cp .env.example .env

# 3. 生成密钥
php artisan key:generate

# 4. 配置 .env 中的数据库连接，然后执行迁移
php artisan migrate

# 5. 创建存储链接
php artisan storage:link

# 6. 安装前端依赖并编译
npm install
npm run build
```

### 本地开发

推荐使用 [Laravel Herd](https://herd.laravel.com/) 提供本地服务，访问地址为 `http://laravel-skel-forge.test`（项目目录 kebab-case 名 + `.test`）。

启动完整开发环境（包含 server、queue、pail 日志、Vite）：

```bash
composer run dev
```

## 可用命令

| 命令 | 说明 |
|------|------|
| `composer run setup` | 一键安装项目 |
| `composer run dev` | 启动开发环境（server/queue/logs/vite） |
| `composer run test` | 运行全部测试 |
| `composer run check-style` | 检查代码风格 |
| `composer run fix-style` | 修复代码风格 |
| `php artisan route:list` | 查看路由列表 |
| `php artisan route:list --path=admin` | 查看后台路由 |

## API 路由

### 认证接口（`/admin/auth`）

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/admin/auth/login` | 管理员登录 | 否 |
| POST | `/admin/auth/logout` | 退出登录 | 是 |
| GET | `/admin/auth/info` | 获取当前管理员信息 | 是 |

请求/响应示例：

**登录请求：**
```json
POST /admin/auth/login
{
  "account": "admin",
  "password": "12345678"
}
```

**成功响应（HTTP 200）：**
```json
{
  "access_token": "1|laravel_sanctum_token_xxx",
  "user": {
    "user_id": 1,
    "user_name": "admin",
    "email": "admin@example.com",
    "avatar": "",
    "roles": ["super-admin"],
    "buttons": ["admin.index", "admin.create", "..."]
  }
}
```

**验证失败响应（HTTP 422）：**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "password": ["用户名或密码错误。"]
  }
}
```

**未认证响应（HTTP 401）：**
```json
{
  "message": "Unauthenticated."
}
```

### 认证机制

后台管理员使用 **Sanctum Token** 认证，前端需在请求头携带：

```
Authorization: Bearer {token}
Accept: application/json
```

默认管理员账号：`admin` / `12345678`（由迁移 `2026_07_31_111934_seed_initial_admin_data.php` 初始化）。

### 统一响应格式

项目调用 `JsonResource::withoutWrapping()` 取消默认的 `data` 包裹，因此响应格式按场景分为以下几种：

**单条资源（GET 详情、POST 创建）：** 直接返回资源对象

```json
{
  "id": 1,
  "name": "...",
  "...": "其他字段"
}
```

**分页列表（GET 列表）：** Laravel 标准分页结构

```json
{
  "data": [ { "...": "资源对象" } ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 100, "..." : "..." }
}
```

**操作类响应（PUT/PATCH/DELETE）：** 返回 `message` 或 204 No Content

```json
{ "message": "操作成功" }
```

**认证响应（POST login）：** 返回 `access_token` 与用户信息

```json
{
  "access_token": "1|laravel_sanctum_token_xxx",
  "user": { "id": 1, "name": "admin", "..." : "..." }
}
```

**错误响应：** HTTP 状态码 + `message`，验证错误附带 `errors`

```json
// 401 未认证
{ "message": "Unauthenticated." }

// 422 验证失败
{ "message": "The given data was invalid.", "errors": { "field": ["错误信息"] } }
```

HTTP 状态码遵循 RESTful 规范：2xx 成功、4xx 客户端错误、5xx 服务器错误。创建资源返回 201，无内容操作返回 204，表单验证错误返回 422，未认证返回 401。

### 审计日志接口（`/admin/audit-logs`）

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | `/admin/audit-logs` | 审计日志列表（分页） | 是 | `audit-logs.index` |
| GET | `/admin/audit-logs/{id}` | 审计日志详情 | 是 | `audit-logs.index` |

通过 `RecordAuditLog` 中间件自动记录后台操作日志，包含请求路径、方法、参数、响应状态、IP、用户代理等信息。

### 调度任务日志接口（`/admin/schedule-logs`）

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | `/admin/schedule-logs` | 调度日志列表（分页） | 是 | `schedule-logs.index` |
| GET | `/admin/schedule-logs/{id}` | 调度日志详情 | 是 | `schedule-logs.index` |

### 附件管理接口（`/admin/attachments`）

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | `/admin/attachments` | 附件列表（分页） | 是 | `attachments.index` |
| POST | `/admin/attachments/register` | 直传完成后登记元数据 | 是 | `attachments.create` |
| GET | `/admin/attachments/{id}` | 附件详情 | 是 | `attachments.index` |
| PUT | `/admin/attachments/{id}/rename` | 重命名显示名 | 是 | `attachments.edit` |
| PUT | `/admin/attachments/{id}/move` | 移动物理路径 | 是 | `attachments.edit` |
| DELETE | `/admin/attachments/{id}` | 删除单个附件 | 是 | `attachments.delete` |
| DELETE | `/admin/attachments` | 批量删除附件 | 是 | `attachments.delete` |
| GET | `/admin/attachments/{id}/download` | 流式下载 | 是 | `attachments.index` |
| GET | `/admin/attachments/{id}/temporary-url` | 私有盘临时签名 URL | 是 | `attachments.index` |

### 字典管理接口（`/admin/dicts`、`/admin/dict-items`）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET/POST | `/admin/dicts` | 字典类型列表/创建 | `dicts.index` / `dicts.create` |
| GET/PUT/DELETE | `/admin/dicts/{id}` | 字典详情/更新/删除（级联删除字典项） | 按操作 |
| GET/POST | `/admin/dict-items` | 字典项列表（支持 dict_id 筛选）/创建 | `dict-items.index` / `dict-items.create` |
| GET/PUT/DELETE | `/admin/dict-items/{id}` | 字典项详情/更新/删除 | 按操作 |

### 应用与版本管理接口

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET/POST | `/admin/apps` | 应用列表/创建 | `apps.index` / `apps.create` |
| GET/PUT/DELETE | `/admin/apps/{id}` | 应用详情/更新/删除 | 按操作 |
| GET/POST | `/admin/apps/{app}/versions` | 版本列表/创建 | `app-versions.index` / `app-versions.create` |
| GET/PUT/DELETE | `/admin/apps/{app}/versions/{version}` | 版本详情/更新/删除（已发布不可删） | 按操作 |
| POST | `/admin/apps/{app}/versions/{version}/publish` | 发布版本（同平台旧版本自动置为废弃） | `app-versions.edit` |
| POST | `/admin/apps/{app}/versions/{version}/withdraw` | 撤回已发布版本 | `app-versions.edit` |
| POST | `/admin/apps/{app}/versions/{version}/obsolete` | 废弃版本 | `app-versions.edit` |

### 缓存清理接口

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/admin/cache/clear` | 清理应用缓存（body 传 `types: ["all"]` 或 `["settings"]`） |

### App 版本检查接口（公开，`/api/v1/apps/check-update`）

客户端传入 `slug`（应用标识）、`platform`（android/ios/harmony）、`build`（当前构建号），返回：

```json
{
  "has_update": true,
  "force_update": false,
  "latest_version": {
    "version": "1.2.0",
    "build": 120,
    "platform": "android",
    "download_url": "https://...",
    "release_notes": "...",
    "force_update": false,
    "min_supported_build": 100
  }
}
```

## 认证与权限

- **双端认证模型**：
  - 前台用户（`User` 模型）：Sanctum Token 认证（guard: `web`）
  - 后台管理员（`Admin` 模型）：Sanctum Token 认证（guard: `admin`）
- **权限系统**：spatie/laravel-permission v8，支持角色（Role）和权限（Permission）
- **中间件别名**：`role`、`permission`、`role_or_permission`、`ability`、`abilities`
- **CSRF 豁免**：`/admin/*` 和 `/api/*` 路径不做 CSRF 校验
- **Sanctum Token 有效期**：默认 6 个月（262800 分钟）

## 数据库

迁移执行后会创建以下核心表：

| 表 | 说明 |
|----|------|
| `admins` | 后台管理员 |
| `admin_menus` | 后台菜单/按钮（树结构） |
| `users` | 前台用户 |
| `user_extra` / `user_profiles` / `user_stats` | 用户扩展资料/画像/统计 |
| `roles` / `permissions` | 角色/权限（spatie-permission） |
| `settings` | 系统设置 |
| `areas` | 地区数据 |
| `dicts` / `dict_items` | 数据字典类型/字典项 |
| `app_releases` / `app_versions` | 应用及版本管理 |
| `attachments` | 附件管理 |
| `audit_logs` | 审计日志 |
| `personal_access_tokens` | Sanctum Token |
| `sessions` / `cache` / `jobs` | 会话/缓存/队列 |
| `mail_codes` / `phone_codes` | 邮件/手机验证码 |
| `login_histories` | 登录历史 |
| `socials` | 第三方账号绑定 |
| `coin_trades` / `point_records` / `point_trades` | 金币/积分流水 |
| `schedule_logs` | 调度任务执行日志 |
| `agent_conversations` | AI Agent 对话记录 |
| `pulse_*` / `telescope_*` | 监控数据 |

初始数据由迁移 `2026_07_31_111934_seed_initial_admin_data.php` 填充，包含超级管理员角色和完整的后台菜单结构（含字典管理、应用管理等菜单与按钮权限）。

## 全局辅助函数

定义在 `bootstrap/helpers.php` 中：

| 函数 | 说明 |
|------|------|
| `cpu_count(): int` | 获取 CPU 核心数 |
| `per_page(Request $request, int $limit = 15): int` | 获取分页大小（限制 1~100） |
| `mobile_replace(?string $value): string` | 手机号脱敏 |
| `settings(string $key, $default)` | 快捷读取系统设置 |
| `validation_exception($field, $message)` | 快速抛出表单验证异常 |

### 系统设置项

通过 `settings()` 函数读取，可在后台「配置管理」中修改：

| Key | 类型 | 默认值 | 说明 |
|-----|------|--------|------|
| `schedule.log_enabled` | bool | `true` | 是否记录调度任务执行日志 |
| `schedule.log_prunable_days` | int | `30` | 调度日志保留天数（1~365），超过后由 `model:prune` 自动清理 |

## 编码规范

- 遵循 PSR-12，使用 PHP 8.4 特性（构造器属性提升、命名参数、枚举、match 表达式）
- 所有 PHP 文件强制 `declare(strict_types=1)` 和文件头 License 注释
- **修改 PHP 文件后必须运行格式化**：
  ```bash
  vendor/bin/pint --dirty --format agent
  ```
- API 统一使用 Eloquent API Resources 和版本控制
- 使用 `php artisan make:` 命令创建文件，传递 `--no-interaction` 参数
- 数据库操作始终通过迁移（migration），字段变更使用索引优化
- 控制器建议最长 200 行，方法建议最长 20 行

## 测试

使用 PHPUnit 12，采用新属性语法（`#[Test]`、`#[CoversClass]`、`#[Group]`、`#[TestDox]`）。

```bash
# 运行全部测试
php artisan test --compact

# 运行单个文件
php artisan test --compact tests/Feature/Admin/AuthControllerTest.php

# 按方法名过滤
php artisan test --compact --filter=login_with_valid_credentials

# 运行测试并生成覆盖率
php artisan test --coverage
```

测试命名使用 `snake_case`，所有测试基类为 `Tests\TestCase`，Feature 测试使用 `RefreshDatabase` trait。

## 监控与调试

| 工具 | 用途 | 访问路径 |
|------|------|----------|
| Laravel Pulse | 应用性能监控 | `/pulse` |
| Laravel Telescope | 调试辅助（仅 local 环境） | `/telescope` |
| Laravel Horizon | 队列监控面板 | `/horizon` |
| Laravel Pail | 实时日志查看 | `php artisan pail` |

## 定时任务

在 `routes/console.php` 中定义：

- `auth:clear-resets` — 每日 01:00 清理过期密码重置令牌
- `model:prune` — 每日 01:05 清理已标记删除的模型（含调度日志过期清理）
- `stats:user` — 每日 01:10 统计昨日用户数据
- `telescope:prune --hours=48 --keep-exceptions` — 每日 01:15 清理 Telescope 记录（保留异常）
- `horizon:snapshot` — 每 5 分钟记录应用状态快照

### 调度任务执行日志

系统自动记录每个调度任务的执行情况，包括任务名称、类型（command/callback/exec）、Cron 表达式、执行状态（执行中/成功/失败/跳过）、退出码、耗时与异常摘要。

- **自动记录**：通过监听 `ScheduledTaskStarting`、`ScheduledTaskFinished`、`ScheduledTaskFailed`、`ScheduledTaskSkipped` 事件自动写入 `schedule_logs` 表
- **开关控制**：系统设置 `schedule.log_enabled`（默认开启）
- **自动清理**：通过 `model:prune` 命令定期清理超过保留天数的日志，保留天数由 `schedule.log_prunable_days` 控制（默认 30 天）
- **后台查看**：只读 API `/admin/schedule-logs`（列表分页 + 详情），支持按名称、类型、状态、时间范围筛选

本地测试调度器：

```bash
php artisan schedule:work
```

生产环境使用 Cron：

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 队列

默认使用 `database` 驱动，生产环境推荐切换到 Redis。通过 Horizon 管理队列进程：

```bash
php artisan horizon          # 启动 Horizon
php artisan horizon:pause    # 暂停
php artisan horizon:continue # 继续
```

## 前端构建

```bash
npm run dev    # 开发模式（热更新）
npm run build  # 生产构建
```

前端使用 Tailwind CSS v4（通过 `@tailwindcss/vite` 插件集成），入口文件为 `resources/css/app.css` 和 `resources/js/app.js`。
前端 SPA 项目位于独立仓库 `laravel-skel-admin`，通过 Vite 代理访问后端 API。

## 配置说明

关键环境变量（`.env`）：

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `APP_NAME` | `Laravel` | 应用名称 |
| `APP_ENV` | `local` | 运行环境 |
| `APP_DEBUG` | `true` | 调试模式 |
| `APP_URL` | `http://laravel-skel-forge.test` | 应用 URL |
| `APP_LOCALE` | `zh_CN` | 默认语言 |
| `APP_TIMEZONE` | `Asia/Shanghai` | 默认时区 |
| `DB_CONNECTION` | `sqlite` | 数据库驱动 |
| `CACHE_STORE` | `database` | 缓存驱动（推荐 redis） |
| `QUEUE_CONNECTION` | `database` | 队列驱动（推荐 redis） |
| `SESSION_DRIVER` | `database` | Session 驱动（推荐 redis） |
| `REDIS_CLIENT` | `phpredis` | Redis 客户端（可选 predis） |
| `BROADCAST_CONNECTION` | `log` | 广播驱动（生产用 reverb） |

## 安全

- 所有输入通过 Form Request 验证
- 使用 Eloquent ORM 预处理语句防止 SQL 注入
- 内置 CSRF 保护、XSS 防护
- Sanctum Token 认证
- 密码使用 bcrypt 加密（BCRYPT_ROUNDS=12）
- Telescope 在生产环境默认禁用
- 异常消息不暴露敏感字段
- 操作审计日志（RecordAuditLog 中间件）

## AI 能力

项目集成 `laravel/ai` 提供 AI Agent 能力：

- **Assistant Agent**：后台 AI 助手，支持多工具调用
- **内置工具**：审计日志查询、验证码查询、系统设置读写、仪表盘统计、地区查询、系统信息、用户管理（余额调整/VIP延期/状态设置/重置密码等）、网页抓取
- **SSE 流式输出**：前端通过 `/admin/chat` 接口以 Server-Sent Events 实时接收 AI 响应
- **工具审批**：敏感操作（如调整用户余额）需管理员审批后继续执行

## License

MIT
