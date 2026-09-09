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
| 用户信息管理 | 查看和修改个人资料（profile/extra 两张扩展表） |
| 密码重置 | 通过邮箱/手机验证码重置密码 |
| 联系方式重置 | 通过验证码重置手机号/邮箱 |
| 用户状态管理 | 启用、禁用、冻结用户 |
| VIP会员系统 | 支持VIP会员延期功能 |
| 积分/金币系统 | 用户积分和金币流水管理（调整余额、查询流水） |
| 钱包系统 | 真实货币余额管理（余额查询、流水明细、充值、提现、冻结/解冻），记录层多态设计支持未来扩展至其他主体 |
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
| 钱包管理 | 钱包流水列表（全局，可按持有者/类型/时间筛选）、流水详情、后台调账（充正/扣减）、钱包统计（总余额/总充值/总提现） |
| 提现审核 | 提现申请列表与统计、提现详情（含脱敏收款账户）、审核通过/驳回（驳回自动解冻）、标记已打款、导出提现记录 |
| 充值订单 | 充值订单列表（按支付状态/渠道/时间筛选）、订单详情、超时订单自动关闭 |

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
│   │   ├── Wallet/          # 钱包模型（WalletTrade、RechargeOrder、WithdrawOrder，均为多态 owner）
│   │   ├── System/          # 系统模型（Area、Attachment、AuditLog、Dict、DictItem、Setting、Role、Permission、Notification、MailCode、PhoneCode、ScheduleLog 等）
│   │   ├── User/            # 用户扩展模型（UserExtra、UserProfile、UserStat）
│   │   ├── Traits/          # 模型 Trait（DateTimeFormatter、HasSettingsProperty、HasUser、HasWalletBalance）
│   │   ├── Model.php        # 模型基类
│   │   └── User.php         # 前台用户模型
│   ├── Observers/           # 模型观察者（UserObserver）
│   ├── Policies/            # 授权策略（UserPolicy）
│   ├── Providers/           # 服务提供者（App、DynamicConfig、Horizon、Telescope）
│   ├── Rules/               # 自定义验证规则（PhoneRule、MailCaptchaRule 等）
│   ├── Services/            # 业务服务（AttachmentService、SmsCaptchaService、WechatService 等）
│   ├── Sms/                 # 短信消息类（VerifyCodeMessage）
│   └── Support/             # 辅助类（CoinHelper、PointHelper、UserHelper、WalletHelper）
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

- **Models Layer**：数据模型和业务逻辑，按领域分子目录（Admin/System/User/Coin/Point/Wallet）
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

#### 5.1.18 钱包相关表（真实货币）

> 设计原则：**余额热数据落在持有者主表字段上（查询零 Join），记录层全部多态**。
> 未来其他模型（如 `admin_users`、未来的 `merchants`）需要支持钱包时，只需在其表追加 `balance` / `frozen_balance` 两个字段并 `use HasWalletBalance`，记录层无需任何改动。

**持有者表新增字段（本期落在 `users` 表）**

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| balance | DECIMAL(16,2) | 0.00 | 可用余额（元） |
| frozen_balance | DECIMAL(16,2) | 0.00 | 冻结余额（元，下单预授权锁定） |

**钱包流水表 (wallet_trades)**

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键（从 10000000 起） |
| owner_type | VARCHAR(64) | 持有者类型（多态，取 `config/morph_maps.php` 别名，如 `user`） |
| owner_id | BIGINT | 持有者 ID |
| amount | DECIMAL(16,2) | 本次变动金额（正增负减） |
| before_balance | DECIMAL(16,2) | 变动前可用余额快照 |
| after_balance | DECIMAL(16,2) | 变动后可用余额快照 |
| before_frozen | DECIMAL(16,2) | 变动前冻结余额快照 |
| after_frozen | DECIMAL(16,2) | 变动后冻结余额快照 |
| type | VARCHAR(32) | 交易类型（枚举 `WalletTradeType`） |
| source_type | VARCHAR(64) | 业务来源类型（多态，如订单、提现单） |
| source_id | BIGINT | 业务来源 ID |
| status | VARCHAR(20) | 流水状态（pending/success/failed） |
| description | VARCHAR(255) | 交易描述 |
| created_at | TIMESTAMP | 交易时间 |

索引：`(owner_type, owner_id, created_at)` 复合索引、`(source_type, source_id)` 复合索引、`type` 索引。

> 冻结/解冻操作只变动冻结余额而不动可用余额，因此必须同时保留 `before_frozen` / `after_frozen` 快照，否则无法完整审计对账。

**充值订单表 (recharge_orders)**

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| owner_type / owner_id | VARCHAR(64) / BIGINT | 持有者（多态） |
| order_no | VARCHAR(64) | 订单号（唯一，前缀 `R` + 日期 + 随机串） |
| amount | DECIMAL(16,2) | 充值金额 |
| bonus_amount | DECIMAL(16,2) | 赠送金额（按 `wallet.recharge_bonus_rate` 计算） |
| pay_channel | VARCHAR(32) | 支付渠道（wechat/alipay/apple） |
| pay_status | VARCHAR(20) | 支付状态（pending/success/failed/expired） |
| paid_at | TIMESTAMP | 支付完成时间 |
| expired_at | TIMESTAMP | 订单过期时间（默认创建后 30 分钟） |
| created_at / updated_at | TIMESTAMP | 时间戳 |

索引：`order_no` 唯一索引、`(owner_type, owner_id)` 复合索引、`pay_status` 索引。

**提现申请表 (withdraw_orders)**

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| owner_type / owner_id | VARCHAR(64) / BIGINT | 持有者（多态） |
| order_no | VARCHAR(64) | 提现单号（唯一，前缀 `W`） |
| amount | DECIMAL(16,2) | 申请提现金额 |
| fee | DECIMAL(16,2) | 手续费（按 `wallet.withdraw_fee_rate` 计算） |
| actual_amount | DECIMAL(16,2) | 实际到账金额（amount - fee） |
| account_type | VARCHAR(32) | 收款账户类型（wechat/alipay/bank） |
| account_info | JSON | 收款账户信息（脱敏展示） |
| status | VARCHAR(20) | 状态（枚举 `WithdrawStatus`） |
| reviewed_by | BIGINT | 审核管理员 ID |
| reviewed_at | TIMESTAMP | 审核时间 |
| reject_reason | VARCHAR(255) | 驳回原因 |
| transfer_at | TIMESTAMP | 打款完成时间 |
| created_at / updated_at | TIMESTAMP | 时间戳 |

索引：`order_no` 唯一索引、`(owner_type, owner_id)` 复合索引、`status` 索引。

**枚举定义**

`WalletTradeType`（钱包交易类型）：

| 值 | 说明 |
|----|------|
| recharge | 充值 |
| withdraw | 提现 |
| freeze | 冻结 |
| unfreeze | 解冻 |
| payment | 消费 |
| refund | 退款 |
| admin_recharge | 后台充正 |
| admin_deduct | 后台扣减 |

`WithdrawStatus`（提现状态）：

| 值 | 说明 |
|----|------|
| pending | 待审核 |
| review | 需复审 |
| approved | 审核通过，待打款 |
| rejected | 已驳回（自动解冻） |
| transferring | 打款中 |
| success | 打款成功 |
| failed | 打款失败（自动解冻） |

**与现有积分/金币体系的关系**

钱包是独立的**真实货币**账本，与现有 `available_points`（积分）、`available_coins`（金币）两套虚拟资产体系**并行且互不影响**，本期不做迁移或合并。

