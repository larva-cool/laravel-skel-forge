# Laravel Skel Forge 产品需求文档 (PRD)

## 1. 项目概述

### 1.1 项目背景
Laravel Skel Forge 是一个基于 Laravel 13.x 的企业级 API 骨架项目，采用领域驱动设计（DDD）架构，内置完整的管理员/用户双端认证、RBAC 权限管理、后台管理系统、AI 助手能力，旨在为开发者提供一个快速构建高质量后端 API 服务的基础框架。

- 前端配套项目：**laravel-skel-admin**（Vue 3 + TypeScript + Element Plus 管理后台 SPA）
- 本地开发域名：`http://laravel-skel-forge.test`（Laravel Herd 自动提供）

### 1.2 项目目标
- 提供开箱即用的企业级 API 开发模板
- 集成成熟的认证、授权和权限管理方案
- 支持多用户系统（管理员+前台用户，双 Guard 隔离）
- 内置常用的系统管理功能（用户、角色、菜单、配置、地区、附件、验证码、通知、审计）
- 集成 AI Agent 能力，为后台提供智能助手
- 内置完整的监控、队列、定时任务、调度日志体系
- 提供完整的开发、测试和部署方案

### 1.3 目标用户
- **后端开发人员**：快速搭建 API 服务
- **技术团队**：统一技术栈和开发规范
- **初创企业**：快速构建 MVP 产品
- **教育机构**：学习 Laravel 最佳实践

## 2. 产品定位

### 2.1 产品类型
企业级 API 开发框架模板

### 2.2 核心价值
- **快速启动**：一键安装和配置，快速开始开发
- **安全可靠**：内置完整的认证和权限体系
- **架构清晰**：采用 DDD 分层架构，代码组织规范
- **功能完整**：包含常用的系统管理、监控、AI 能力
- **易于扩展**：模块化设计，易于添加新功能
- **实时通信**：Laravel Reverb + Echo 提供 WebSocket 能力

### 2.3 市场定位
- **技术层次**：中高级后端开发框架
- **应用场景**：企业管理系统、电商后台、SaaS 服务
- **目标规模**：中小型到大型企业级应用

## 3. 功能需求

### 3.1 用户系统

#### 3.1.1 管理员用户系统（Guard: admin）
| 功能 | 说明 |
|------|------|
| 管理员登录 | 支持用户名/邮箱/手机号登录，可选微信/Apple 授权登录 |
| Token 管理 | 刷新 Token、查看已签发 Token、销毁指定 Token |
| 管理员信息管理 | 查看和修改个人资料、上传头像 |
| 密码管理 | 修改密码、重置密码 |
| 状态管理 | 启用/禁用管理员账号 |
| 角色分配 | 为管理员分配角色（多对多） |
| 登录历史 | 记录管理员登录历史（IP、设备、浏览器） |
| 权限控制 | 基于 RBAC 的权限管理（spatie/laravel-permission v8） |

#### 3.1.2 前台用户系统（Guard: web）
| 功能 | 说明 |
|------|------|
| 用户注册 | 支持手机号/邮箱注册 |
| 用户认证 | 基于 Sanctum 的 Token 认证 |
| 用户信息管理 | 查看和修改个人资料（profile/extra/stats 三张扩展表） |
| 密码重置 | 通过邮箱/手机验证码重置密码 |
| 联系方式重置 | 通过验证码重置手机号/邮箱 |
| 用户状态管理 | 启用、禁用、冻结用户 |
| VIP会员系统 | 支持VIP会员延期功能 |
| 积分/金币系统 | 用户积分和金币流水管理（调整余额、查询流水） |
| 第三方绑定 | 查询用户社交账号绑定记录 |
| 登录历史 | 记录用户登录历史 |

### 3.2 认证与授权

#### 3.2.1 认证系统
| 功能 | 说明 |
|------|------|
| Sanctum Token认证 | 双 Guard Token 认证（admin/web），默认有效期 6 个月 |
| 多账号登录 | 支持用户名/邮箱/手机号三种登录方式 |
| 第三方登录 | 微信公众号授权登录、Apple 登录（管理员端预留） |
| 验证码服务 | 短信验证码（EasySMS）和邮件验证码 |
| Token 管理 | 刷新 Token、查看/撤销已签发的个人访问令牌 |
| 登录防护 | 登录频率限制、IP 限制 |

#### 3.2.2 权限管理
| 功能 | 说明 |
|------|------|
| 角色管理 | 创建、编辑、删除角色 |
| 权限管理 | 管理系统权限列表 |
| 角色权限分配 | 为角色分配权限 |
| 管理员角色分配 | 为管理员分配角色 |
| 权限检查 | 路由级（permission:xxx 中间件）和方法级权限控制 |
| 菜单权限 | 基于权限的菜单显示控制，前端自动过滤无权限路由 |

### 3.3 后台管理系统

#### 3.3.1 系统设置
| 功能 | 说明 |
|------|------|
| 配置管理 | 管理系统配置项（支持 string/int/float/bool/json 数据类型），支持分组查看和批量更新 |
| 菜单管理 | 管理后台菜单/按钮结构（树结构，支持目录/菜单/按钮三种类型） |
| 字典管理 | 管理数据字典类型和字典项（支持标签样式 default/primary/success/warning/danger/info），字典项按排序权重排列，删除字典时级联删除字典项 |
| 地区管理 | 管理省市区地区数据（树结构，含经纬度、区号、邮编） |
| 应用管理 | 管理客户端应用（名称、唯一标识 slug、描述、图标、排序、启用状态），支持查看各平台最新发布版本 |
| 应用版本管理 | 管理应用版本（平台：安卓/iOS/鸿蒙；版本号、构建号、下载地址、更新说明、最低支持版本、强制更新标记、文件大小/哈希），支持草稿/发布/撤回/废弃四种状态，发布时自动将同应用同平台的旧发布版本置为废弃 |
| 应用缓存清理 | 支持清理全部缓存或仅清理系统设置缓存（`types=all` 或 `types=settings`） |
| 操作审计日志 | 通过中间件自动记录后台操作（路径、方法、参数、状态码、IP、UA、耗时），只读查看 |
| 调度任务日志 | 自动记录定时任务执行情况（成功/失败/跳过、退出码、耗时、异常），支持按保留天数自动清理 |

#### 3.3.2 运营管理
| 功能 | 说明 |
|------|------|
| 评论管理 | 评论列表（支持按审核状态/源类型/关键词/用户过滤）、评论详情（含 IP、上级评论摘要）、回复链查看（分页展示指定评论下的直接回复，支持链内下钻）、审核（通过/驳回/设为审核中，可填审核备注）、删除 |
| 反馈管理 | 用户反馈列表与统计、反馈详情（含附件图片、客户端信息）、处理（调整状态/优先级、填写处理备注）、删除 |
| 短信验证码记录 | 查看短信验证码发送记录（只读） |
| 邮件验证码记录 | 查看邮件验证码发送记录（只读） |
| 上传服务 | 文件/图片/视频/APK 上传接口，生成直传 Token（兼容 S3 协议云存储） |
| 通知管理 | 站内通知列表、未读数、标记已读、批量已读、清理已读 |

#### 3.3.3 公共选项接口
| 功能 | 说明 |
|------|------|
| 源类型选项 | 基于 `config/morph_maps.php` 的 morph map 输出多态源类型选项（label/value），供前端筛选下拉使用。此类与业务模块无关的通用选项统一挂在 `CommonController` 下（路由前缀 `/admin/common`） |

#### 3.3.4 附件管理（上传文件管理器）
| 功能 | 说明 |
|------|------|
| 附件列表 | 分页查询，支持类型/磁盘/扩展名/上传者/关键词/时间范围筛选 |
| 附件详情 | 查看单个附件的完整元数据（含物理文件存在性检查） |
| 附件删除 | 单个删除、批量删除，同步删除 Storage 物理文件 |
| 附件预览 | 图片/视频返回可访问 URL（公有盘直链，私有盘返回 null） |
| 附件下载 | 服务端流式下载，保留原始文件名 |
| 临时 URL | 为私有磁盘生成有时效的签名访问地址 |
| 附件重命名 | 修改显示名称，不改动物理路径 |
| 附件移动 | 在同一磁盘内移动物理路径（Storage 层 move） |
| 磁盘列表 | 查询可用的存储磁盘列表 |
| 直传登记 | 客户端直传云存储后回调登记元数据 |

#### 3.3.5 监控面板
| 功能 | 说明 |
|------|------|
| 服务器性能 | 读取 Laravel Pulse 数据（CPU、内存、请求量） |
| 队列监控 | 队列负载、慢任务统计 |
| Reverb 监控 | WebSocket 连接数、消息吞吐 |
| 缓存监控 | 缓存命中率统计 |
| 异常监控 | 应用异常聚合统计 |
| 慢查询/慢请求/慢任务 | 数据库慢查询、慢 HTTP 请求、慢队列任务 |
| 外部请求监控 | 慢外部 HTTP 请求 |

#### 3.3.6 调试面板（Telescope，仅 local 环境）
| 功能 | 说明 |
|------|------|
| 标签管理 | 查询/监控/取消监控标签 |
| 记录开关 | 开启/暂停 Telescope 记录 |
| 条目查询 | 分页查询 Telescope 条目、查看详情、标记解决、删除条目 |

#### 3.3.7 队列管理（Horizon）
| 功能 | 说明 |
|------|------|
| 队列状态 | 队列统计、负载、Master/Supervisor 进程状态 |
| 标签监控 | 监控/取消监控指定标签 |
| 任务指标 | Jobs/Queues 吞吐指标和详情 |
| 批处理 | 批处理列表、详情、重试失败批次 |
| 任务列表 | 待处理/已完成/失败/静默任务列表和详情，重试失败任务 |

#### 3.3.8 数据概览
| 功能 | 说明 |
|------|------|
| 仪表盘统计 | 今日/总数统计（用户数、管理员数等核心指标） |

### 3.4 AI 助手

基于 `laravel/ai` 集成的后台 AI 助手能力：

| 功能 | 说明 |
|------|------|
| SSE 流式对话 | 以 Server-Sent Events 实时推送 AI 响应，支持思考过程（reasoning）和文本输出 |
| 工具调用 | AI 可调用内置工具查询数据或执行操作（审计日志查询、验证码查询、系统设置读写、仪表盘统计、地区查询、系统信息、用户管理、网页抓取等） |
| 工具审批 | 敏感操作（如调整用户余额、VIP 延期、重置密码等）需管理员二次审批后继续执行 |
| 对话历史 | 按会话（Conversation）保存对话记录，支持查看历史会话、删除会话 |

### 3.5 核心服务

#### 3.5.1 验证码服务
| 功能 | 说明 |
|------|------|
| 短信验证码发送 | 支持多种场景（登录、注册、重置密码等），EasySMS 多网关支持 |
| 短信验证码验证 | 验证验证码的正确性，过期/错误次数限制 |
| 邮件验证码发送 | 通过邮件发送验证码 |
| 邮件验证码验证 | 验证邮件验证码 |
| 验证码记录管理 | 记录和查询验证码发送历史 |

#### 3.5.2 文件上传服务
| 功能 | 说明 |
|------|------|
| 文件上传 | 通用文件上传接口 |
| 图片上传 | 图片专用上传接口 |
| 视频上传 | 视频专用上传接口 |
| APK 上传 | Android 安装包专用上传接口（返回附件 id 和文件元信息） |
| 上传Token生成 | 生成临时直传 Token（兼容 S3 协议） |
| 文件安全 | 上传文件类型和大小限制，自动写入附件台账 |

