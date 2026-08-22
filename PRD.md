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
| 短信验证码记录 | 查看短信验证码发送记录（只读） |
| 邮件验证码记录 | 查看邮件验证码发送记录（只读） |
| 上传服务 | 文件/图片/视频/APK 上传接口，生成直传 Token（兼容 S3 协议云存储） |
| 通知管理 | 站内通知列表、未读数、标记已读、批量已读、清理已读 |

#### 3.3.3 附件管理（上传文件管理器）
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

#### 3.3.4 监控面板
| 功能 | 说明 |
|------|------|
| 服务器性能 | 读取 Laravel Pulse 数据（CPU、内存、请求量） |
| 队列监控 | 队列负载、慢任务统计 |
| Reverb 监控 | WebSocket 连接数、消息吞吐 |
| 缓存监控 | 缓存命中率统计 |
| 异常监控 | 应用异常聚合统计 |
| 慢查询/慢请求/慢任务 | 数据库慢查询、慢 HTTP 请求、慢队列任务 |
| 外部请求监控 | 慢外部 HTTP 请求 |

#### 3.3.5 调试面板（Telescope，仅 local 环境）
| 功能 | 说明 |
|------|------|
| 标签管理 | 查询/监控/取消监控标签 |
| 记录开关 | 开启/暂停 Telescope 记录 |
| 条目查询 | 分页查询 Telescope 条目、查看详情、标记解决、删除条目 |

#### 3.3.6 队列管理（Horizon）
| 功能 | 说明 |
|------|------|
| 队列状态 | 队列统计、负载、Master/Supervisor 进程状态 |
| 标签监控 | 监控/取消监控指定标签 |
| 任务指标 | Jobs/Queues 吞吐指标和详情 |
| 批处理 | 批处理列表、详情、重试失败批次 |
| 任务列表 | 待处理/已完成/失败/静默任务列表和详情，重试失败任务 |

#### 3.3.7 数据概览
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

#### 5.1.17 其他核心表
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
| 200 | 成功（查询/更新） |
| 201 | 创建成功 |
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

#### 6.3.12 验证码管理
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/phone-codes` | 短信验证码列表 | phone-codes.index |
| GET | `/admin/phone-codes/{id}` | 短信验证码详情 | phone-codes.index |
| GET | `/admin/mail-codes` | 邮件验证码列表 | mail-codes.index |
| GET | `/admin/mail-codes/{id}` | 邮件验证码详情 | mail-codes.index |

#### 6.3.13 上传管理（/admin/uploader）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/admin/uploader/file` | 上传通用文件 | 是 |
| POST | `/admin/uploader/image` | 上传图片 | 是 |
| POST | `/admin/uploader/video` | 上传视频 | 是 |
| POST | `/admin/uploader/apk` | 上传 APK 安装包 | 是 |
| POST | `/admin/uploader/token` | 获取直传 Token（S3 兼容） | 是 |

上传成功后返回字段包含附件 `id`，前端可直接用于附件管理。

#### 6.3.14 附件管理（/admin/attachments）
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

#### 6.3.15 应用管理（/admin/apps）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/apps` | 应用列表（分页，支持关键字/状态/日期筛选，预加载各平台最新版本） | apps.index |
| POST | `/admin/apps` | 创建应用 | apps.create |
| GET | `/admin/apps/{id}` | 应用详情 | apps.index |
| PUT | `/admin/apps/{id}` | 更新应用 | apps.edit |
| DELETE | `/admin/apps/{id}` | 删除应用（软删除） | apps.delete |

应用资源字段包含 `latest_versions`（各平台最多 3 条已发布版本，按 build 倒序）。

#### 6.3.16 应用版本管理（/admin/apps/{app}/versions）
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

#### 6.3.17 审计日志（/admin/audit-logs）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/audit-logs` | 审计日志列表（分页，支持时间范围/管理员/方法筛选） | audit-logs.index |
| GET | `/admin/audit-logs/{id}` | 审计日志详情（含请求体、响应） | audit-logs.index |

#### 6.3.18 调度任务日志（/admin/schedule-logs）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/schedule-logs` | 调度日志列表（分页，支持任务名/状态/时间范围筛选） | schedule-logs.index |
| GET | `/admin/schedule-logs/{id}` | 调度日志详情 | schedule-logs.index |

#### 6.3.19 数据概览（/admin/dashboard）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/admin/dashboard/stats` | 仪表盘统计数据 | 是 |

#### 6.3.20 监控接口（/admin/monitor，读取 Pulse 数据）
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

#### 6.3.21 调试接口（/admin/debug，仅 local 环境，读取 Telescope）
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

#### 6.3.22 队列管理（/admin/queue，代理 Horizon 数据）
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

#### 6.3.23 AI 聊天（/admin/chat，SSE 流式）
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

## 13. 未来规划

### 13.1 短期规划（1-3个月）
- [ ] 完善单元测试覆盖（目标 70%+）
- [ ] 完善 API 文档
- [ ] 性能优化和基准测试

### 13.2 中期规划（3-6个月）
- [ ] 添加更多认证方式（OAuth、Socialite 社交登录）
- [ ] 完善多语言支持
- [ ] 添加 GraphQL 支持
- [ ] AI 工具扩展（更多业务场景）

### 13.3 长期规划（6-12个月）
- [ ] 微服务架构支持
- [ ] 多云部署支持
- [ ] 更多开箱即用的业务模块
- [ ] 可视化代码生成工具

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

**文档版本**：v2.1  
**最后更新**：2026-08-22  
**维护人员**：Tongle Xu