#### 5.1.19 其他核心表
| 表名 | 说明 |
|------|------|
| personal_access_tokens | Sanctum 个人访问令牌 |
| notifications | 站内通知（多态） |
| socials | 第三方账号绑定 |
| password_reset_tokens | 密码重置令牌 |
| coin_trades | 金币流水记录 |
| point_records | 积分记录 |
| point_trades | 积分流水记录 |
| wallet_trades | 钱包流水记录（真实货币，多态 owner） |
| recharge_orders | 充值订单（多态 owner） |
| withdraw_orders | 提现申请（多态 owner） |
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

#### 6.3.13 实名认证（/api/v1/user/identity & /admin/identity-verifications）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/api/v1/user/identity` | 当前用户最新一条实名认证记录 | -（登录） |
| GET | `/api/v1/user/identity/status` | 简要状态（是否已通过、类型、最新驳回原因） | -（登录） |
| POST | `/api/v1/user/identity` | 提交/重新提交实名认证（personal 个人 / enterprise 企业） | -（登录） |
| GET | `/admin/identity-verifications` | 认证记录列表（分页，支持 `keyword`/`user_id`/`type`/`status`/`start_date`/`end_date` 过滤） | identity-verifications.index |
| GET | `/admin/identity-verifications/stats` | 认证统计（总量/待审/今日新增/已通过/已驳回） | identity-verifications.index |
| GET | `/admin/identity-verifications/{verification}` | 认证记录详情（含证件附件 URL） | identity-verifications.index |
| POST | `/admin/identity-verifications/{verification}/approve` | 审核通过（同步 `users.identity_verified`/`identity_type`） | identity-verifications.edit |
| POST | `/admin/identity-verifications/{verification}/reject` | 审核驳回（必填 `reason`、可选 `remark`） | identity-verifications.edit |

> 同一用户同时仅允许一条 `pending`/`review` 记录；已认证用户禁止再次提交，驳回后可重新提交。`users` 表仅保留 `identity_type` 与 `identity_verified` 两个标记位，详细证件数据保存在 `identity_verifications` 表。证件图片通过现有 `/api/v1/uploader/image` 上传后回填 `attachment_id`。

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

#### 6.3.22 钱包管理（/admin/wallet）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/wallet/stats` | 钱包统计（总余额、总冻结、累计充值、累计提现、待审提现数） | wallet.index |
| GET | `/admin/wallet/trades` | 全局钱包流水列表（分页，支持 `owner_type`/`owner_id`/`type`/`status`/关键词/时间范围筛选） | wallet.index |
| GET | `/admin/wallet/trades/{trade}` | 钱包流水详情（含持有者摘要、业务来源摘要、前后余额快照） | wallet.index |
| GET | `/admin/wallet/trades/export` | 导出钱包流水（xlsx，支持同步下载/异步入队双模式） | wallet.export |
| GET | `/admin/users/{user}/wallet` | 指定用户钱包概览（余额、冻结余额、累计收支） | users.index |
| GET | `/admin/users/{user}/wallet-trades` | 指定用户钱包流水（分页） | users.index |
| PUT | `/admin/users/{user}/adjust-wallet` | 后台调整用户余额（正数充正，负数扣减，必填 `description`） | wallet.adjust |

#### 6.3.23 提现审核（/admin/wallet/withdraws）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/wallet/withdraws/stats` | 提现统计（总量/待审/今日新增/已打款/已驳回） | wallet.withdraws |
| GET | `/admin/wallet/withdraws` | 提现申请列表（分页，支持 `owner_type`/`owner_id`/`status`/`account_type`/时间范围筛选） | wallet.withdraws |
| GET | `/admin/wallet/withdraws/{withdraw}` | 提现申请详情（收款账户信息脱敏展示） | wallet.withdraws |
| POST | `/admin/wallet/withdraws/{withdraw}/approve` | 审核通过（状态转 `approved`，保持冻结待打款） | wallet.withdraws |
| POST | `/admin/wallet/withdraws/{withdraw}/reject` | 审核驳回（必填 `reason`，自动解冻并通知用户） | wallet.withdraws |
| POST | `/admin/wallet/withdraws/{withdraw}/transfer` | 标记已打款（状态转 `success`，扣减冻结余额并通知用户） | wallet.withdraws |
| GET | `/admin/wallet/withdraws/export` | 导出提现记录（xlsx） | wallet.export |

> 提现状态流转：`pending` →（approve）`approved` →（transfer）`success`；`pending` →（reject）`rejected`（自动解冻）；打款失败置 `failed` 同样自动解冻。已进入终态（`success`/`rejected`）的记录不可重复操作，参照 `IdentityVerificationController::authorizeAction()` 范式返回 422。

#### 6.3.24 充值订单（/admin/wallet/recharge-orders）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/wallet/recharge-orders` | 充值订单列表（分页，支持 `owner_type`/`owner_id`/`pay_status`/`pay_channel`/时间范围筛选） | wallet.orders |
| GET | `/admin/wallet/recharge-orders/{order}` | 充值订单详情 | wallet.orders |
| GET | `/admin/wallet/recharge-orders/export` | 导出充值订单（xlsx） | wallet.export |

#### 6.3.25 数据概览（/admin/dashboard）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/admin/dashboard/stats` | 仪表盘统计数据 | 是 |

#### 6.3.26 监控接口（/admin/monitor，读取 Pulse 数据）
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

#### 6.3.27 调试接口（/admin/debug，仅 local 环境，读取 Telescope）
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

#### 6.3.28 队列管理（/admin/queue，代理 Horizon 数据）
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

#### 6.3.29 AI 聊天（/admin/chat，SSE 流式）
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

#### 6.4.2 钱包接口（/api/v1/wallet）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/wallet` | 我的钱包概览（可用余额、冻结余额、累计收入、累计支出） |
| GET | `/api/v1/wallet/trades` | 我的钱包流水（分页，可按 `type`/时间范围过滤） |
| POST | `/api/v1/wallet/recharge` | 创建充值订单（返回 `order_no` 与支付参数，供客户端调起支付 SDK） |
| GET | `/api/v1/wallet/recharge-orders` | 我的充值订单列表（分页） |
| GET | `/api/v1/wallet/recharge-orders/{order}` | 充值订单详情（用于轮询支付状态） |
| POST | `/api/v1/wallet/withdraw` | 提交提现申请（校验限额与频次，成功后冻结对应金额） |
| GET | `/api/v1/wallet/withdraws` | 我的提现记录列表（分页） |
| GET | `/api/v1/wallet/withdraws/{withdraw}` | 提现详情 |

**约定说明**

- 全部接口需 Sanctum 认证，内部统一取 `auth()->user()` 作为 owner，前台无需感知多态
- 提现提交需通过独立 `throttle` 限流，防止刷单
- 越权访问他人订单返回 `403`，由 `RechargeOrderPolicy` / `WithdrawOrderPolicy` 校验
- 金额字段统一以元为单位、`decimal(16,2)` 精度返回字符串，避免前端浮点精度丢失