#### 3.5.3 系统监控
| 功能 | 说明 |
|------|------|
| 应用性能监控 | Laravel Pulse 提供性能监控（/pulse） |
| 调试辅助 | Laravel Telescope 提供调试功能（/telescope，仅 local） |
| 队列管理 | Laravel Horizon 提供队列管理（/horizon） |
| 实时日志 | Laravel Pail 提供实时日志查看 |
| WebSocket | Laravel Reverb 提供实时通信服务 |
| 高性能服务 | Laravel Octane 支持 Swoole/RoadRunner |

## 4. 技术架构

### 4.1 技术栈

| 技术 | 版本 | 用途 |
|------|------|------|
| PHP | ^8.4 | 编程语言 |
| Laravel | ^13.x | Web 框架 |
| Laravel Sanctum | ^4.x | API 认证 |
| Spatie Laravel Permission | ^8.x | 权限管理 |
| Laravel Horizon | ^5.x | 队列管理 |
| Laravel Pulse | ^1.x | 性能监控 |
| Laravel Telescope | ^5.x | 调试工具 |
| Laravel Reverb | ^1.x | WebSocket 服务 |
| Laravel Octane | ^2.x | 高性能应用服务 |
| Laravel Scout | ^11.x | 全文搜索 |
| Laravel Echo | ^2.x | 前端 WebSocket 客户端 |
| Laravel AI | ^0.10.x | AI Agent 能力 |
| Redis | - | 缓存和队列驱动 |
| Predis | ^2.x | Redis 客户端 |
| EasySMS | ^3.x | 短信发送 |
| Intervention Image | ^4.x | 图像处理 |
| Tailwind CSS | v4 | 前端样式 |
| Vite | ^8.x | 前端构建 |
| PHPUnit | ^12.x | 单元/功能测试 |
| Laravel Pint | ^1.x | 代码格式化 |

### 4.2 项目结构

```
laravel-skel-forge/
├── app/
│   ├── Ai/                  # AI 能力
│   │   ├── Agents/          # AI Agent（Assistant）
│   │   └── Tools/           # AI 工具（Audit/Settings/Stats/System/Users/Web 等）
│   ├── Console/
│   │   └── Commands/        # Artisan 命令
│   ├── Enums/               # 枚举类（含 Traits/HasLabel）
│   │   ├── AppPlatform.php          # 应用平台枚举（android/ios/harmony）
│   │   ├── DictLabelType.php        # 字典标签样式枚举
│   │   ├── DictStatus.php           # 字典状态枚举
│   │   └── ReleaseStatus.php        # 版本发布状态枚举（draft/published/withdrawn/obsolete）
│   ├── Events/              # 事件类（Admin/、User/、System/ 等子目录）
│   ├── Exceptions/          # 自定义异常
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/       # 后台控制器（Auth、Admin、User、Role、Menu、Setting、Area、Attachment、Uploader、PhoneCode、MailCode、Notification、Chat、Dashboard、Monitor、Debug、Queue、AuditLog、ScheduleLog、AppRelease、AppVersion、Dict、DictItem、Main）
│   │   │   └── Api/V1/      # API v1 控制器（Auth、Register、Common、User、App）
│   │   ├── Middleware/      # 中间件（RecordAuditLog）
│   │   ├── Requests/        # 表单请求验证（Admin/、Api/V1/）
│   │   └── Resources/       # API 资源（Admin/、Api/V1/）
│   ├── Jobs/                # 队列任务（User/DeleteAccessTokenJob、StatUserJob）
│   ├── Listeners/           # 事件监听器（Admin/、System/、User/）
│   ├── Mail/                # 邮件类（MailVerifyCode）
│   ├── Models/
│   │   ├── Admin/           # 管理员模型（Admin、AdminMenu）
│   │   ├── App/             # 应用版本模型（AppRelease、AppVersion）
│   │   ├── Coin/            # 金币模型（CoinTrade）
│   │   ├── Point/           # 积分模型（PointRecord、PointTrade）
│   │   ├── System/          # 系统模型（Area、Attachment、AuditLog、Dict、DictItem、Setting、Role、Permission、Notification、MailCode、PhoneCode、ScheduleLog 等）
│   │   ├── User/            # 用户扩展模型（UserExtra、UserProfile、UserStat）
│   │   ├── Traits/          # 模型 Trait（DateTimeFormatter、HasSettingsProperty、HasUser）
│   │   ├── Model.php        # 模型基类
│   │   └── User.php         # 前台用户模型
│   ├── Observers/           # 模型观察者（UserObserver）
│   ├── Policies/            # 授权策略（UserPolicy）
│   ├── Providers/           # 服务提供者（App、DynamicConfig、Horizon、Telescope）
│   ├── Rules/               # 自定义验证规则（PhoneRule、MailCaptchaRule 等）
│   ├── Services/            # 业务服务（AttachmentService、SmsCaptchaService、WechatService 等）
│   ├── Sms/                 # 短信消息类（VerifyCodeMessage）
│   └── Support/             # 辅助类（CoinHelper、PointHelper、UserHelper）
├── bootstrap/
│   └── helpers.php          # 全局辅助函数
├── config/                  # 配置文件
├── database/
│   ├── factories/           # 模型工厂
│   ├── migrations/          # 数据库迁移
│   └── seeders/             # 数据填充
├── lang/                    # 语言文件（zh_CN、en）
├── public/
├── resources/
├── routes/                  # 路由文件
│   ├── web.php              # Web 路由
│   ├── admin.php            # 后台路由（前缀 /admin，通过 bootstrap/app.php 注册）
│   ├── api_v1.php           # API v1 路由
│   ├── channels.php         # 广播频道
│   └── console.php          # 定时任务
├── stubs/                   # 自定义 Artisan stub（agent、tool）
├── storage/
└── tests/                   # 测试文件
```

### 4.3 架构设计

#### 4.3.1 DDD 分层
项目采用领域驱动设计思想，按业务领域组织代码：

- **Models Layer**：数据模型和业务逻辑，按领域分子目录（Admin/System/User/Coin/Point）
- **Services Layer**：业务服务封装，处理跨模型事务逻辑（如附件台账+Storage一致性）
- **Controllers Layer**：HTTP 请求编排，调用 Service 和 Resource
- **Resources Layer**：API 响应格式化
- **Requests Layer**：请求参数校验

#### 4.3.2 请求流转
```
HTTP Request → Route → Middleware (Auth/Permission/Audit) → FormRequest Validation
→ Controller → Service (业务逻辑) → Model (Eloquent) → Database
                                            → Resource (响应格式化) → JSON Response
```

AI 聊天特殊流转：
```
POST /admin/chat → ChatController → AI Agent (laravel/ai) → Tool 调用 → SSE Stream 响应
                                            ↓ (敏感操作)
                                       审批等待 → POST /admin/chat/approve → 继续执行
```

## 5. 数据模型

### 5.1 核心数据表

#### 5.1.1 管理员表 (admin_users)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| user_id | BIGINT | 主键（统一 user_id 命名） |
| user_name | VARCHAR | 用户名 |
| email | VARCHAR | 邮箱地址 |
| phone | VARCHAR | 手机号 |
| name | VARCHAR | 昵称/姓名 |
| avatar | VARCHAR | 头像 URL |
| status | TINYINT | 状态（0禁用，1启用） |
| password | VARCHAR | 密码哈希 |
| login_count | INT | 登录次数 |
| last_login_ip | VARCHAR | 最后登录 IP |
| last_login_at | TIMESTAMP | 最后登录时间 |
| last_active_at | TIMESTAMP | 最后活动时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

#### 5.1.2 后台菜单表 (admin_menus)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| menu_id | BIGINT | 主键 |
| parent_id | BIGINT | 父级菜单 ID |
| path | VARCHAR | 路由路径 |
| name | VARCHAR | 路由名称 |
| component | VARCHAR | 组件路径 |
| redirect | VARCHAR | 重定向路径 |
| title | VARCHAR | 菜单标题 |
| icon | VARCHAR | 菜单图标 |
| type | TINYINT | 类型（0目录，1菜单，2按钮） |
| permission | VARCHAR | 权限标识 |
| is_enable | BOOLEAN | 是否启用 |
| is_hide | BOOLEAN | 是否隐藏菜单 |
| is_hide_tab | BOOLEAN | 是否隐藏标签 |
| is_iframe | BOOLEAN | 是否 iframe 嵌入 |
| keep_alive | BOOLEAN | 是否缓存 |
| is_full_page | BOOLEAN | 是否全屏 |
| fixed_tab | BOOLEAN | 是否固定标签 |
| show_badge | BOOLEAN | 是否显示徽章 |
| show_text_badge | VARCHAR | 徽章文本 |
| active_path | VARCHAR | 激活路径 |
| link | VARCHAR | 外部链接 |
| sort | INT | 排序 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 5.1.3 用户表 (users)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| user_id | BIGINT | 主键 |
| user_name | VARCHAR | 用户名 |
| email | VARCHAR | 邮箱地址 |
| mobile | VARCHAR | 手机号 |
| avatar | VARCHAR | 头像 |
| status | TINYINT | 状态 |
| available_points | INT | 可用积分 |
| available_coins | INT | 可用金币 |
| vip_expires_at | TIMESTAMP | VIP 过期时间 |
| password | VARCHAR | 密码哈希 |
| login_count | INT | 登录次数 |
| last_login_ip | VARCHAR | 最后登录 IP |
| last_login_at | TIMESTAMP | 最后登录时间 |
| last_active_at | TIMESTAMP | 最后活动时间 |
| email_verified_at | TIMESTAMP | 邮箱验证时间 |
| mobile_verified_at | TIMESTAMP | 手机验证时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

#### 5.1.4 用户扩展表
| 表名 | 说明 |
|------|------|
| user_extras | 用户额外信息（性别、生日、签名等） |
| user_profiles | 用户画像/偏好 |
| user_stats | 用户统计数据（积分、金币、邀请等） |

#### 5.1.5 角色和权限表（spatie/laravel-permission）
| 表名 | 说明 |
|------|------|
| roles | 角色表 |
| permissions | 权限表 |
| model_has_roles | 模型角色关联表（多态） |
| model_has_permissions | 模型权限关联表（多态） |
| role_has_permissions | 角色权限关联表 |

#### 5.1.6 系统配置表 (settings)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| name | VARCHAR | 配置名称 |
| key | VARCHAR | 配置键名（唯一） |
| value | TEXT | 配置值 |
| cast_type | VARCHAR | 数据类型（string/int/float/bool/json） |
| input_type | VARCHAR | 输入类型 |
| param | TEXT | 参数配置（JSON） |
| remark | VARCHAR | 备注 |
| sort | INT | 排序 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 5.1.7 地区表 (areas)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| parent_id | BIGINT | 父级地区 ID |
| name | VARCHAR | 地区名称 |
| area_code | INT | 地区代码 |
| city_code | VARCHAR | 城市代码 |
| lat | DECIMAL | 纬度 |
| lng | DECIMAL | 经度 |
| level | TINYINT | 层级 |
| sort | INT | 排序 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 5.1.8 验证码表
| 表名 | 说明 | 关键字段 |
|------|------|----------|
| phone_codes | 短信验证码 | phone、scene、code、state、ip、send_at |
| mail_codes | 邮件验证码 | email、scene、code、state、ip、send_at |

#### 5.1.9 登录历史表 (login_histories)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| user_type | VARCHAR | 用户类型（多态：App\Models\User / App\Models\Admin\Admin） |
| user_id | BIGINT | 用户 ID |
| ip | VARCHAR | 登录 IP |
| port | INT | 端口 |
| platform | VARCHAR | 平台 |
| device | VARCHAR | 设备 |
| browser | VARCHAR | 浏览器 |
| user_agent | TEXT | User-Agent |
| address | VARCHAR | 地址 |
| login_at | TIMESTAMP | 登录时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 5.1.10 附件表 (attachments)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键（从 10000000 起） |
| uploader_type | VARCHAR | 上传者类型（多态） |
| uploader_id | BIGINT | 上传者 ID |
| disk | VARCHAR | 存储磁盘（local/public/s3 等） |
| path | VARCHAR | 物理存储路径 |
| name | VARCHAR | 显示名称 |
| original_name | VARCHAR | 原始文件名 |
| extension | VARCHAR | 文件扩展名 |
| mime_type | VARCHAR | MIME 类型 |
| type | VARCHAR | 附件类型（image/video/audio/document/other） |
| size | BIGINT | 文件大小（字节） |
| hash | CHAR(32) | 文件 MD5 哈希（用于去重校验） |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