#### 6.4.3 支付回调（/callback）

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/callback/pay/wechat` | 微信支付异步回调（验签后置订单成功并入账） |
| POST | `/callback/pay/alipay` | 支付宝异步回调（验签后置订单成功并入账） |

回调接口豁免 CSRF 与认证中间件，必须完成渠道验签；处理逻辑幂等（同一 `order_no` 重复回调只入账一次）。

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

## 8. 钱包技术方案

### 8.1 设计原则

- **余额热数据落在持有者主表，记录层全部多态**：余额是最高频读取的字段，直接作为 `users` 表列存储可做到查询零 Join；而流水、充值单、提现单三张记录表统一使用 `owner_type` + `owner_id` 多态关联，业务逻辑不与 `User` 耦合。
- **可扩展但不过度设计**：未来其他主体（如 `admin_users` 平台账户、未来的 `merchants` 商户）需要钱包时，只需在其表追加 `balance` / `frozen_balance` 两个字段并 `use HasWalletBalance`，记录层与 `WalletHelper` 无需改动。本期不引入独立 `wallets` 表和多币种，避免为假想需求付出复杂度。
- **增量更新而非流水汇总**：与现有 `CoinHelper`（每次 `SUM` 全表重算）不同，钱包余额采用事务内行锁 + 增量更新（`balance + amount`），流水表保存前后余额快照用于对账。真实货币场景对一致性与性能要求都更高，`SUM` 全表在数据量增长后不可接受。
- **冻结与可用余额分离**：`balance` 为可用余额，`frozen_balance` 为冻结余额，两者互不重叠。下单冻结即 `balance -= n; frozen_balance += n`，成交扣款即 `frozen_balance -= n`，取消解冻即反向操作。
- **金额精度**：统一 `decimal(16,2)`，以元为单位；PHP 侧用字符串/`decimal` cast 传递，禁止使用 `float` 参与金额运算。
- **与虚拟资产完全隔离**：钱包是真实货币账本，与 `available_points`（积分）、`available_coins`（金币）并行独立，不做迁移合并。

### 8.2 代码结构

```
app/
├── Enums/
│   ├── WalletTradeType.php             # 钱包交易类型枚举（实现 HasLabel）
│   ├── WalletTradeStatus.php           # 流水状态枚举（pending/success/failed）
│   ├── WithdrawStatus.php              # 提现状态枚举（实现 HasLabel）
│   ├── PayChannel.php                  # 支付渠道枚举（wechat/alipay/apple）
│   └── WithdrawAccountType.php         # 收款账户类型枚举（wechat/alipay/bank）
├── Exceptions/
│   ├── InsufficientBalanceException.php    # 余额不足
│   └── InsufficientFrozenBalanceException.php # 冻结余额不足
├── Models/
│   ├── Traits/
│   │   └── HasWalletBalance.php        # 钱包能力 trait（多态关系 + 余额判定）
│   └── Wallet/
│       ├── WalletTrade.php             # 钱包流水（morphTo owner + morphTo source）
│       ├── RechargeOrder.php           # 充值订单（morphTo owner）
│       └── WithdrawOrder.php           # 提现申请（morphTo owner）
├── Support/
│   └── WalletHelper.php                # 余额引擎（incr/decr/freeze/unfreeze）
├── Services/
│   ├── RechargeService.php             # 充值下单与回调入账编排
│   └── WithdrawService.php             # 提现申请与审核流转编排
├── Events/Wallet/
│   ├── BalanceChanged.php              # 余额变动事件
│   ├── RechargeSucceeded.php           # 充值成功事件
│   ├── WithdrawApproved.php            # 提现审核通过事件
│   └── WithdrawRejected.php            # 提现驳回事件
├── Notifications/Wallet/
│   ├── RechargeSucceededNotification.php
│   ├── WithdrawApprovedNotification.php
│   ├── WithdrawRejectedNotification.php
│   └── WithdrawTransferredNotification.php
├── Jobs/Wallet/
│   └── CloseExpiredRechargeOrdersJob.php   # 关闭超时未支付订单
├── Policies/
│   ├── RechargeOrderPolicy.php
│   └── WithdrawOrderPolicy.php
├── Exports/Admin/
│   ├── WalletTradeExport.php
│   ├── WithdrawOrderExport.php
│   └── RechargeOrderExport.php
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── WalletController.php            # 流水列表/详情/统计/调账
│   │   │   ├── WithdrawOrderController.php     # 提现审核
│   │   │   └── RechargeOrderController.php     # 充值订单查看
│   │   ├── Api/V1/User/
│   │   │   └── WalletController.php            # 前台钱包概览/流水/充值/提现
│   │   └── PayCallbackController.php           # 支付渠道异步回调
│   ├── Requests/
│   │   ├── Admin/Wallet/
│   │   │   ├── WalletTradeIndexRequest.php
│   │   │   ├── WalletAdjustRequest.php
│   │   │   ├── WithdrawIndexRequest.php
│   │   │   └── WithdrawRejectRequest.php
│   │   └── Api/V1/Wallet/
│   │       ├── CreateRechargeRequest.php
│   │       └── CreateWithdrawRequest.php
│   └── Resources/
│       ├── Admin/
│       │   ├── WalletTradeResource.php
│       │   ├── WalletStatResource.php
│       │   ├── WithdrawOrderResource.php
│       │   └── RechargeOrderResource.php
│       └── Api/V1/
│           ├── WalletResource.php
│           ├── WalletTradeResource.php
│           ├── WithdrawOrderResource.php
│           └── RechargeOrderResource.php
database/
├── migrations/
│   ├── xxxx_add_balance_to_users_table.php     # users 表加 balance/frozen_balance
│   ├── xxxx_create_wallet_trades_table.php
│   ├── xxxx_create_recharge_orders_table.php
│   └── xxxx_create_withdraw_orders_table.php
└── factories/Wallet/
    ├── WalletTradeFactory.php
    ├── RechargeOrderFactory.php
    └── WithdrawOrderFactory.php
tests/Feature/
├── Admin/
│   ├── WalletControllerTest.php
│   ├── WithdrawOrderControllerTest.php
│   └── RechargeOrderControllerTest.php
└── Api/V1/
    └── WalletControllerTest.php
```

### 8.3 HasWalletBalance Trait

任何主表含 `balance` / `frozen_balance` 两列的模型 `use HasWalletBalance` 即获得完整钱包能力：

| 方法 | 职责 |
|------|------|
| `walletTrades(): MorphMany` | 钱包流水（作为 owner） |
| `rechargeOrders(): MorphMany` | 充值订单（作为 owner） |
| `withdrawOrders(): MorphMany` | 提现申请（作为 owner） |
| `hasBalance(float $amount): bool` | 判定可用余额是否充足 |
| `totalBalance(): float` | 总资产（可用 + 冻结） |

本期仅 `User` 接入。

### 8.4 WalletHelper 关键方法

| 方法 | 职责 | 余额变动 |
|------|------|---------|
| `incr($owner, $amount, $source, $type, $desc)` | 增加可用余额（充值到账、退款、后台充正） | `balance += n` |
| `decr($owner, $amount, $source, $type, $desc)` | 扣减可用余额（直接消费、后台扣减） | `balance -= n` |
| `freeze($owner, $amount, $source, $desc)` | 冻结（下单预授权、提现申请） | `balance -= n; frozen_balance += n` |
| `unfreeze($owner, $amount, $source, $desc)` | 解冻（订单取消、提现驳回） | `frozen_balance -= n; balance += n` |
| `settleFrozen($owner, $amount, $source, $type, $desc)` | 结算冻结（订单成交、提现打款成功） | `frozen_balance -= n` |
| `getBalance($owner): float` | 读取可用余额 | — |
| `getFrozenBalance($owner): float` | 读取冻结余额 | — |

**并发安全实现要点**：

- 所有写方法包裹在 `DB::transaction()` 中，进入事务后先对持有者主表行执行 `lockForUpdate()` 取最新余额
- 校验余额充足性使用锁内读到的值，不使用传入模型的过期属性
- 余额更新使用增量表达式（`increment` / `decrement`）而非先读后写的赋值，避免丢失更新
- 流水记录写入 `before_balance` / `after_balance` / `before_frozen` / `after_frozen` 四个快照字段，供财务对账与差异排查
- 余额不足抛 `InsufficientBalanceException`，控制器层捕获后 `abort(422, ...)`，与现有 `InsufficientCoinsException` 处理方式一致

### 8.5 充值链路

```
POST /api/v1/wallet/recharge
  → CreateRechargeRequest 校验金额区间
  → RechargeService::create()
      → 计算赠送金额（wallet.recharge_bonus_rate）
      → 生成 order_no（R + 日期 + 随机串）
      → 创建 RechargeOrder(pay_status=pending, expired_at=now+30min)
      → 调用支付渠道预下单，返回客户端支付参数

POST /callback/pay/{channel}
  → PayCallbackController 完成渠道验签
  → RechargeService::handleCallback()
      → 事务内按 order_no 加锁查单，已 success 直接返回（幂等）
      → 置 pay_status=success、paid_at
      → WalletHelper::incr() 入账（amount + bonus_amount）
      → 派发 RechargeSucceeded 事件 → 队列发站内信
```

超时未支付订单由 `CloseExpiredRechargeOrdersJob` 定时置为 `expired`，注册到 `routes/console.php`。

### 8.6 提现链路

```
POST /api/v1/wallet/withdraw
  → CreateWithdrawRequest 校验金额、限额、当日次数
  → WithdrawService::submit()
      → 事务内校验可用余额
      → 计算手续费与实际到账金额
      → WalletHelper::freeze() 冻结申请金额
      → 创建 WithdrawOrder(status=pending)

POST /admin/wallet/withdraws/{id}/approve
  → WithdrawService::approve()
      → 校验当前状态非终态（参照 IdentityVerificationController::authorizeAction）
      → 置 status=approved、reviewed_by、reviewed_at
      → 派发 WithdrawApproved 事件 → 通知用户

POST /admin/wallet/withdraws/{id}/reject
  → WithdrawService::reject()
      → 置 status=rejected、reject_reason
      → WalletHelper::unfreeze() 解冻退回可用余额
      → 派发 WithdrawRejected 事件 → 通知用户

POST /admin/wallet/withdraws/{id}/transfer
  → WithdrawService::markTransferred()
      → 置 status=success、transfer_at
      → WalletHelper::settleFrozen() 扣减冻结余额（资金真正流出）
      → 通知用户到账
```

打款失败置 `failed` 并同样执行 `unfreeze()` 退回余额。

### 8.7 权限与菜单

**新增权限**：

| 权限标识 | 说明 |
|---------|------|
| wallet.index | 查看钱包流水与统计 |
| wallet.withdraws | 提现审核管理（列表/详情/审核/打款） |
| wallet.orders | 充值订单查看 |
| wallet.adjust | 后台调整余额（高风险操作） |
| wallet.export | 导出钱包流水/提现记录/充值订单 |

**新增菜单**（挂在「运营管理」目录下，通过编辑 `2026_10_31_111934_seed_initial_admin_data.php` 迁移追加）：

| 菜单 key | path | component | title | permission |
|----------|------|-----------|-------|------------|
| operation.wallet | wallet | /operation/wallet | 钱包流水 | wallet.index |
| operation.withdraw | withdraw | /operation/withdraw | 提现审核 | wallet.withdraws |
| operation.recharge | recharge | /operation/recharge | 充值订单 | wallet.orders |

按钮权限：钱包流水页挂「调账」（`wallet.adjust`）、「导出」（`wallet.export`）；提现审核页挂「审核」（`wallet.withdraws`）、「导出」（`wallet.export`）。

`wallet.adjust` 属高风险操作，后续接入 PRD 14.2.5「敏感操作二次验证」时需纳入覆盖范围。

### 8.8 配置项

新增 `wallet.*` 分组系统设置（写入 settings 表种子）：

| 配置键 | 默认值 | 类型 | 说明 |
|--------|--------|------|------|
| wallet.enable_recharge | true | bool | 是否开放充值 |
| wallet.enable_withdraw | true | bool | 是否开放提现 |
| wallet.min_recharge_amount | 1 | int | 单笔最低充值金额（元） |
| wallet.max_recharge_amount | 50000 | int | 单笔最高充值金额（元） |
| wallet.recharge_bonus_rate | 0 | float | 充值赠送比例（%） |
| wallet.recharge_order_expire_minutes | 30 | int | 充值订单超时时长（分钟） |
| wallet.min_withdraw_amount | 100 | int | 单笔最低提现金额（元） |
| wallet.max_withdraw_amount | 50000 | int | 单笔最高提现金额（元） |
| wallet.withdraw_fee_rate | 0 | float | 提现手续费率（%） |
| wallet.daily_withdraw_limit | 3 | int | 每日提现申请次数上限 |
| wallet.withdraw_throttle | 3,1 | string | 提现接口限流（次数,分钟） |

### 8.9 多态别名

`config/morph_maps.php` 新增：

```php
'wallet_trade'   => \App\Models\Wallet\WalletTrade::class,
'recharge_order' => \App\Models\Wallet\RechargeOrder::class,
'withdraw_order' => \App\Models\Wallet\WithdrawOrder::class,
```

`owner_type` 直接复用已注册的 `user` / `admin` 别名，无需额外注册。

### 8.10 前端落地清单（laravel-skel-admin）

| 文件 | 说明 |
|------|------|
| `src/api/wallet-manage.ts` | 钱包流水、提现审核、充值订单全部 API 函数 |
| `src/types/api/api.d.ts` | 新增 `Api.WalletManage` 命名空间类型定义 |
| `src/views/operation/wallet/index.vue` | 钱包流水列表（统计卡片 + 表格） |
| `src/views/operation/wallet/modules/wallet-search.vue` | 流水搜索栏 |
| `src/views/operation/wallet/modules/wallet-trade-detail-dialog.vue` | 流水详情弹窗（含前后余额快照） |
| `src/views/operation/wallet/modules/wallet-adjust-dialog.vue` | 后台调账弹窗 |
| `src/views/operation/withdraw/index.vue` | 提现审核列表 |
| `src/views/operation/withdraw/modules/withdraw-search.vue` | 提现搜索栏 |
| `src/views/operation/withdraw/modules/withdraw-stats.vue` | 提现统计卡片 |
| `src/views/operation/withdraw/modules/withdraw-detail-drawer.vue` | 提现详情抽屉 |
| `src/views/operation/withdraw/modules/withdraw-reject-dialog.vue` | 驳回弹窗 |
| `src/views/operation/recharge/index.vue` | 充值订单列表 |
| `src/views/operation/recharge/modules/recharge-search.vue` | 充值订单搜索栏 |
| `src/views/operation/recharge/modules/recharge-detail-dialog.vue` | 充值订单详情弹窗 |
| `src/views/user/list/modules/user-detail-dialog.vue` | 现有文件追加「钱包」Tab（余额 + 流水 + 导出） |

页面路由由后端菜单动态注册，前端无需手写路由配置。

### 8.11 实施阶段

| 阶段 | 内容 | 交付物 |
|------|------|--------|
| **Phase 1 基础账本** | `users` 表加字段；`wallet_trades` 表；`WalletTradeType` / `WalletTradeStatus` 枚举；`HasWalletBalance` trait；`WalletHelper` 四个核心方法 + 结算方法；后台流水列表/详情/统计/调账；用户详情弹窗钱包 Tab；前台钱包概览与流水接口；PHPUnit 覆盖并发扣减与余额不足场景 | 可查询、可调账的完整账本 |
| **Phase 2 充值闭环** | `recharge_orders` 表 + 模型 + Policy；`PayChannel` 枚举；`RechargeService`；微信支付渠道对接与回调验签；幂等入账；超时关单 Job + 定时任务注册；后台充值订单管理；前台充值接口；`RechargeSucceededNotification` | 用户可充值到账 |
| **Phase 3 提现审核** | `withdraw_orders` 表 + 模型 + Policy；`WithdrawStatus` / `WithdrawAccountType` 枚举；`WithdrawService` 完整状态流转；冻结/解冻/结算联动；后台提现审核页面；前台提现接口 + 限流；三个提现通知类；导出类 | 提现申请到打款全流程闭环 |
| **Phase 4 集成与验收** | 订单/优惠券等业务侧接入冻结与结算；钱包指标并入业务数据看板；Apifox 按 Admin/Api 模块规范全量发布；补齐测试至项目 70% 覆盖率要求；README 与本 PRD 同步 | 生产可用 |

**前置依赖**：

- Phase 2 需先在 `config/services.php` 配置微信支付/支付宝商户参数与证书
- Phase 4 的冻结链路需 PRD 14.3 P2「支付与订单」模块先落地，否则冻结无实际业务场景
- `wallet.adjust` 高风险操作建议与 PRD 14.2.5「敏感操作二次验证」协同推进

## 9. 非功能性需求

### 9.1 性能要求
- API 响应时间：< 200ms（95% 请求）
- 支持并发用户数：1000+（Octane 模式可进一步提升）
- 数据库查询优化：预加载防止 N+1 查询
- 缓存策略：热点数据使用 Redis 缓存
- 队列异步处理：耗时操作（统计、AI 工具等）走队列

### 9.2 安全要求
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

### 9.3 可扩展性
- 模块化设计：功能独立封装
- 微服务友好：API 化设计，易于拆分
- 支持水平扩展：无状态设计（Token 认证）
- 队列系统：异步任务处理
- AI Tool 可插拔：新工具只需实现 Tool 契约即可接入

### 9.4 可维护性
- 代码规范：遵循 PSR-12 标准，Laravel Pint 自动格式化
- 类型安全：PHP 8.4 严格类型（`declare(strict_types=1)`），所有方法参数和返回值类型声明
- 注释完整：关键逻辑使用 PHPDoc，复杂处加内联注释
- 测试覆盖：单元测试 + 功能测试，PHPUnit 12 属性语法
- 枚举统一：使用 PHP 8 Enum + HasLabel trait

### 9.5 可靠性
- 错误处理：统一的异常处理机制
- 日志记录：完整的日志记录（Monolog 多通道）
- 数据备份：支持数据库备份
- 故障恢复：队列失败重试、调度任务自动记录失败状态

## 10. 开发流程

### 10.1 环境要求
- PHP >= 8.4
- PHP 扩展：`pdo`、`mbstring`、`openssl`、`redis`、`bcmath`、`ctype`、`json`、`fileinfo`、`tokenizer`、`xml`
- Composer >= 2.x
- MySQL 8.0+ / PostgreSQL / SQLite
- Redis 6.0+
- Node.js >= 20.x（前端资源构建）
- 推荐使用 [Laravel Herd](https://herd.laravel.com/) 作为本地开发环境

### 10.2 快速开始

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

### 10.3 开发命令

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

### 10.4 代码规范
- 遵循 PSR-12 编码标准
- 使用 PHP 8.4 特性：构造器属性提升、命名参数、枚举、match 表达式
- 所有 PHP 文件强制 `declare(strict_types=1)` 和文件头 License 注释
- 所有方法添加参数类型声明和返回类型声明
- 使用 PHPUnit 12 新属性语法（`#[Test]`、`#[CoversClass]`、`#[Group]`、`#[TestDox]`）
- 修改 PHP 文件后必须运行 `vendor/bin/pint --dirty --format agent`
- 使用 `php artisan make:` 命令创建文件，传递 `--no-interaction` 参数
- AI Agent 使用 `php artisan make:agent` / `php artisan make:tool`（自定义 stub 在 stubs/）