索引：`(uploader_type, uploader_id)` 复合索引、`type` 索引、`hash` 索引、`(disk, path)` 唯一索引。

#### 5.1.11 审计日志表 (audit_logs)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| admin_id | BIGINT | 操作管理员 ID |
| method | VARCHAR | HTTP 方法 |
| path | VARCHAR | 请求路径 |
| query | TEXT | Query 参数（JSON） |
| body | TEXT | 请求体（JSON，脱敏） |
| status_code | INT | 响应状态码 |
| ip | VARCHAR | 请求 IP |
| user_agent | VARCHAR | User-Agent |
| duration_ms | INT | 请求耗时（毫秒） |
| created_at | TIMESTAMP | 创建时间 |

#### 5.1.12 调度任务日志表 (schedule_logs)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| task_name | VARCHAR | 任务名称 |
| task_type | VARCHAR | 任务类型（command/callback/exec） |
| expression | VARCHAR | Cron 表达式 |
| status | VARCHAR | 执行状态（running/success/failed/skipped） |
| exit_code | INT | 退出码 |
| duration_ms | INT | 执行耗时（毫秒） |
| exception | TEXT | 异常信息摘要 |
| started_at | TIMESTAMP | 开始时间 |
| finished_at | TIMESTAMP | 结束时间 |
| created_at | TIMESTAMP | 创建时间 |

通过 `Schedule::task` 的事件监听自动记录，支持按 `schedule.log_prunable_days` 设置自动清理。

#### 5.1.13 AI 对话表 (agent_conversations / agent_messages)
| 表名 | 说明 |
|------|------|
| agent_conversations | AI 会话列表（管理员 ID、标题、模型、创建/更新时间） |
| agent_messages | AI 消息记录（会话 ID、角色 user/assistant/tool、内容、工具调用信息等） |

#### 5.1.14 数据字典表 (dicts / dict_items)
**字典类型表 (dicts)**
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| name | VARCHAR | 字典名称 |
| code | VARCHAR | 字典编码（唯一） |
| status | TINYINT | 状态（0 禁用，1 启用） |
| sort | INT | 排序权重（越小越靠前） |
| remark | VARCHAR | 备注 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

**字典数据表 (dict_items)**
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| dict_id | BIGINT | 所属字典 ID |
| label | VARCHAR | 字典项标签 |
| value | VARCHAR | 字典项值 |
| label_type | VARCHAR | 标签样式（default/primary/success/warning/danger/info，对应 Element Plus Tag type） |
| status | TINYINT | 状态（0 禁用，1 启用） |
| sort | INT | 排序权重 |
| remark | VARCHAR | 备注 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

删除字典时通过模型 `booted` 事件级联删除其所有字典项。

#### 5.1.15 应用发布表 (app_releases)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| name | VARCHAR | 应用名称 |
| slug | VARCHAR | 应用唯一标识（客户端传参用） |
| description | VARCHAR | 应用描述 |
| icon | VARCHAR | 应用图标 URL |
| sort | INT | 排序权重 |
| status | BOOLEAN | 启用状态 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

#### 5.1.16 应用版本表 (app_versions)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| app_release_id | BIGINT | 所属应用 ID |
| platform | VARCHAR | 平台（android/ios/harmony，枚举 AppPlatform） |
| version | VARCHAR | 语义化版本号（如 1.2.0） |
| build | INT | 构建号（整数递增，用于版本比较） |
| download_url | VARCHAR | 安装包下载地址 |
| release_notes | TEXT | 更新说明 |
| min_supported_version | VARCHAR | 最低支持版本号 |
| min_supported_build | INT | 最低支持构建号（低于此构建号需强制更新） |
| force_update | BOOLEAN | 是否强制更新 |
| file_size | BIGINT | 安装包大小（字节） |
| file_hash | VARCHAR | 文件哈希（校验用） |
| status | VARCHAR | 发布状态（draft/published/withdrawn/obsolete，枚举 ReleaseStatus） |
| published_at | TIMESTAMP | 发布时间 |
| created_by | BIGINT | 创建人管理员 ID |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

发布版本时会自动将同应用同平台下其他已发布版本置为 `obsolete`；已发布状态的版本不可删除，需先撤回或废弃。

#### 5.1.17 内容互动表 (likes / collections / comments)

**点赞表 (likes)**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| user_id | bigint | 点赞用户 |
| source_type / source_id | string / bigint | 多态目标（morphs） |
| extra | json | 扩展信息 |
| created_at / updated_at | timestamp | 时间戳 |

- 唯一约束 `uniq_user_like`：`user_id + source_type + source_id`，同一用户对同一资源只能点赞一次
- 模型事件：`created` 时对目标 `like_count` 自增，`deleted` 时自减

**收藏表 (collections)**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| user_id | bigint | 收藏用户 |
| source_type / source_id | string / bigint | 多态目标（morphs） |
| extra | json | 扩展信息 |
| created_at / updated_at | timestamp | 时间戳 |

- 唯一约束 `uniq_user_collection`：`user_id + source_type + source_id`
- 模型事件：`created` 时对目标 `collection_count` 自增，`deleted` 时自减

**评论表 (comments)**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| user_id | bigint | 评论用户 |
| source_type / source_id | string / bigint | 多态目标（自关联可实现楼层回复） |
| is_top | boolean | 是否置顶 |
| status | string | 审核状态 |
| like_count / comment_count | int | 点赞数 / 回复数 |
| content | string(1000) | 评论内容 |
| mentioned_users | json | 艾特用户 ID 列表 |
| ip_address | ip | 评论 IP |
| created_at | timestamp | 评论时间（无 `updated_at`） |

**互动能力 Trait**

被互动的业务模型通过 trait 接入，目标表需自备对应计数列：

| Trait | 提供方法 | 依赖计数列 |
|-------|---------|-----------|
| `HasLikeable` | `likes()`、`isLikedBy($user)` | `like_count` |
| `HasCollectionable` | `collections()`、`isCollectedBy($user)` | `collection_count` |
| `HasCommentable` | `comments()` | `comment_count` |

- `source_type` 仅接受 `config/morph_maps.php` 中注册的多态别名
- 计数列由模型事件自动维护，接入前须确认目标表已有对应字段
- **评论楼层回复约定**：当 `source_type = 'comment'` 时表示本条为对另一条评论的回复，`source_id` 即父评论 ID；顶级评论的 `source_type` 指向业务实体（如 `user`、`post`）。后台判定回复关系与查询回复链均基于此约定，无需额外 `parent_id` 字段

#### 5.1.18 其他核心表
| 表名 | 说明 |
|------|------|
| personal_access_tokens | Sanctum 个人访问令牌 |
| notifications | 站内通知（多态） |
| socials | 第三方账号绑定 |
| password_reset_tokens | 密码重置令牌 |
| coin_trades | 金币流水记录 |
| point_records | 积分记录 |
| point_trades | 积分流水记录 |
| dicts | 数据字典类型 |
| dict_items | 字典数据项 |
| likes | 点赞记录（多态） |
| collections | 收藏记录（多态） |
| comments | 评论记录（多态） |
| app_releases | 应用发布 |
| app_versions | 应用版本 |
| sessions | 会话存储 |
| cache / cache_locks | 缓存/缓存锁 |
| jobs / job_batches / failed_jobs | 队列任务/批次/失败任务 |
| pulse_values / pulse_entries / pulse_aggregates | Laravel Pulse 监控数据 |

## 6. API 接口说明

### 6.1 响应格式

项目使用 Laravel 原生响应 + Eloquent API Resources，**不使用统一 code/msg/data 包装**。调用 `JsonResource::withoutWrapping()` 取消默认 data 包裹，通过 HTTP 状态码表达结果：

**单条资源（GET 详情、POST 创建）：**
```json
{
  "id": 1,
  "name": "...",
  "...": "其他字段"
}
```

**分页列表（GET 列表）：**
```json
{
  "data": [ { "...": "资源对象" } ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 100 }
}
```

**操作成功（PUT/PATCH/DELETE）：** `204 No Content` 或 `{ "message": "操作成功" }`

**认证成功（POST login）：**
```json
{
  "access_token": "1|laravel_sanctum_token_xxx",
  "user": { "user_id": 1, "user_name": "admin", "...": "..." }
}
```

**错误响应：**
```json
// 401 未认证
{ "message": "Unauthenticated." }

// 403 无权限
{ "message": "This action is unauthorized." }

// 422 验证失败
{ "message": "The given data was invalid.", "errors": { "field": ["错误信息"] } }
```

### 6.2 HTTP 状态码规范

| 状态码 | 含义 |
|--------|------|
| 200 | 成功（查询/更新/同步二进制下载） |
| 201 | 创建成功 |
| 202 | 已接受（异步入队，如大批量导出任务，响应体携带任务入队提示消息） |
| 204 | 删除/操作成功，无返回内容 |
| 400 | 请求参数错误 |
| 401 | 未认证或 Token 过期 |
| 403 | 无权限访问 |
| 404 | 资源不存在 |
| 422 | 数据验证失败 |
| 429 | 请求频率超限 |
| 500 | 服务器错误 |

### 6.3 主要接口列表

所有后台接口前缀为 `/admin`，需 `Authorization: Bearer {token}` 请求头。

#### 6.3.1 认证接口（/admin/auth）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/admin/auth/login` | 管理员登录（账号+密码） | 否 |
| POST | `/admin/auth/wx-login` | 微信公众号授权登录 | 否 |
| POST | `/admin/auth/apple-login` | Apple 授权登录 | 否 |
| POST | `/admin/auth/refresh-token` | 刷新 Token | 是 |
| GET | `/admin/auth/tokens` | 查询已签发的所有 Token | 是 |
| DELETE | `/admin/auth/tokens/{tokenId}` | 销毁指定 Token | 是 |
| POST | `/admin/auth/logout` | 退出登录 | 是 |
| GET | `/admin/auth/info` | 获取当前管理员信息（含角色/按钮权限） | 是 |

#### 6.3.2 管理员管理（/admin/admins）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/admins` | 管理员列表（分页） | admins.index |
| POST | `/admin/admins` | 创建管理员 | admins.create |
| GET | `/admin/admins/{id}` | 管理员详情 | admins.index |
| PUT | `/admin/admins/{id}` | 更新管理员 | admins.edit |
| DELETE | `/admin/admins/{id}` | 删除管理员 | admins.delete |
| GET | `/admin/admins/profile` | 当前管理员资料 | - |
| PUT | `/admin/admins/profile` | 更新当前管理员资料 | - |
| POST | `/admin/admins/avatar` | 更新头像 | - |
| PUT | `/admin/admins/change-password` | 修改当前管理员密码 | - |
| GET | `/admin/admins/{id}/roles` | 获取管理员角色 | admins.edit |
| PUT | `/admin/admins/{id}/roles` | 分配管理员角色 | admins.edit |
| PUT | `/admin/admins/{id}/toggle-status` | 切换管理员状态 | admins.edit |
| PUT | `/admin/admins/{id}/reset-password` | 重置管理员密码 | admins.edit |
| GET | `/admin/admins/{id}/login-histories` | 管理员登录历史 | admins.index |

#### 6.3.3 前台用户管理（/admin/users）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/users` | 用户列表（分页） | users.index |
| GET | `/admin/users/{id}` | 用户详情 | users.index |
| PUT | `/admin/users/{id}` | 更新用户信息 | users.edit |
| DELETE | `/admin/users/{id}` | 删除用户 | users.delete |
| PUT | `/admin/users/{id}/toggle-status` | 切换用户状态 | users.edit |
| PUT | `/admin/users/{id}/reset-password` | 重置用户密码 | users.edit |
| PUT | `/admin/users/{id}/reset-contact` | 重置用户手机号/邮箱 | users.edit |
| PUT | `/admin/users/{id}/adjust-balance` | 调整用户积分/金币余额 | users.edit |
| PUT | `/admin/users/{id}/extend-vip` | 延长 VIP 有效期 | users.edit |
| GET | `/admin/users/{id}/login-histories` | 用户登录历史 | users.index |
| GET | `/admin/users/{id}/socials` | 用户第三方绑定 | users.index |
| GET | `/admin/users/{id}/point-trades` | 用户积分流水 | users.index |
| GET | `/admin/users/{id}/coin-trades` | 用户金币流水 | users.index |
| GET | `/admin/users/export` | 导出用户列表（xlsx，支持同步下载/异步入队双模式） | users.export |
| GET | `/admin/users/{id}/point-trades/export` | 导出用户积分流水（xlsx） | users.export |
| GET | `/admin/users/{id}/coin-trades/export` | 导出用户金币流水（xlsx） | users.export |

#### 6.3.4 角色管理（/admin/roles）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/roles` | 角色列表 | roles.index |
| POST | `/admin/roles` | 创建角色 | roles.create |
| GET | `/admin/roles/{id}` | 角色详情 | roles.index |
| PUT | `/admin/roles/{id}` | 更新角色 | roles.edit |
| DELETE | `/admin/roles/{id}` | 删除角色 | roles.delete |
| GET | `/admin/roles/permissions` | 获取全部权限列表 | roles.index |
| GET | `/admin/roles/{id}/permissions` | 获取角色权限 | roles.index |
| PUT | `/admin/roles/{id}/permissions` | 分配角色权限 | roles.edit |

#### 6.3.5 菜单管理（/admin/menus）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/menus` | 获取菜单树 | menus.index |
| POST | `/admin/menus` | 创建菜单 | menus.create |
| GET | `/admin/menus/{id}` | 菜单详情 | menus.index |
| PUT | `/admin/menus/{id}` | 更新菜单 | menus.edit |
| DELETE | `/admin/menus/{id}` | 删除菜单 | menus.delete |
| GET | `/admin/routes` | 获取前端路由配置（按当前管理员权限过滤） | - |

#### 6.3.6 缓存管理（/admin/cache）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/admin/cache/clear` | 清理应用缓存（types=all/settings，支持数组多选） | 是 |

#### 6.3.7 配置管理（/admin/settings）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/settings` | 配置列表（分页） | settings.index |
| POST | `/admin/settings` | 创建配置 | settings.create |
| GET | `/admin/settings/{id}` | 配置详情 | settings.index |
| PUT | `/admin/settings/{id}` | 更新配置 | settings.edit |
| DELETE | `/admin/settings/{id}` | 删除配置 | settings.delete |
| GET | `/admin/settings/groups` | 获取配置分组列表 | settings.index |
| GET | `/admin/settings/input-types` | 获取支持的输入类型 | settings.index |
| PUT | `/admin/settings/batch` | 批量更新配置值 | settings.edit |

#### 6.3.8 字典管理（/admin/dicts）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/dicts` | 字典类型列表（分页，支持关键字/状态筛选） | dicts.index |
| POST | `/admin/dicts` | 创建字典类型 | dicts.create |
| GET | `/admin/dicts/{id}` | 字典详情（含字典项） | dicts.index |
| PUT | `/admin/dicts/{id}` | 更新字典 | dicts.edit |
| DELETE | `/admin/dicts/{id}` | 删除字典（级联删除字典项） | dicts.delete |

#### 6.3.9 字典数据管理（/admin/dict-items）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/dict-items` | 字典项列表（分页，支持按 dict_id 筛选） | dict-items.index |
| POST | `/admin/dict-items` | 创建字典项 | dict-items.create |
| GET | `/admin/dict-items/{id}` | 字典项详情 | dict-items.index |
| PUT | `/admin/dict-items/{id}` | 更新字典项 | dict-items.edit |
| DELETE | `/admin/dict-items/{id}` | 删除字典项 | dict-items.delete |

#### 6.3.10 地区管理（/admin/areas）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/areas` | 获取地区树 | areas.index |
| POST | `/admin/areas` | 创建地区 | areas.create |
| GET | `/admin/areas/{id}` | 地区详情 | areas.index |
| PUT | `/admin/areas/{id}` | 更新地区 | areas.edit |
| DELETE | `/admin/areas/{id}` | 删除地区 | areas.delete |

#### 6.3.11 通知管理（/admin/notifications）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/admin/notifications` | 通知列表（分页） | 是 |
| GET | `/admin/notifications/unread` | 未读通知列表/未读数 | 是 |
| PUT | `/admin/notifications/mark-read` | 标记单条已读 | 是 |
| PUT | `/admin/notifications/mark-all-read` | 全部标记已读 | 是 |
| DELETE | `/admin/notifications/clear-read` | 清理已读通知 | 是 |

#### 6.3.12 评论管理（/admin/comments）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/comments` | 评论列表（分页，支持 `status`/`source_type`/`keyword`/`user_id` 过滤） | comments.index |
| GET | `/admin/comments/{comment}` | 评论详情（含 `ip_address`、`is_reply`、`parent_comment` 上级评论摘要） | comments.index |
| GET | `/admin/comments/{comment}/replies` | 回复列表（分页，返回该评论下的直接回复） | comments.index |
| POST | `/admin/comments/{comment}/review` | 审核评论（`status` 取 `approved`/`rejected`/`review`，可选 `remark`） | comments.edit |
| DELETE | `/admin/comments/{comment}` | 删除评论 | comments.delete |
| GET | `/admin/comments/export` | 导出评论列表（xlsx，支持同步下载/异步入队双模式） | comments.export |

> 评论父子关系通过多态自关联实现：回复的 `source_type='comment'`、`source_id` 指向父评论 ID。列表接口不返回 `ip_address`，仅详情接口返回。

#### 6.3.13 反馈管理（/admin/feedbacks）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/feedbacks` | 反馈列表（分页，支持 `keyword`/`type`/`status`/`priority` 过滤） | feedbacks.index |
| GET | `/admin/feedbacks/stats` | 反馈统计（总量、待推进、今日新增、已闭环） | feedbacks.index |
| GET | `/admin/feedbacks/{feedback}` | 反馈详情（含附件图片、客户端信息） | feedbacks.index |
| PUT | `/admin/feedbacks/{feedback}` | 处理反馈（调整 `status`/`priority`、填写 `admin_remark`） | feedbacks.edit |
| DELETE | `/admin/feedbacks/{feedback}` | 删除反馈 | feedbacks.delete |
| GET | `/admin/feedbacks/export` | 导出反馈列表（xlsx，支持同步下载/异步入队双模式） | feedbacks.export |

#### 6.3.14 公共选项（/admin/common）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/admin/common/source-types` | 多态源类型选项列表（基于 `config/morph_maps.php`，返回 `[{label, value}]`） | 是 |

> 与业务模块无关的通用选项、枚举、字典类接口统一由 `CommonController` 承载，仅需登录态，不绑定业务权限。

#### 6.3.15 验证码管理
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/phone-codes` | 短信验证码列表 | phone-codes.index |
| GET | `/admin/phone-codes/{id}` | 短信验证码详情 | phone-codes.index |
| GET | `/admin/mail-codes` | 邮件验证码列表 | mail-codes.index |
| GET | `/admin/mail-codes/{id}` | 邮件验证码详情 | mail-codes.index |

#### 6.3.16 上传管理（/admin/uploader）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/admin/uploader/file` | 上传通用文件 | 是 |
| POST | `/admin/uploader/image` | 上传图片 | 是 |
| POST | `/admin/uploader/video` | 上传视频 | 是 |
| POST | `/admin/uploader/apk` | 上传 APK 安装包 | 是 |
| POST | `/admin/uploader/token` | 获取直传 Token（S3 兼容） | 是 |

上传成功后返回字段包含附件 `id`，前端可直接用于附件管理。

#### 6.3.17 附件管理（/admin/attachments）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/attachments/disks` | 获取可用存储磁盘列表 | attachments.index |
| GET | `/admin/attachments` | 附件列表（分页+筛选） | attachments.index |
| POST | `/admin/attachments/register` | 直传完成后登记元数据 | attachments.create |
| GET | `/admin/attachments/{id}` | 附件详情 | attachments.index |
| PUT | `/admin/attachments/{id}/rename` | 重命名显示名 | attachments.edit |
| PUT | `/admin/attachments/{id}/move` | 移动物理路径 | attachments.edit |
| DELETE | `/admin/attachments/{id}` | 删除单个附件 | attachments.delete |
| DELETE | `/admin/attachments` | 批量删除附件（ids 数组） | attachments.delete |
| GET | `/admin/attachments/{id}/download` | 流式下载 | attachments.index |
| GET | `/admin/attachments/{id}/temporary-url` | 临时签名 URL | attachments.index |

列表筛选参数：`per_page`、`type`、`disk`、`keyword`、`extension`、`uploader_id`、`start_date`、`end_date`。

附件资源字段：
| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 附件 ID |
| name | string | 显示名称 |
| original_name | string | 原始文件名 |
| disk | string | 存储磁盘 |
| path | string | 存储路径 |
| url | string\|null | 可访问 URL（公有盘直链，私有盘为 null） |
| type | object | 附件类型（value + label） |
| extension | string | 扩展名 |
| mime_type | string | MIME 类型 |
| size | int | 字节数 |
| size_text | string | 人类可读大小 |
| exists | bool | 物理文件是否存在（仅详情接口返回） |
| uploader | object\|null | 上传者摘要（id + name） |
| created_at | string | 创建时间 |

#### 6.3.18 应用管理（/admin/apps）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/apps` | 应用列表（分页，支持关键字/状态/日期筛选，预加载各平台最新版本） | apps.index |
| POST | `/admin/apps` | 创建应用 | apps.create |
| GET | `/admin/apps/{id}` | 应用详情 | apps.index |
| PUT | `/admin/apps/{id}` | 更新应用 | apps.edit |
| DELETE | `/admin/apps/{id}` | 删除应用（软删除） | apps.delete |

应用资源字段包含 `latest_versions`（各平台最多 3 条已发布版本，按 build 倒序）。

#### 6.3.19 应用版本管理（/admin/apps/{app}/versions）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/apps/{app}/versions` | 版本列表（分页，支持平台/状态/版本号/强制更新/日期筛选） | app-versions.index |
| POST | `/admin/apps/{app}/versions` | 创建版本 | app-versions.create |
| GET | `/admin/apps/{app}/versions/{version}` | 版本详情 | app-versions.index |
| PUT | `/admin/apps/{app}/versions/{version}` | 更新版本 | app-versions.edit |
| DELETE | `/admin/apps/{app}/versions/{version}` | 删除版本（已发布状态不可删除） | app-versions.delete |
| POST | `/admin/apps/{app}/versions/{version}/publish` | 发布版本（自动将同平台旧已发布版本置为废弃） | app-versions.edit |
| POST | `/admin/apps/{app}/versions/{version}/withdraw` | 撤回版本（仅已发布状态可撤回） | app-versions.edit |
| POST | `/admin/apps/{app}/versions/{version}/obsolete` | 废弃版本 | app-versions.edit |

版本状态流转：`draft` → `published`（publish）→ `withdrawn`（withdraw）/ `obsolete`（obsolete 或被新版本自动替换）。