### 10.5 全局辅助函数（bootstrap/helpers.php）
| 函数 | 说明 |
|------|------|
| `cpu_count(): int` | 获取 CPU 核心数 |
| `per_page(Request $request, int $limit = 15): int` | 获取分页大小（限制 1~100） |
| `mobile_replace(?string $value): string` | 手机号脱敏 |
| `settings(string $key, $default): mixed` | 快捷读取系统设置 |
| `validation_exception($field, $message): void` | 快速抛出表单验证异常 |

## 11. 部署方案

### 11.1 生产环境配置

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

### 11.2 部署方式

| 方式 | 说明 |
|------|------|
| Octane + Swoole/RoadRunner | 高性能常驻内存模式 |
| Nginx + PHP-FPM | 传统部署方式 |

### 11.3 服务配置

| 服务 | 建议配置 |
|------|----------|
| Horizon | Supervisor 守护，`php artisan horizon` |
| Reverb | 独立进程或 Supervisor 守护，`php artisan reverb:start` |
| Scheduler | Cron 每分钟执行 `php artisan schedule:run` |
| Pulse | 访问控制保护（Gate 鉴权） |
| Telescope | 生产环境禁用或仅限管理员访问 |

### 11.4 备份策略
- 数据库每日备份
- 重要配置备份
- 上传文件备份（如使用 S3 可开启版本控制）
- 备份保留 30 天

## 12. 监控与维护

### 12.1 监控工具
| 工具 | 用途 | 访问路径 |
|------|------|----------|
| Laravel Pulse | 应用性能监控 | `/pulse` |
| Laravel Horizon | 队列监控 | `/horizon` |
| Laravel Telescope | 调试辅助 | `/telescope`（local 环境） |
| Laravel Reverb | WebSocket 统计 | `/api/laravel-reverb` 或后台 Monitor |
| Laravel Pail | 实时日志 | 命令行 `php artisan pail` |

### 12.2 日常维护
- 定期清理过期验证码（`auth:clear-resets`、phone_codes/mail_codes 自动过期）
- 定期清理 Telescope 记录（`telescope:prune --hours=48`）
- 定期清理审计日志和调度日志（通过 `model:prune` 按配置保留天数）
- 数据库优化（索引维护、慢查询优化）
- 依赖安全更新检查（`composer audit`）

## 13. 定时任务

在 `routes/console.php` 中定义：

| 任务 | 频率 | 说明 |
|------|------|------|
| `auth:clear-resets` | 每日 01:00 | 清理过期密码重置令牌 |
| `model:prune` | 每日 01:05 | 清理已标记删除的模型（含调度日志过期清理） |
| `stats:user` | 每日 01:10 | 统计昨日用户数据 |
| `telescope:prune --hours=48 --keep-exceptions` | 每日 01:15 | 清理 Telescope 记录 |
| `horizon:snapshot` | 每 5 分钟 | 记录应用状态快照 |
| `wallet:close-expired-recharge` | 每 5 分钟 | 关闭超时未支付的充值订单（钱包 Phase 2 交付） |

调度任务执行情况自动记录到 `schedule_logs` 表，可在后台"监控管理-调度日志"查看。

## 14. 待建设需求（Roadmap）

> 本章记录作为"系统应用起点"仍需补齐的能力清单。优先级定义：
> **P0** = 上线前必备（缺失将阻塞生产发布）；**P1** = 强烈建议（影响运营闭环）；
> **P2** = 典型业务扩展（按业务形态选做）；**P3** = 体验与性能优化。

### 14.1 P0 上线前必备

> 状态：P0 共 7 项，已完成 3 项、进行中 1 项、未开始 3 项。

#### 14.1.1 Banner / 轮播图管理 ✅
| 项目 | 说明 |
|------|------|
| 业务价值 | 启动页、首页轮播是 App/H5 的标准运营位，是最高频的运营配置需求 |
| 状态 | **已完成**（见 14.5 变更记录 2026-08-27）。后端 7 个管理端点 + 1 个前台公开端点全部交付：CRUD、启用/禁用切换、拖拽排序（同广告位内上移/下移）、按广告位/关键词/状态筛选、生效时间区间控制；前台公开接口自动过滤禁用/未开始/已过期 Banner。前端 `views/operation/banner/` 完整页面（列表+搜索+编辑弹窗），Apifox 8 个接口全部按 Admin/Api 模块规范发布并通过 PHPUnit 38 个用例验证 |
| 验收标准 | ✅ 广告位可配置（基于字典 banner_positions，无需改代码即可扩展）；✅ 过期/未开始的 Banner 前台不可见；✅ 排序可移动并保存（上移/下移方式）；✅ 字段安全：前台接口仅返回 6 个公开字段；✅ 跨平台字段安全：Admin 端用 EloquentPaginate `x-apifox-refs` 引用 #/definitions/304922372，Api 端独立 PublicBanner Schema #/definitions/308708084，零跨模块引用 |

#### 14.1.2 管理员登录日志 ✅
| 项目 | 说明 |
|------|------|
| 业务价值 | 安全合规必备。已有用户侧登录历史，管理员侧缺失审计能力 |
| 状态 | **已完成**（见 14.5 变更记录 2026-08-27）。管理员与前台用户复用多态表 `login_histories`，`result` 字段区分成功/失败；单管理员视角 `GET /admin/admins/{id}/login-histories`、单用户视角 `GET /admin/users/{user}/login-histories` 均支持 `result` 过滤；全局视角 `GET /admin/login-histories`（跨管理员与前台用户，支持按 guard、用户ID、IP、结果、关键字、时间范围过滤）已提供 |
| 前端页面 | `views/monitor/login-history/index.vue`，展示登录时间、归属主体、IP、归属地、设备、浏览器、结果（成功/失败） |
| 验收标准 | ~~登录成功与失败均落库~~（已完成）；~~可按管理员和时间范围检索~~（已完成）；~~全局列表可跨主体检索~~（已完成，前端页面 `views/monitor/login-history/index.vue` 已实现），超级管理员可见全部 |

#### 14.1.3 系统公告 / 站内信群发
| 项目 | 说明 |
|------|------|
| 业务价值 | 当前通知仅由业务事件被动触发，缺少主动运营触达能力 |
| 状态 | **进行中**——已具备的前置能力：① 分群能力（14.2.4）已就绪，可按分群圈定目标用户；② 批量打标/解标异步化（步骤 13）已就绪，可支撑百万级用户场景；③ `UserSegmentController::options` 已暴露手动分群下拉。**待补**：公告主模块（CRUD + 发布/撤回 + 发送范围预览 + 发送进度查询） |
| 数据表 | `announcements`：id、title、content、type、target_type（全体/指定用户/用户分群）、target_value、publish_at、status |
| 后台接口 | 公告 CRUD、发布/撤回、发送范围预览、发送进度查询 |
| 实现要点 | 群发通过队列分批投递（避免大批量同步写入）；复用现有 `notifications` 通知体系；目标范围支持"全体 / 手动分群 / 规则分群 / 指定用户"四类，分群选项直接复用 `GET /admin/user-segments/options` |
| 前台接口 | `GET /api/v1/announcements` 公告列表、`GET /api/v1/announcements/{id}` 详情 |
| 前端页面 | `views/operation/announcement/index.vue`，富文本编辑（复用 ArtWangEditor）、发送范围选择（复用分群下拉组件） |
| 验收标准 | 支持定时发布；群发通过队列异步执行；用户端可查看未读公告数；分群圈选体验与分群管理一致 |
| 排期建议 | **P0 优先级第一**（P0 仅剩 2 个未开始项，公告群发是上线后运营触达最关键能力） |

#### 14.1.4 敏感词过滤
| 项目 | 说明 |
|------|------|
| 业务价值 | UGC 内容（评论、反馈、昵称）合规底线要求 |
| 状态 | **未开始** |
| 数据表 | `sensitive_words`：id、word、category、level（警告/拦截）、status |
| 实现要点 | 提供 `SensitiveWordService`，支持 DFA 算法匹配；结果缓存到 Redis；命中拦截级直接返回 422，命中警告级自动置为待审核 |
| 接入点 | 评论发布、反馈提交、用户昵称/简介修改 |
| 后台接口 | 敏感词 CRUD、批量导入（TXT/CSV）、缓存刷新 |
| 前端页面 | `views/system/sensitive-word/index.vue`，支持分类管理与批量导入 |
| 验收标准 | 命中拦截级内容无法提交；命中警告级自动进入待审核队列；词库变更后缓存自动刷新 |

#### 14.1.5 列表数据导出 ✅
| 项目 | 说明 |
|------|------|
| 业务价值 | 运营/财务对账的刚性需求；前端已有 `ArtExcelExport` 组件，后端接口层已补齐 |
| 状态 | **已完成**（后端 + 前端，见 14.5 变更记录 2026-08-27）。后端：`maatwebsite/excel` 4.0.2 已集成，发布 `config/excel.php`；新增 `App\Support\Export\AbstractExport` 基类（实现 `FromQuery`/`WithHeadings`/`WithMapping`/`WithTitle`/`WithColumnFormatting`，自动追加主键避免 chunk 漏行）与 `HandlesExports` 共享 trait；5 个 Export 类（用户/评论/反馈/审计日志/用户积分/金币流水），所有 .xlsx 导出；同步/异步双通道：≤ `SYNC_THRESHOLD`（用户/评论/反馈/审计日志 1000 行、流水 2000 行）走 `BinaryFileResponse` 直接下载（`Content-Disposition: attachment; filename*=UTF-8''...`），超阈值走 `PerformExportJob` 队列生成文件并通过 `ExportCompletedNotification` 推送站内信下载链接，失败推 `ExportFailedNotification`；控制器与路由：用户 `users/export`、`users/{user}/point-trades/export` 与 `coin-trades/export`、评论 `comments/export`、反馈 `feedbacks/export`、审计日志 `audit-logs/export`（共 6 个端点）；权限点：`users.export`/`comments.export`/`feedbacks.export`/`audit-logs.export`。前端（laravel-skel-admin）：HTTP 层 `request()` 新增 `returnRawResponse` 选项（返回完整 AxiosResponse，保留 `Content-Disposition` 响应头用于读文件名）；新增 `src/utils/file/download.ts` 提供 `parseFilenameFromDisposition()`（RFC 5987 UTF-8 文件名解析 + plain 回退）与 `downloadBlob()`（Blob → ObjectURL → `<a download>` 下载链路）；新增 `src/hooks/core/useExport.ts` 通用导出 Hook，封装「全屏 loading → blob 请求 → 按 status/contentType 分流处理」：200 + xlsx → 解析文件名并触发下载；202 + JSON → 读取 Blob 为 JSON 并 `ElMessage.success` 提示「导出任务已提交，完成后将通过站内信通知您」；200 + JSON → 警告提示；新增 6 个 `fetchExport*` API 函数（`responseType: 'blob'` + `returnRawResponse: true` + `showErrorMessage: true`）；`Api.Common.ExportTaskResponse` 类型入 `api.d.ts`；4 个列表页（用户/评论/反馈/审计日志）在 `ArtTableHeader` #left 插槽新增「导出」按钮（带 `v-auth` 权限指令、`isExporting` loading 状态），复用 `useTable.searchParams` 自动携带当前筛选条件（剔除 `page`/`per_page`）；用户详情弹窗（`user-detail-dialog.vue`）积分/金币两个 Tab 的筛选栏各加一个独立 loading 的「导出」按钮，复用各自的 `keyword` + `dateRange` 状态作为筛选参数；ESLint / vue-tsc / vite 生产构建均通过 |
| 实现要点 | 统一导出基类，接收当前列表筛选条件；大数据量走队列异步生成后推送下载链接（结合通知）；前端统一走 `useExport()` Hook，同步下载/异步通知自动分流 |
| 覆盖模块 | 用户列表、评论管理、反馈管理、审计日志、用户详情内积分流水/金币流水 |
| 前端交互 | 导出按钮挂载在表格工具栏左侧；点击后全屏 loading 遮罩防止重复点击；同步场景自动触发浏览器下载，文件名形如 `users_20260827_153045.xlsx`；异步场景弹出成功提示，用户稍后在站内信中心下载；导出参数自动与当前表格搜索/筛选条件联动 |
| 验收标准 | ✅ 导出结果与当前筛选条件一致；✅ 超过阈值走异步并通过站内信推送下载链接；✅ 中文文件名正确解析（RFC 5987）；✅ 导出按钮受权限指令控制；✅ ESLint / vue-tsc / 生产构建零错误 |