#### 6.3.20 审计日志（/admin/audit-logs）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/audit-logs` | 审计日志列表（分页，支持时间范围/管理员/方法筛选） | audit-logs.index |
| GET | `/admin/audit-logs/{id}` | 审计日志详情（含请求体、响应） | audit-logs.index |
| GET | `/admin/audit-logs/export` | 导出审计日志（xlsx，支持同步下载/异步入队双模式） | audit-logs.export |

#### 6.3.21 调度任务日志（/admin/schedule-logs）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/schedule-logs` | 调度日志列表（分页，支持任务名/状态/时间范围筛选） | schedule-logs.index |
| GET | `/admin/schedule-logs/{id}` | 调度日志详情 | schedule-logs.index |

#### 6.3.22 数据概览（/admin/dashboard）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/admin/dashboard/stats` | 仪表盘统计数据 | 是 |

#### 6.3.23 监控接口（/admin/monitor，读取 Pulse 数据）
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/monitor/servers` | 服务器性能指标 |
| GET | `/admin/monitor/queues` | 队列负载 |
| GET | `/admin/monitor/reverb/connections` | Reverb 连接数 |
| GET | `/admin/monitor/reverb/messages` | Reverb 消息吞吐 |
| GET | `/admin/monitor/cache` | 缓存命中率 |
| GET | `/admin/monitor/exceptions` | 异常统计 |
| GET | `/admin/monitor/slow-queries` | 慢查询 |
| GET | `/admin/monitor/slow-requests` | 慢请求 |
| GET | `/admin/monitor/slow-jobs` | 慢任务 |
| GET | `/admin/monitor/slow-outgoing-requests` | 慢外部请求 |
| GET | `/admin/monitor/usage` | 使用情况 |

#### 6.3.24 调试接口（/admin/debug，仅 local 环境，读取 Telescope）
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/debug/tags` | 获取标签列表 |
| POST | `/admin/debug/tags` | 监控指定标签 |
| DELETE | `/admin/debug/tags` | 取消监控标签 |
| POST | `/admin/debug/toggle-recording` | 开启/暂停记录 |
| GET | `/admin/debug/entries` | 条目列表（分页） |
| GET | `/admin/debug/entries/{id}` | 条目详情 |
| PUT | `/admin/debug/entries/{id}/resolve` | 标记已解决 |
| DELETE | `/admin/debug/entries` | 删除条目 |

#### 6.3.25 队列管理（/admin/queue，代理 Horizon 数据）
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/queue/stats` | 队列总体统计 |
| GET | `/admin/queue/workload` | 队列工作负载 |
| GET | `/admin/queue/masters` | Master 进程状态 |
| GET | `/admin/queue/monitoring/tags` | 监控标签列表 |
| GET | `/admin/queue/monitoring/jobs` | 监控任务列表 |
| POST | `/admin/queue/monitoring/tags` | 添加监控标签 |
| DELETE | `/admin/queue/monitoring/tags/{tag}` | 移除监控标签 |
| GET | `/admin/queue/metrics/jobs` | 任务吞吐指标 |
| GET | `/admin/queue/metrics/jobs/{id}` | 任务指标详情 |
| GET | `/admin/queue/metrics/queues` | 队列指标 |
| GET | `/admin/queue/metrics/queues/{id}` | 队列指标详情 |
| GET | `/admin/queue/batches` | 批处理列表 |
| GET | `/admin/queue/batches/{id}` | 批处理详情 |
| POST | `/admin/queue/batches/{id}/retry` | 重试失败批次 |
| GET | `/admin/queue/jobs/pending` | 待处理任务 |
| GET | `/admin/queue/jobs/completed` | 已完成任务 |
| GET | `/admin/queue/jobs/silenced` | 静默任务 |
| GET | `/admin/queue/jobs/failed` | 失败任务 |
| GET | `/admin/queue/jobs/failed/{id}` | 失败任务详情 |
| POST | `/admin/queue/jobs/failed/{id}/retry` | 重试失败任务 |
| GET | `/admin/queue/jobs/{id}` | 任务详情 |

#### 6.3.26 AI 聊天（/admin/chat，SSE 流式）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/admin/chat/conversations` | 会话列表 | 是 |
| GET | `/admin/chat/conversations/{id}` | 会话详情（含消息历史） | 是 |
| POST | `/admin/chat` | 发送消息（SSE 流式响应） | 是 |
| POST | `/admin/chat/approve` | 审批工具调用并继续执行 | 是 |
| DELETE | `/admin/chat/conversations/{id}` | 删除会话 | 是 |

SSE 流式响应通过 `text/event-stream` 逐行推送以下事件类型：
- `text`：文本增量
- `reasoning`：思考过程
- `tool_call`：工具调用信息
- `tool_approval_required`：需要人工审批的敏感操作
- `error`：错误
- `done`：流结束