#### 14.1.6 安全基线加固（部分完成）
| 项目 | 说明 |
|------|------|
| 状态 | **部分完成**——登录限流（`admin.login_throttle`）与失败日志（14.1.2）已交付。**待补**：验证码限流、密码重置限流、CORS 白名单收敛、安全响应头 |
| CORS 配置 | 确认 `config/cors.php` 生产环境白名单收敛（不使用 `*`） |
| 接口限流 | ~~登录~~（已完成：`/api/v1` 侧 `user.login_throttle`、`/admin` 侧 `admin.login_throttle`）、注册、验证码发送、密码重置等敏感接口配置独立 `throttle` 规则 |
| 安全响应头 | 补充 `X-Frame-Options`、`X-Content-Type-Options`、`Referrer-Policy`、CSP |
| 验收标准 | 生产环境安全扫描无高危项；敏感接口具备防爆破限流 |
| 排期建议 | 与 14.1.4 敏感词过滤并行推进，作为上线前最后一轮安全加固 |

### 14.2 P1 强烈建议

> 状态：P1 共 7 项，已完成 1 项、未开始 6 项。优先顺序：内容/帖子（14.2.1）→ 举报（14.2.2）→ 业务通知联动（14.2.3）→ 业务数据看板（14.2.6）→ 敏感操作二次验证（14.2.5）→ 业务定时任务管理（14.2.7）。

#### 14.2.1 内容 / 帖子管理
| 项目 | 说明 |
|------|------|
| 业务价值 | 评论模块已按多态（morphTo）设计，但缺少"内容主体"。补齐后评论体系才形成闭环 |
| 状态 | **未开始** |
| 数据表 | `posts`：id、user_id、title、content、images、category_id、status（审核状态）、view_count、like_count、comment_count、is_top、published_at |
| 后台接口 | 内容 CRUD、审核（复用 `ReviewStatus` 枚举）、置顶、推荐、批量操作 |
| 前台接口 | 内容列表/详情/发布/删除，复用现有点赞、收藏、评论多态能力 |
| 验收标准 | 内容可作为 `source_type` 被评论、点赞、收藏正确关联；审核流程与评论一致 |
| 排期建议 | **P1 优先级第一**——补齐"内容"主体是评论/举报/通知联动的根，缺它则 14.2.2/14.2.3/14.2.4 都只能空转 |

#### 14.2.2 举报管理
| 项目 | 说明 |
|------|------|
| 业务价值 | UGC 平台合规必备，与内容/评论管理配套 |
| 状态 | **未开始** |
| 数据表 | `reports`：id、user_id、source_type、source_id（多态）、reason_type、reason、images、status、admin_id、handle_remark、handled_at |
| 后台接口 | 举报列表（按状态/类型过滤）、处理（举报成立→联动下架内容/封禁用户 / 举报不成立→驳回） |
| 前台接口 | `POST /api/v1/reports` 提交举报 |
| 前端页面 | `views/operation/report/index.vue`，处理弹窗内可直接跳转查看被举报内容 |
| 验收标准 | 举报成立后自动联动处置被举报对象；同一用户对同一对象不可重复举报 |
| 排期建议 | 紧随 14.2.1；举报是合规最敏感一环，须在内容上线后立即补齐 |

#### 14.2.3 业务通知联动
| 项目 | 说明 |
|------|------|
| 业务价值 | 当前评论审核、反馈处理完成后未通知用户，运营闭环断裂 |
| 状态 | **未开始** |
| 覆盖场景 | 评论审核通过/驳回 → 通知作者；反馈处理完成 → 通知提交人；举报处理完成 → 通知举报人；VIP 到期前 → 提醒续费 |
| 实现要点 | 通过模型事件/领域事件触发，走队列异步发送，通知内容直接在代码中定义 |
| 验收标准 | 各场景通知准确送达；不阻塞主流程 |

#### 14.2.4 用户标签与分群 ✅
| 项目 | 说明 |
|------|------|
| 业务价值 | 精细化运营基础，是公告群发指定范围、定向推送的前置能力 |
| 状态 | **已完成**（见 14.5 变更记录 2026-08-27 + 2026-08-28）。交付内容：① `user_segments` + `user_segment_memberships` 双表 + 4 个二级索引；② 手动/规则两种分群类型 + JSON 规则评估器（11 种操作符 + 7 字段）；③ 14 个 Admin 端点 + 异步化（>1000 ID 入队）+ 进度查询；④ 前端 5 个页面（分群管理 + 搜索 + 规则构建器 + 编辑弹窗 + 成员弹窗 + 预览弹窗 + 异步任务列表页）；⑤ Apifox 全量发布到 Admin 模块；⑥ PHPUnit 85 个用例 / 263 断言全绿 |
| 验收标准 | ✅ 用户列表可按分群筛选（AND 语义）；✅ 公告群发可选择分群作为目标范围（14.1.3 待集成）；✅ 批量打标支持 1000 同步 / 超限异步，站内信通知；✅ 任务列表权限隔离，仅本人可见 |

#### 14.2.5 敏感操作二次验证
| 项目 | 说明 |
|------|------|
| 业务价值 | 高风险操作防误操作与防越权 |
| 状态 | **未开始** |
| 覆盖操作 | 删除用户、彻底删除、积分/金币余额调整、角色权限变更、清理缓存 |
| 实现要点 | 中间件 `confirm.password`，校验管理员在 N 分钟内已完成密码确认；前端统一确认弹窗组件 |
| 验收标准 | 未确认密码时高风险接口返回 423；确认后在有效期内不重复要求 |
| 排期建议 | **P1 第二批**——优先级低于内容/举报/通知闭环，是治理型而非业务型需求 |

#### 14.2.6 业务数据看板
| 项目 | 说明 |
|------|------|
| 业务价值 | 精细化运营必备，是 P2 业务扩展后做决策的核心入口 |
| 状态 | **未开始** |
| 现状 | Dashboard 仅有用户数/登录数等基础卡片与趋势图 |
| 待补指标 | DAU/MAU、次日/7日/30日留存、内容与评论发布量趋势、评论审核通过率/驳回率、反馈处理时效（平均处理时长）、活跃用户排行、积分/金币发放与消耗对比 |
| 实现要点 | 指标预聚合到统计表（扩展现有 `stats:user` 定时任务），看板只读聚合表，避免实时全表扫描 |
| 验收标准 | 看板首屏加载 < 1s；支持时间范围切换；指标口径在文档中明确定义 |
| 排期建议 | 紧随 14.2.1 内容上线后——内容数据是看板最关键的数据源之一 |

#### 14.2.7 业务定时任务管理
| 项目 | 说明 |
|------|------|
| 业务价值 | 业务侧需要可配置的定时任务（优惠券过期、积分清零、统计聚合等），不必每次都发版 |
| 状态 | **未开始** |
| 现状 | 已有调度日志（只读），但任务本身在代码中硬编码，无法运营侧调整 |
| 待补 | 任务配置表（任务名、Cron 表达式、参数、启用状态）、手动立即执行、暂停/恢复 |
| 边界 | 系统级任务仍保留在 `routes/console.php`；本模块面向业务可配置任务 |
| 验收标准 | Cron 表达式变更后无需发版即生效；手动执行结果可在调度日志查看 |
| 排期建议 | **P1 末位**——P2 业务上线前必须有，否则每次新增优惠券/活动都要发版 |

### 14.3 P2 业务扩展（按业务形态选做）

> 状态：P2 共 9 项，全部未开始。按"资金 > 支付 > 营销 > 客服 > 前台 > 其他"排序：① 用户钱包 ② 支付与订单 ③ 优惠券/活动 ④ 签到与任务 ⑤ 邀请与分销 ⑥ 工单/客服 ⑦ 前台用户端 ⑧ 国际化 ⑨ 地区数据校验。
>
> 选做原则：选 P2 项时必须先确认 P0/P1 全部 ✅ + 至少 2 个 P2 排在前面的核心业务已交付。

| # | 需求 | 说明 | 依赖 |
|---|------|------|------|
| 1 | 用户钱包 | 真实货币余额账本：余额/冻结余额、多态流水、充值、提现审核、冻结与结算。**P2 优先级第一**——是支付与订单、分销结算的资金底座。完整技术方案见第 8 章 | 14.2.7 业务定时任务（充值超时关单）、14.2.5 敏感操作二次验证（后台调账） |
| 2 | 支付与订单 | 涉及交易场景的根基：订单、支付渠道（微信/支付宝/苹果内购）、退款、对账 | 用户钱包（余额支付与冻结）、14.2.7 业务定时任务（订单超时关闭）|
| 3 | 优惠券 / 活动管理 | 积分体系已就绪，缺营销工具：优惠券模板、领取记录、核销、活动页配置 | 14.2.3 业务通知（核销成功通知）|
| 4 | 签到与任务体系 | 配合积分体系：每日签到、连续签到奖励、成长任务、任务奖励发放 | 14.2.3 业务通知（任务完成通知）|
| 5 | 邀请与分销 | 邀请码、邀请关系树、邀请奖励结算 | 用户钱包（佣金入账）、14.2.4 分群（拉新统计）|
| 6 | 工单 / 客服系统 | 现有反馈模块偏轻量，若需客服能力则需补：工单流转、分配、SLA 计时、多轮会话 | 无强依赖 |
| 7 | 前台用户端 | 目前仅有管理后台，若产品需要则补 H5 / 小程序 / App 用户端 | 14.1.3 公告群发、国际化（如果海外）|
| 8 | 国际化落地 | 海外市场扩张的下一前提；前端 vue-i18n 已安装但未落地语言包 | 14.1.3 公告群发（多语言公告）|
| 9 | 地区数据校验 | 核对地区表是否符合最新国家标准，并与收货地址接口打通联动 | 仅在 P2 选做"商品/物流"时启动 |

### 14.4 P3 体验与性能优化

> 状态：P3 共 7 项，部分已在 14.1.5 列表导出（14.4.5）中部分完成。P3 排期最末，仅在前述 P0/P1/P2 全部稳定后启动；每月迭代 1-2 项即可。

| # | 需求 | 说明 | 状态 |
|---|------|------|------|
| 1 | 批量操作 | 各列表页目前仅支持单条操作，需补批量审核、批量删除、批量打标签 | **未开始** |
| 2 | 暗黑模式完整度 | 框架已支持，但抽屉、详情弹窗、图表等场景需逐页检查暗色适配 | **未开始** |
| 3 | 前端错误边界 | 已有路由级 403/404/500，缺组件级错误捕获与降级渲染 | **未开始** |
| 4 | 大列表性能 | 审计日志、Telescope 条目等大数据量列表需虚拟滚动 / 图片懒加载 | **未开始** |
| 5 | 单元测试覆盖 | 持续补齐至 70%+ 覆盖率（项目规范要求） | **进行中**（当前 1209 tests / 2919 assertions 已绿，仍需补 70% 覆盖率指标）|
| 6 | API 文档同步 | 新增接口同步发布至 Apifox，保持文档与代码一致 | **进行中**（14.1.1/14.2.5 已全量发布，沉淀发布规范）|
| 7 | 性能基准测试 | 建立关键接口的基准测试与压测报告，为容量规划提供依据 | **未开始** |



### 14.6 建议实施顺序

> 与 14.1-14.4 P 级别映射：P0 第一批 → P0 第二批 → P1 第一批 → P1 第二批 → P1 第三批 → P2 选做 → P3 长期。每个阶段开始前必须确认上一阶段所有 ✅ 项已上线稳定。

**第一阶段（P0 必达 · 上线前）**
1. ~~Banner / 轮播图管理（14.1.1）~~ ✅ 2026-08-27
2. ~~管理员登录日志（14.1.2）~~ ✅ 2026-08-27
3. ~~列表数据导出（14.1.5）~~ ✅ 2026-08-27
4. 系统公告 / 站内信群发（14.1.3）— **本阶段起点**
5. 敏感词过滤（14.1.4）
6. 安全基线加固（14.1.6 剩余项）

**第二阶段（P1 业务闭环）**
7. ~~用户标签与分群（14.2.4）~~ ✅ 2026-08-28（含分群异步任务前端列表页）
8. 内容 / 帖子管理（14.2.1）
9. 举报管理（14.2.2）
10. 业务通知联动（14.2.3）
11. 业务数据看板（14.2.6）
12. 敏感操作二次验证（14.2.5）
13. 业务定时任务管理（14.2.7）

**第三阶段（P2 业务扩展 · 按需选做）**
14. 用户钱包（技术方案见第 8 章，分 4 个 Phase 交付）
15. 支付与订单
16. 优惠券 / 活动管理
17. 签到与任务体系
18. 邀请与分销
19. 工单 / 客服系统
20. 前台用户端
21. 国际化落地
22. 地区数据校验

**第四阶段（P3 体验与性能 · 长期迭代）**
23. 批量操作、暗黑模式完整度、前端错误边界、大列表性能、单元测试覆盖、API 文档同步、性能基准测试（每月迭代 1-2 项）

---

### 14.6.1 P 级别优先级总览

| P 级别 | 总项 | 已完成 | 进行中 | 未开始 | 下一行动 |
|--------|------|--------|--------|--------|----------|
| **P0** | 6 | 3 ✅ | 1 🔄（14.1.3 公告群发，部分完成） | 2（14.1.4 敏感词 / 14.1.6 安全加固） | 启动 14.1.3 公告群发主模块 |
| **P1** | 7 | 1 ✅ | 0 | 6 | 启动 14.2.1 内容/帖子管理 |
| **P2** | 9 | 0 | 0 | 9 | 技术方案已就绪（第 8 章「用户钱包」），待 P0/P1 收口后启动 Phase 1 |
| **P3** | 7 | 0 | 2（14.4.5 测试覆盖 / 14.4.6 API 文档同步） | 5 | 持续推进，与每次新功能同步发布 |
| **合计** | 29 | 4 | 3 | 22 | — |

### 14.7 技术演进方向（长期）

- [ ] 更多认证方式（OAuth2 Server、Socialite 多平台社交登录）
- [ ] GraphQL 支持（与现有 RESTful 并行）
- [ ] AI 工具扩展（更多业务场景的 Function Calling）
- [ ] 微服务架构支持（服务拆分与服务间通信规范）
- [ ] 多云部署支持
- [ ] 可视化代码生成工具（CRUD 模块一键生成前后端代码）

## 15. 附录

### 15.1 默认账号
- 超级管理员：用户名 `admin`，密码 `12345678`（由迁移 `2026_07_31_111934_seed_initial_admin_data.php` 创建）

### 15.2 相关文档
- Laravel 官方文档：https://laravel.com/docs
- Laravel Skel Forge README.md
- 前端项目：laravel-skel-admin（Vue 3 SPA 管理后台）

### 15.3 联系方式
- 作者：Tongle Xu <xutongle@msn.com>

---

**文档版本**：v2.8
**最后更新**：2026-09-09
**维护人员**：Tongle Xu