### 6.4 API v1 前台接口（/api/v1）
| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/api/v1/auth/login` | 用户登录（密码/手机验证码/微信/Apple） |
| POST | `/api/v1/auth/register` | 用户注册（手机/邮箱） |
| POST | `/api/v1/auth/logout` | 退出登录 |
| GET | `/api/v1/auth/info` | 当前用户信息 |
| GET | `/api/v1/common/*` | 公共接口（短信/邮件验证码、地区等） |
| POST | `/api/v1/apps/check-update` | App 版本检查更新（传 slug/platform/build，返回是否有更新、是否强制、最新版本信息） |
| GET | `/api/v1/user/*` | 用户个人中心（资料、密码、通知、积分、金币、登录历史等） |

#### 6.4.1 内容互动接口

**点赞（/api/v1/likes）**

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/likes` | 我的点赞列表（分页，可按 `source_type` 过滤） |
| POST | `/api/v1/likes` | 添加点赞 |
| DELETE | `/api/v1/likes/{like}` | 取消点赞 |

**收藏（/api/v1/collections）**

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/collections` | 我的收藏列表（分页，可按 `source_type` 过滤） |
| POST | `/api/v1/collections` | 添加收藏 |
| DELETE | `/api/v1/collections/{collection}` | 取消收藏 |

**评论（/api/v1/comments）**

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/comments` | 评论列表（分页，可按 `source_type` + `source_id` 过滤；公开接口） |
| POST | `/api/v1/comments` | 发布评论（支持 `parent_id` 回复、`mentioned_users` 艾特） |
| DELETE | `/api/v1/comments/{comment}` | 删除评论（仅本人） |

**约定说明**

- 点赞、收藏全部接口需 Sanctum 认证；评论仅 `POST`/`DELETE` 需认证，列表公开
- 写入参数：`source_type`（必填，须为 `config/morph_maps.php` 已注册别名）、`source_id`（必填）、`extra`（可选，JSON）
- 点赞/收藏对同一资源重复提交返回 `422`，由 `Rule::unique` 拦截
- 越权删除他人记录返回 `403`，由 `LikePolicy` / `CollectionPolicy` / `CommentPolicy` 校验
- 响应中的 `user_id` 通过模型 `#[Hidden]` 隐藏
- 列表接口使用 Laravel 标准分页，支持 `page` / `per_page`（1-100，默认 15）

## 7. 附件管理技术方案

### 7.1 设计原则
- **元数据入库、字节流交给 Storage**：`attachments` 表只保存 `disk + path` 等元数据，所有物理读写通过 `Storage::disk($disk)` 完成，不出现任何 `storage_path()` 硬编码，保证 local/public/s3 三种驱动行为一致。
- **复用现有上传链路**：不改动 `UploaderController` 的对外契约，上传落盘成功后由 `AttachmentService` 追加台账写入，返回体增加 `id` 字段。
- **分层职责清晰**：Controller 只做 HTTP 编排，物理文件操作与台账一致性收敛到 `AttachmentService`。
- **失败可回滚**：删除、移动等涉及"数据库 + 物理文件"两段操作以数据库事务包裹，物理操作失败即回滚台账。

### 7.2 代码结构

```
app/
├── Enums/
│   └── AttachmentType.php              # 附件类型枚举（image/video/audio/document/other，实现 HasLabel）
├── Models/System/
│   └── Attachment.php                  # 附件模型（软删除 + morphTo uploader）
├── Services/
│   └── AttachmentService.php           # 台账 + Storage 一致性编排
├── Http/
│   ├── Controllers/Admin/
│   │   └── AttachmentController.php    # 后台附件管理控制器
│   ├── Requests/Admin/Attachment/
│   │   ├── AttachmentIndexRequest.php  # 列表筛选参数校验
│   │   ├── AttachmentRenameRequest.php # 重命名校验
│   │   ├── AttachmentMoveRequest.php   # 移动路径校验
│   │   ├── AttachmentDestroyRequest.php# 批量删除 ids 校验
│   │   └── AttachmentRegisterRequest.php # 直传登记校验
│   └── Resources/Admin/
│       └── AttachmentResource.php      # 附件资源
database/
├── migrations/
│   └── 0001_01_01_000016_create_attachments_table.php
└── factories/System/
    └── AttachmentFactory.php
tests/Feature/Admin/
└── AttachmentControllerTest.php
```

### 7.3 数据表设计要点
- 主键从 `10000000` 起（与其他业务表保持一致风格），带表注释与字段注释。
- 索引：`(uploader_type, uploader_id)` 复合索引、`type` 索引、`hash` 索引、`(disk, path)` 唯一索引（防止同一物理文件重复登记）。
- 使用软删除 `deleted_at`：接口默认执行"硬删除物理文件 + 软删除台账"，保留审计线索。
- `size` 使用 `unsignedBigInteger`，`hash` 使用 `char(32)`（md5）并允许为空（直传场景可能拿不到内容哈希）。

### 7.4 AttachmentService 关键方法

| 方法 | 职责 | Storage 调用 |
|------|------|-------------|
| `record(array $meta): Attachment` | 上传成功后写台账 | 无 |
| `url(Attachment $a): ?string` | 公有盘返回直链 | `Storage::disk()->url()` |
| `temporaryUrl(Attachment $a, int $minutes): string` | 私有盘签名地址；本地盘回退直链 | `Storage::disk()->temporaryUrl()` |
| `download(Attachment $a): StreamedResponse` | 流式下载，保留原始名 | `Storage::disk()->download()` |
| `move(Attachment $a, string $newPath): Attachment` | 同盘移动，事务内先改库再移文件 | `Storage::disk()->move()` |
| `delete(Attachment $a): bool` | 删物理文件后软删台账 | `Storage::disk()->delete()` |
| `deleteMany(array $ids): int` | 按磁盘分组批量删除 | `Storage::disk()->delete(array)` |
| `exists(Attachment $a): bool` | 校验物理文件是否仍存在 | `Storage::disk()->exists()` |

要点：
- `temporaryUrl()` 需捕获驱动不支持的异常（local 驱动在未启用 `serve` 时不支持），降级为 `url()` 或 422 提示。
- `move()` 只允许同磁盘内移动，目标路径必须落在 `uploads/` 前缀下，且先做 `exists()` 冲突检查，避免覆盖既有文件。
- `deleteMany()` 按 `disk` 分组后一次性传数组给 `Storage::disk()->delete()`，减少远端请求次数。
- 物理文件已丢失（`exists() === false`）时删除仍视为成功，只清理台账，避免脏数据无法删除。

### 7.5 上传链路改造
在 `UploadRequest::handleUpload()` 落盘成功后调用 `AttachmentService::record()`，`uploader` 取 `$this->user()`（morphTo 兼容 Admin 与 User 两种守卫）。返回结构在原有字段上追加 `id`。

直传场景（`uploader/token`）文件不经过服务端，改为前端上传成功后调用 `POST /admin/attachments/register` 补登记，服务端用 `Storage::disk()->exists()` 与 `size()` 校验真实存在后再入库，防止伪造记录。

### 7.6 权限与菜单
- 新增权限：`attachments.index`、`attachments.create`、`attachments.edit`、`attachments.delete`，在控制器构造器中以 `permission:` 中间件绑定。
- 新增菜单：`system.attachment`，挂在"系统管理"目录下，含"删除附件""重命名附件""移动附件"按钮权限。
- 通过迁移追加权限与菜单，不修改已执行过的历史迁移。

### 7.7 配置项
沿用现有 `upload.*` 配置（`upload.storage`、`upload.allow_extension` 等），新增：

| 配置键 | 默认值 | 说明 |
|--------|--------|------|
| upload.temporary_url_minutes | 10 | 临时签名 URL 有效期（分钟） |
| upload.prune_orphan_days | 0 | 孤儿附件自动清理天数，0 表示不清理 |

## 8. 非功能性需求

### 8.1 性能要求
- API 响应时间：< 200ms（95% 请求）
- 支持并发用户数：1000+（Octane 模式可进一步提升）
- 数据库查询优化：预加载防止 N+1 查询
- 缓存策略：热点数据使用 Redis 缓存
- 队列异步处理：耗时操作（统计、AI 工具等）走队列

### 8.2 安全要求
- 密码使用 Bcrypt 加密（rounds = 12）
- API 接口使用 Sanctum Token 认证
- 敏感接口使用权限验证（permission/role 中间件）
- SQL 注入防护：使用 Eloquent ORM / 查询构建器
- XSS 防护：API Resource 输出转义
- CSRF 防护：Web 接口启用，`/admin/*` 和 `/api/*` 豁免
- 请求频率限制：登录、验证码等接口设置 throttle
- 操作审计：后台写操作自动记录审计日志
- CSP 内容安全策略
- Telescope 在生产环境默认禁用

### 8.3 可扩展性
- 模块化设计：功能独立封装
- 微服务友好：API 化设计，易于拆分
- 支持水平扩展：无状态设计（Token 认证）
- 队列系统：异步任务处理
- AI Tool 可插拔：新工具只需实现 Tool 契约即可接入

### 8.4 可维护性
- 代码规范：遵循 PSR-12 标准，Laravel Pint 自动格式化
- 类型安全：PHP 8.4 严格类型（`declare(strict_types=1)`），所有方法参数和返回值类型声明
- 注释完整：关键逻辑使用 PHPDoc，复杂处加内联注释
- 测试覆盖：单元测试 + 功能测试，PHPUnit 12 属性语法
- 枚举统一：使用 PHP 8 Enum + HasLabel trait

### 8.5 可靠性
- 错误处理：统一的异常处理机制
- 日志记录：完整的日志记录（Monolog 多通道）
- 数据备份：支持数据库备份
- 故障恢复：队列失败重试、调度任务自动记录失败状态

## 9. 开发流程

### 9.1 环境要求
- PHP >= 8.4
- PHP 扩展：`pdo`、`mbstring`、`openssl`、`redis`、`bcmath`、`ctype`、`json`、`fileinfo`、`tokenizer`、`xml`
- Composer >= 2.x
- MySQL 8.0+ / PostgreSQL / SQLite
- Redis 6.0+
- Node.js >= 20.x（前端资源构建）
- 推荐使用 [Laravel Herd](https://herd.laravel.com/) 作为本地开发环境

### 9.2 快速开始

```bash
# 一键安装（推荐）
composer run setup

# 启动开发环境
composer run dev
```

手动步骤：

```bash
composer install
cp .env.example .env
php artisan key:generate
# 配置 .env 数据库连接后
php artisan migrate
php artisan storage:link
npm install
npm run build
```

本地访问地址：`http://laravel-skel-forge.test`（Herd 自动提供）。

### 9.3 开发命令

| 命令 | 说明 |
|------|------|
| `composer run setup` | 一键安装（依赖、密钥、迁移、存储链接、前端构建） |
| `composer run dev` | 启动开发环境（server/queue/pail/vite） |
| `composer run test` | 运行全部测试 |
| `composer run check-style` | 检查代码风格（Pint） |
| `composer run fix-style` | 修复代码风格（Pint） |
| `php artisan route:list` | 查看路由列表 |
| `php artisan route:list --path=admin` | 查看后台路由 |
| `php artisan horizon` | 启动队列服务（生产用 Supervisor 守护） |
| `php artisan pail` | 查看实时日志 |
| `php artisan schedule:work` | 本地运行调度器 |

### 9.4 代码规范
- 遵循 PSR-12 编码标准
- 使用 PHP 8.4 特性：构造器属性提升、命名参数、枚举、match 表达式
- 所有 PHP 文件强制 `declare(strict_types=1)` 和文件头 License 注释
- 所有方法添加参数类型声明和返回类型声明
- 使用 PHPUnit 12 新属性语法（`#[Test]`、`#[CoversClass]`、`#[Group]`、`#[TestDox]`）
- 修改 PHP 文件后必须运行 `vendor/bin/pint --dirty --format agent`
- 使用 `php artisan make:` 命令创建文件，传递 `--no-interaction` 参数
- AI Agent 使用 `php artisan make:agent` / `php artisan make:tool`（自定义 stub 在 stubs/）

### 9.5 全局辅助函数（bootstrap/helpers.php）
| 函数 | 说明 |
|------|------|
| `cpu_count(): int` | 获取 CPU 核心数 |
| `per_page(Request $request, int $limit = 15): int` | 获取分页大小（限制 1~100） |
| `mobile_replace(?string $value): string` | 手机号脱敏 |
| `settings(string $key, $default): mixed` | 快捷读取系统设置 |
| `validation_exception($field, $message): void` | 快速抛出表单验证异常 |

## 10. 部署方案

### 10.1 生产环境配置

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_CLIENT=phpredis

BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=s3
```

优化命令：
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
```

### 10.2 部署方式

| 方式 | 说明 |
|------|------|
| Laravel Cloud（推荐） | 官方部署平台，自动化部署和托管 |
| Octane + Swoole/RoadRunner | 高性能常驻内存模式 |
| Nginx + PHP-FPM | 传统部署方式 |

### 10.3 服务配置

| 服务 | 建议配置 |
|------|----------|
| Horizon | Supervisor 守护，`php artisan horizon` |
| Reverb | 独立进程或 Supervisor 守护，`php artisan reverb:start` |
| Scheduler | Cron 每分钟执行 `php artisan schedule:run` |
| Pulse | 访问控制保护（Gate 鉴权） |
| Telescope | 生产环境禁用或仅限管理员访问 |

### 10.4 备份策略
- 数据库每日备份
- 重要配置备份
- 上传文件备份（如使用 S3 可开启版本控制）
- 备份保留 30 天

## 11. 监控与维护

### 11.1 监控工具
| 工具 | 用途 | 访问路径 |
|------|------|----------|
| Laravel Pulse | 应用性能监控 | `/pulse` |
| Laravel Horizon | 队列监控 | `/horizon` |
| Laravel Telescope | 调试辅助 | `/telescope`（local 环境） |
| Laravel Reverb | WebSocket 统计 | `/api/laravel-reverb` 或后台 Monitor |
| Laravel Pail | 实时日志 | 命令行 `php artisan pail` |

### 11.2 日常维护
- 定期清理过期验证码（`auth:clear-resets`、phone_codes/mail_codes 自动过期）
- 定期清理 Telescope 记录（`telescope:prune --hours=48`）
- 定期清理审计日志和调度日志（通过 `model:prune` 按配置保留天数）
- 数据库优化（索引维护、慢查询优化）
- 依赖安全更新检查（`composer audit`）

## 12. 定时任务

在 `routes/console.php` 中定义：

| 任务 | 频率 | 说明 |
|------|------|------|
| `auth:clear-resets` | 每日 01:00 | 清理过期密码重置令牌 |
| `model:prune` | 每日 01:05 | 清理已标记删除的模型（含调度日志过期清理） |
| `stats:user` | 每日 01:10 | 统计昨日用户数据 |
| `telescope:prune --hours=48 --keep-exceptions` | 每日 01:15 | 清理 Telescope 记录 |
| `horizon:snapshot` | 每 5 分钟 | 记录应用状态快照 |

调度任务执行情况自动记录到 `schedule_logs` 表，可在后台"监控管理-调度日志"查看。

## 13. 待建设需求（Roadmap）

> 本章记录作为"系统应用起点"仍需补齐的能力清单。优先级定义：
> **P0** = 上线前必备（缺失将阻塞生产发布）；**P1** = 强烈建议（影响运营闭环）；
> **P2** = 典型业务扩展（按业务形态选做）；**P3** = 体验与性能优化。

### 13.1 P0 上线前必备

#### 13.1.1 Banner / 轮播图管理
| 项目 | 说明 |
|------|------|
| 业务价值 | 启动页、首页轮播是 App/H5 的标准运营位，是最高频的运营配置需求 |
| 现状 | **已完成**（见 13.5 变更记录 2026-08-27）。后端 7 个管理端点 + 1 个前台公开端点全部交付：CRUD、启用/禁用切换、拖拽排序（同广告位内上移/下移）、按广告位/关键词/状态筛选、生效时间区间控制；前台公开接口自动过滤禁用/未开始/已过期 Banner。前端 `views/operation/banner/` 完整页面（列表+搜索+编辑弹窗），Apifox 8 个接口全部按 Admin/Api 模块规范发布并通过 PHPUnit 38 个用例验证 |
| 验收标准 | ✅ 广告位可配置（基于字典 banner_positions，无需改代码即可扩展）；✅ 过期/未开始的 Banner 前台不可见；✅ 排序可移动并保存（上移/下移方式）；✅ 字段安全：前台接口仅返回 6 个公开字段；✅ 跨平台字段安全：Admin 端用 EloquentPaginate `x-apifox-refs` 引用 #/definitions/304922372，Api 端独立 PublicBanner Schema #/definitions/308708084，零跨模块引用 |

#### 13.1.2 管理员登录日志
| 项目 | 说明 |
|------|------|
| 业务价值 | 安全合规必备。已有用户侧登录历史，管理员侧缺失审计能力 |
| 现状 | **已完成**（见 13.5 变更记录 2026-08-27）。管理员与前台用户复用多态表 `login_histories`，`result` 字段区分成功/失败；单管理员视角 `GET /admin/admins/{id}/login-histories`、单用户视角 `GET /admin/users/{user}/login-histories` 均支持 `result` 过滤；全局视角 `GET /admin/login-histories`（跨管理员与前台用户，支持按 guard、用户ID、IP、结果、关键字、时间范围过滤）已提供 |
| 前端页面 | `views/monitor/login-history/index.vue`，展示登录时间、归属主体、IP、归属地、设备、浏览器、结果（成功/失败） |
| 验收标准 | ~~登录成功与失败均落库~~（已完成）；~~可按管理员和时间范围检索~~（已完成）；~~全局列表可跨主体检索~~（已完成，前端页面 `views/monitor/login-history/index.vue` 已实现），超级管理员可见全部 |

#### 13.1.3 系统公告 / 站内信群发
| 项目 | 说明 |
|------|------|
| 业务价值 | 当前通知仅由业务事件被动触发，缺少主动运营触达能力 |
| 数据表 | `announcements`：id、title、content、type、target_type（全体/指定用户/用户标签）、target_value、publish_at、status |
| 后台接口 | 公告 CRUD、发布/撤回、发送范围预览、发送进度查询 |
| 实现要点 | 群发通过队列分批投递（避免大批量同步写入）；复用现有 `notifications` 通知体系 |
| 前台接口 | `GET /api/v1/announcements` 公告列表、`GET /api/v1/announcements/{id}` 详情 |
| 前端页面 | `views/operation/announcement/index.vue`，富文本编辑（复用 ArtWangEditor）、发送范围选择 |
| 验收标准 | 支持定时发布；群发通过队列异步执行；用户端可查看未读公告数 |

#### 13.1.4 敏感词过滤
| 项目 | 说明 |
|------|------|
| 业务价值 | UGC 内容（评论、反馈、昵称）合规底线要求 |
| 数据表 | `sensitive_words`：id、word、category、level（警告/拦截）、status |
| 实现要点 | 提供 `SensitiveWordService`，支持 DFA 算法匹配；结果缓存到 Redis；命中拦截级直接返回 422，命中警告级自动置为待审核 |
| 接入点 | 评论发布、反馈提交、用户昵称/简介修改 |
| 后台接口 | 敏感词 CRUD、批量导入（TXT/CSV）、缓存刷新 |
| 前端页面 | `views/system/sensitive-word/index.vue`，支持分类管理与批量导入 |
| 验收标准 | 命中拦截级内容无法提交；命中警告级自动进入待审核队列；词库变更后缓存自动刷新 |

#### 13.1.5 列表数据导出
| 项目 | 说明 |
|------|------|
| 业务价值 | 运营/财务对账的刚性需求；前端已有 `ArtExcelExport` 组件，后端接口层已补齐 |
| 现状 | **已完成**（后端 + 前端，见 13.5 变更记录 2026-08-27）。后端：`maatwebsite/excel` 4.0.2 已集成，发布 `config/excel.php`；新增 `App\Support\Export\AbstractExport` 基类（实现 `FromQuery`/`WithHeadings`/`WithMapping`/`WithTitle`/`WithColumnFormatting`，自动追加主键避免 chunk 漏行）与 `HandlesExports` 共享 trait；5 个 Export 类（用户/评论/反馈/审计日志/用户积分/金币流水），所有 .xlsx 导出；同步/异步双通道：≤ `SYNC_THRESHOLD`（用户/评论/反馈/审计日志 1000 行、流水 2000 行）走 `BinaryFileResponse` 直接下载（`Content-Disposition: attachment; filename*=UTF-8''...`），超阈值走 `PerformExportJob` 队列生成文件并通过 `ExportCompletedNotification` 推送站内信下载链接，失败推 `ExportFailedNotification`；控制器与路由：用户 `users/export`、`users/{user}/point-trades/export` 与 `coin-trades/export`、评论 `comments/export`、反馈 `feedbacks/export`、审计日志 `audit-logs/export`（共 6 个端点）；权限点：`users.export`/`comments.export`/`feedbacks.export`/`audit-logs.export`。前端（laravel-skel-admin）：HTTP 层 `request()` 新增 `returnRawResponse` 选项（返回完整 AxiosResponse，保留 `Content-Disposition` 响应头用于读文件名）；新增 `src/utils/file/download.ts` 提供 `parseFilenameFromDisposition()`（RFC 5987 UTF-8 文件名解析 + plain 回退）与 `downloadBlob()`（Blob → ObjectURL → `<a download>` 下载链路）；新增 `src/hooks/core/useExport.ts` 通用导出 Hook，封装「全屏 loading → blob 请求 → 按 status/contentType 分流处理」：200 + xlsx → 解析文件名并触发下载；202 + JSON → 读取 Blob 为 JSON 并 `ElMessage.success` 提示「导出任务已提交，完成后将通过站内信通知您」；200 + JSON → 警告提示；新增 6 个 `fetchExport*` API 函数（`responseType: 'blob'` + `returnRawResponse: true` + `showErrorMessage: true`）；`Api.Common.ExportTaskResponse` 类型入 `api.d.ts`；4 个列表页（用户/评论/反馈/审计日志）在 `ArtTableHeader` #left 插槽新增「导出」按钮（带 `v-auth` 权限指令、`isExporting` loading 状态），复用 `useTable.searchParams` 自动携带当前筛选条件（剔除 `page`/`per_page`）；用户详情弹窗（`user-detail-dialog.vue`）积分/金币两个 Tab 的筛选栏各加一个独立 loading 的「导出」按钮，复用各自的 `keyword` + `dateRange` 状态作为筛选参数；ESLint / vue-tsc / vite 生产构建均通过 |
| 实现要点 | 统一导出基类，接收当前列表筛选条件；大数据量走队列异步生成后推送下载链接（结合通知）；前端统一走 `useExport()` Hook，同步下载/异步通知自动分流 |
| 覆盖模块 | 用户列表、评论管理、反馈管理、审计日志、用户详情内积分流水/金币流水 |
| 前端交互 | 导出按钮挂载在表格工具栏左侧；点击后全屏 loading 遮罩防止重复点击；同步场景自动触发浏览器下载，文件名形如 `users_20260827_153045.xlsx`；异步场景弹出成功提示，用户稍后在站内信中心下载；导出参数自动与当前表格搜索/筛选条件联动 |
| 验收标准 | ✅ 导出结果与当前筛选条件一致；✅ 超过阈值走异步并通过站内信推送下载链接；✅ 中文文件名正确解析（RFC 5987）；✅ 导出按钮受权限指令控制；✅ ESLint / vue-tsc / 生产构建零错误 |

#### 13.1.6 安全基线加固
| 项目 | 说明 |
|------|------|
| CORS 配置 | 确认 `config/cors.php` 生产环境白名单收敛（不使用 `*`） |
| 接口限流 | ~~登录~~（已完成：`/api/v1` 侧 `user.login_throttle`、`/admin` 侧 `admin.login_throttle`）、注册、验证码发送、密码重置等敏感接口配置独立 `throttle` 规则 |
| 安全响应头 | 补充 `X-Frame-Options`、`X-Content-Type-Options`、`Referrer-Policy`、CSP |
| 验收标准 | 生产环境安全扫描无高危项；敏感接口具备防爆破限流 |

#### 13.1.7 国际化落地
| 项目 | 说明 |
|------|------|
| 现状 | 后端有 `lang/` 目录，前端已安装 `vue-i18n` 但未实际落地语言包 |
| 后端 | 校验消息、枚举 label、异常提示统一走 `__()`；根据 `Accept-Language` 切换 |
| 前端 | 补齐 `src/locales/` 中英文语言包；页面文案全部走 `$t()` |
| 验收标准 | 切换语言后界面与接口提示同步切换；无硬编码中文残留 |

### 13.2 P1 强烈建议

#### 13.2.1 内容 / 帖子管理
| 项目 | 说明 |
|------|------|
| 业务价值 | 评论模块已按多态（morphTo）设计，但缺少"内容主体"。补齐后评论体系才形成闭环 |
| 数据表 | `posts`：id、user_id、title、content、images、category_id、status（审核状态）、view_count、like_count、comment_count、is_top、published_at |
| 后台接口 | 内容 CRUD、审核（复用 `ReviewStatus` 枚举）、置顶、推荐、批量操作 |
| 前台接口 | 内容列表/详情/发布/删除，复用现有点赞、收藏、评论多态能力 |
| 验收标准 | 内容可作为 `source_type` 被评论、点赞、收藏正确关联；审核流程与评论一致 |

#### 13.2.2 举报管理
| 项目 | 说明 |
|------|------|
| 业务价值 | UGC 平台合规必备，与内容/评论管理配套 |
| 数据表 | `reports`：id、user_id、source_type、source_id（多态）、reason_type、reason、images、status、admin_id、handle_remark、handled_at |
| 后台接口 | 举报列表（按状态/类型过滤）、处理（举报成立→联动下架内容/封禁用户 / 举报不成立→驳回） |
| 前台接口 | `POST /api/v1/reports` 提交举报 |
| 前端页面 | `views/operation/report/index.vue`，处理弹窗内可直接跳转查看被举报内容 |
| 验收标准 | 举报成立后自动联动处置被举报对象；同一用户对同一对象不可重复举报 |

#### 13.2.3 消息模板管理
| 项目 | 说明 |
|------|------|
| 业务价值 | 当前短信/邮件仅有固定验证码模板，业务通知（注册欢迎、审核结果、反馈回复）需可视化维护 |
| 数据表 | `message_templates`：id、code、name、channel（sms/mail/notification）、title、content、variables、status |
| 实现要点 | 模板变量以 `{{name}}` 占位；提供 `MessageTemplateService::render(code, vars)` 统一渲染 |
| 前端页面 | `views/system/message-template/index.vue`，支持变量说明、内容预览 |
| 验收标准 | 模板变量渲染正确；缺失变量有兜底；模板停用后走默认文案不报错 |

#### 13.2.4 业务通知联动
| 项目 | 说明 |
|------|------|
| 业务价值 | 当前评论审核、反馈处理完成后未通知用户，运营闭环断裂 |
| 覆盖场景 | 评论审核通过/驳回 → 通知作者；反馈处理完成 → 通知提交人；举报处理完成 → 通知举报人；VIP 到期前 → 提醒续费 |
| 实现要点 | 通过模型事件/领域事件触发，走队列异步发送，复用消息模板 |
| 验收标准 | 各场景通知准确送达；通知内容可通过模板配置；不阻塞主流程 |

#### 13.2.5 用户标签与分群
| 项目 | 说明 |
|------|------|
| 业务价值 | 精细化运营基础，是公告群发指定范围、定向推送的前置能力 |
| 数据表 | `tags`（标签定义）+ `taggables`（多态关联，可复用于用户/内容） |
| 后台接口 | 标签 CRUD、为用户批量打标/移除标签、按标签筛选用户 |
| 实现要点 | 支持手动标签与规则自动标签（如"7日未登录"、"VIP用户"），自动标签由定时任务刷新 |
| 验收标准 | 用户列表可按标签筛选；公告群发可选择标签作为目标范围 |

#### 13.2.6 敏感操作二次验证
| 项目 | 说明 |
|------|------|
| 业务价值 | 高风险操作防误操作与防越权 |
| 覆盖操作 | 删除用户、彻底删除、积分/金币余额调整、角色权限变更、清理缓存 |
| 实现要点 | 中间件 `confirm.password`，校验管理员在 N 分钟内已完成密码确认；前端统一确认弹窗组件 |
| 验收标准 | 未确认密码时高风险接口返回 423；确认后在有效期内不重复要求 |

#### 13.2.7 素材管理增强
| 项目 | 说明 |
|------|------|
| 现状 | 附件管理已支持中转/直传、重命名、移动、删除 |
| 待补 | 素材分组/分类目录树、图片缩略图自动生成、批量删除/批量移动、按类型与尺寸筛选、引用计数（防误删被使用的素材） |
| 验收标准 | 支持目录树管理；被引用的素材删除时给出提示；列表加载缩略图而非原图 |

#### 13.2.8 业务数据看板
| 项目 | 说明 |
|------|------|
| 现状 | Dashboard 仅有用户数/登录数等基础卡片与趋势图 |
| 待补指标 | DAU/MAU、次日/7日/30日留存、内容与评论发布量趋势、评论审核通过率/驳回率、反馈处理时效（平均处理时长）、活跃用户排行、积分/金币发放与消耗对比 |
| 实现要点 | 指标预聚合到统计表（扩展现有 `stats:user` 定时任务），看板只读聚合表，避免实时全表扫描 |
| 验收标准 | 看板首屏加载 < 1s；支持时间范围切换；指标口径在文档中明确定义 |

#### 13.2.9 业务定时任务管理
| 项目 | 说明 |
|------|------|
| 现状 | 已有调度日志（只读），但任务本身在代码中硬编码，无法运营侧调整 |
| 待补 | 任务配置表（任务名、Cron 表达式、参数、启用状态）、手动立即执行、暂停/恢复 |
| 边界 | 系统级任务仍保留在 `routes/console.php`；本模块面向业务可配置任务 |
| 验收标准 | Cron 表达式变更后无需发版即生效；手动执行结果可在调度日志查看 |

### 13.3 P2 业务扩展（按业务形态选做）

| 需求 | 说明 |
|------|------|
| 优惠券 / 活动管理 | 积分体系已就绪，缺营销工具：优惠券模板、领取记录、核销、活动页配置 |
| 工单 / 客服系统 | 现有反馈模块偏轻量，若需客服能力则需补：工单流转、分配、SLA 计时、多轮会话 |
| 支付与订单 | 若涉及交易场景，需补：订单、支付渠道（微信/支付宝/苹果内购）、退款、对账 |
| 签到与任务体系 | 配合积分体系：每日签到、连续签到奖励、成长任务、任务奖励发放 |
| 邀请与分销 | 邀请码、邀请关系树、邀请奖励结算 |
| 地区数据校验 | 核对地区表是否符合最新国家标准，并与收货地址接口打通联动 |
| 前台用户端 | 目前仅有管理后台，若产品需要则补 H5 / 小程序 / App 用户端 |

### 13.4 P3 体验与性能优化

| 需求 | 说明 |
|------|------|
| 批量操作 | 各列表页目前仅支持单条操作，需补批量审核、批量删除、批量打标签 |
| 暗黑模式完整度 | 框架已支持，但抽屉、详情弹窗、图表等场景需逐页检查暗色适配 |
| 前端错误边界 | 已有路由级 403/404/500，缺组件级错误捕获与降级渲染 |
| 大列表性能 | 审计日志、Telescope 条目等大数据量列表需虚拟滚动 / 图片懒加载 |
| 单元测试覆盖 | 持续补齐至 70%+ 覆盖率（项目规范要求） |
| API 文档同步 | 新增接口同步发布至 Apifox，保持文档与代码一致 |
| 性能基准测试 | 建立关键接口的基准测试与压测报告，为容量规划提供依据 |

### 13.5 已完成事项

记录已从待办清单中交付的能力，便于追踪进展。

| 完成时间 | 事项 | 交付内容 |
|----------|------|----------|
| 2026-08-26 | 评论管理（运营） | 后端 `CommentController`（列表/详情/审核/删除）+ `CommentResource` + `CommentReviewRequest`；支持按审核状态、源类型、关键词、用户过滤；权限点 `comments.index/edit/delete`；菜单挂在「运营管理」下。前端 `views/operation/comment/`（列表页 + 搜索栏 + 详情抽屉 + 审核弹窗） |
| 2026-08-26 | 公共选项接口 | 新增 `CommonController`（路由前缀 `/admin/common`），提供 `GET /admin/common/source-types` 基于 morph map 输出源类型选项；前端源类型下拉改为接口动态加载，替代硬编码映射 |
| 2026-08-26 | 评论回复链展示 | 后端详情接口返回 `is_reply` 与 `parent_comment` 上级评论摘要（`morphWith` 嵌套预加载避免 N+1），新增 `GET /admin/comments/{comment}/replies` 分页回复列表；前端详情抽屉新增上级评论引用块与回复列表卡片，支持链内无限层级下钻与分页 |
| 2026-08-27 | 后台登录失败记录与限流 | 新增 `App\Events\Admin\LoginFailed` 事件与 `LoginFailedListener`，在 `PasswordLoginRequest::authenticate()` 的「密码错误」「账号被禁用」两条失败路径派发，写入 `login_histories` 且 `result=false`（账号不存在的尝试无法归因到 user_id，不落库）；修复 `LoginHistory` 模型 `created` 钩子——失败记录不再累加 `login_count`/`last_login_at`，也不再误触发 `TodayFirstLogged`，`isTodayLogged()` 仅统计成功记录；`result` 补 boolean cast 并在两个 `LoginHistoryResource` 中输出；`GET /admin/admins/{id}/login-histories` 与 `GET /admin/users/{user}/login-histories` 新增 `result` 查询参数，AI 审计工具 `ListLoginHistories` 同步支持；仪表盘 4 处登录统计（`range_logins`、`today_logins`、`login_trend`、`recent_logins`）补 `result=true` 过滤避免被失败记录污染；新增配置项 `admin.login_throttle`（默认 `10,3`）与 `AuthController` 登录限流中间件，超限返回 429 |
| 2026-08-27 | 全局登录历史接口 | 新增 `GET /admin/login-histories` 跨主体全局登录日志列表（权限 `login-histories.index`），复用 `LoginHistory` 多态表，支持按 guard（user/admin）、user_id、IP、结果、关键字、时间范围过滤，新增 `LoginHistory::scopeFilter` 统一筛选；响应新增 `GlobalLoginHistoryResource`（含 guard、user_id、user_name、result）；后台菜单「登录日志」合并至 `2026_07_31_111934_seed_initial_admin_data.php` |
| 2026-08-27 | 全局登录历史前端 | 新增 `src/views/monitor/login-history/`（index.vue + login-history-search.vue + login-history-detail-dialog.vue），复用 useTable + ArtSearchBar 模式；`src/types/api/api.d.ts` 新增 `Api.Monitor.LoginHistoryItem/LoginHistoryList/LoginHistorySearchParams`；`src/api/monitor.ts` 新增 `fetchLoginHistoryList` |
| 2026-08-27 | 列表数据导出接口发布至 Apifox | 6 个导出端点（用户、用户积分流水、用户金币流水、评论、反馈、审计日志）按 Apifox 规范全部发布到 Admin 模块（Apifox 端点 ID 分别为 507519423/507524413/507524424/507524428/507527307/507527357）；分页响应统一使用 `x-apifox-refs` 引用 Admin 模块 `EloquentPaginate`（#/definitions/304922372）并覆写 `data` 字段，接口模块归属严格按 `/admin` 前缀归入 Admin 模块，未跨模块引用 Schema |
| 2026-08-27 | 前端导出能力接入（laravel-skel-admin） | HTTP 层 `request()` 新增 `returnRawResponse` 选项（保留完整 AxiosResponse 与 headers）；新增 `src/utils/file/download.ts`（`parseFilenameFromDisposition` RFC 5987 文件名解析 + `downloadBlob` Blob 下载工具）；新增 `src/hooks/core/useExport.ts` 通用导出 Hook（全屏 loading + 200/xlsx 同步下载 + 202/JSON 异步入队提示双通道自动分流）；`src/api/` 下 4 个 API 文件新增 6 个 `fetchExport*` 方法（`responseType: 'blob'` + `returnRawResponse: true`）；`Api.Common.ExportTaskResponse` 类型入库；用户/评论/反馈/审计日志 4 个列表页在 `ArtTableHeader` #left 挂载「导出」按钮（v-auth 权限控制 + isExporting loading + 自动携带当前 searchParams 筛选条件），用户详情弹窗积分/金币两个 Tab 各加一个独立 loading 的导出按钮；ESLint / vue-tsc / vite 生产构建零错误 |
| 2026-08-27 | 登录日志操作列 UI 统一 | `views/monitor/login-history/index.vue` 操作列由原 `ElLink` 文字「查看」改为与附件管理一致的 `ArtButtonTable` 圆形图标按钮（`type: 'view'`，眼睛图标），列宽由 100 收窄到 80，保持全后台操作列视觉风格统一 |
| 2026-08-27 | Banner / 轮播图管理 | 后端：`App\Enums\LinkType`（链接类型枚举 none/url/page/goods/webview）；`App\Models\Operation\Banner` + `BannerFactory`（带 disabled/noLink/withDuration 状态）；迁移 `2026_03_27_120000_create_banners_table.php` 创建表（id 从 10000000 起，含 position/title/image/link_type/link_value/sort/status/start_at/end_at 字段）并预置 `banner_positions` 字典（home_top/home_banner/launch/user_center/discover 5 个广告位）；`App\Http\Controllers\Admin\BannerController`（index/store/show/update/destroy + toggleStatus + sort 共 7 个端点，index 接口按广告位/关键词/状态筛选，sort 接口按 ids 数组顺序回写 sort 字段，仅修改传入的 ID 不影响其他 Banner），3 个 FormRequest（BannerIndexRequest/BannerSaveRequest/BannerSortRequest），`BannerResource`（含 position_label/link_type_label）按 `banner_positions` 字典缓存映射；菜单与权限点 `banners.index/create/edit/delete` 合并至 `2026_07_31_111934_seed_initial_admin_data.php` 运营管理目录下（评论、反馈之后排序 3）；前台 `App\Http\Controllers\Api\V1\BannerController::index` 公开接口（`GET /api/v1/banners?position=home_top`），自动过滤 status=false、start_at 未到、end_at 已过的 Banner，按 sort 升序 + id 倒序返回，`BannerPublicResource` 仅返回 6 个公开字段（id/title/image/link_type/link_value/sort）保护后台字段；7 个 Admin 接口 + 1 个 Api 接口全部按 Apifox 规范发布（Admin 模块用 `EloquentPaginate` #/definitions/304922372 + 覆写 data 字段，Api 模块独立定义 `PublicBanner` Schema #/definitions/308708084，跨模块零共享 Schema）；前端 `src/views/operation/banner/`（index.vue + modules/banner-search.vue + modules/banner-edit-dialog.vue），用 `useTable` 模式 + `ArtTableHeader` + `ArtTable` + 状态列 `ElSwitch` 直接切换 + 操作列「上移/下移」按钮（同广告位内交换 sort 后批量回写，替代第三方拖拽库）、新增/编辑弹窗含图片预览、链接类型联动显示「链接值」输入框、生效时间区间选择器；`src/api/operation-manage.ts` 7 个 fetch 方法 + `src/types/api/api.d.ts` 新增 `Api.OperationManage` 命名空间（BannerList/BannerListItem/BannerSearchParams/BannerSaveParams/BannerSortParams/BannerPositionOption 6 个类型）；ESLint / vue-tsc / vite 生产构建零错误；PHPUnit 测试 38 个用例全通过（Admin 24 + Api 14，共 115 个断言，1.017s），覆盖 CRUD、筛选、状态切换、拖拽排序、字段安全、生效时间过滤等场景 |

### 13.6 建议实施顺序

**第一阶段（上线必备最小集）**
1. Banner / 轮播图管理（13.1.1）
2. ~~管理员登录日志（13.1.2）~~ ✅ 2026-08-27
3. 系统公告 / 站内信群发（13.1.3）
4. 敏感词过滤（13.1.4）
5. ~~列表数据导出（13.1.5）~~ ✅ 2026-08-27
6. 安全基线加固（13.1.6）— 登录限流已完成；验证码与密码重置限流、CORS 白名单、安全响应头仍待补齐

**第二阶段（业务闭环）**
7. 内容 / 帖子管理（13.2.1）
8. 举报管理（13.2.2）
9. 消息模板管理（13.2.3）+ 业务通知联动（13.2.4）
10. 业务数据看板（13.2.8）
11. 敏感操作二次验证（13.2.6）

**第三阶段（精细化运营）**
12. 用户标签与分群（13.2.5）
13. 素材管理增强（13.2.7）
14. 业务定时任务管理（13.2.9）
15. 国际化落地（13.1.7）

**第四阶段（按业务形态扩展）**
16. P2 业务扩展模块按实际产品形态选做
17. P3 体验与性能优化持续迭代

### 13.7 技术演进方向（长期）

- [ ] 更多认证方式（OAuth2 Server、Socialite 多平台社交登录）
- [ ] GraphQL 支持（与现有 RESTful 并行）
- [ ] AI 工具扩展（更多业务场景的 Function Calling）
- [ ] 微服务架构支持（服务拆分与服务间通信规范）
- [ ] 多云部署支持
- [ ] 可视化代码生成工具（CRUD 模块一键生成前后端代码）

## 14. 附录

### 14.1 默认账号
- 超级管理员：用户名 `admin`，密码 `12345678`（由迁移 `2026_07_31_111934_seed_initial_admin_data.php` 创建）

### 14.2 相关文档
- Laravel 官方文档：https://laravel.com/docs
- Laravel Skel Forge README.md
- 前端项目：laravel-skel-admin（Vue 3 SPA 管理后台）

### 14.3 联系方式
- 作者：Tongle Xu <xutongle@msn.com>

---

**文档版本**：v2.5
**最后更新**：2026-08-27
**维护人员**：Tongle Xu
