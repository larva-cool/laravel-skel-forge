# Changelog

Laravel Skel Forge 版本变更记录。格式参考 [Keep a Changelog](https://keepachangelog.com/)，版本号遵循语义化版本（SemVer）。

> v1.0.0 为首个正式版本。此前未维护变更日志，故本条目汇总该版本的核心能力，并列出发布前的关键新增。

## [Unreleased]

### Added

- 新增火山引擎 AI 服务依赖，扩展 `config/ai.php` 配置以支持音视频生成
- 为多个 AI 代理添加 300 秒超时配置

### Fixed

- 为 AI 生成图片添加超时设置，避免生图请求长时间挂起

## [v1.0.8] - 2026-10-22

### Added

- 公众号智能客服知识库自动回复能力：`WechatAssistant` 代理、公众号公开知识库检索工具 `SearchPublicKnowledgeBase`、文本消息异步回复 Job（`ReplyWechatTextMessageJob`）及配套测试
- 后台初始数据新增财务分析菜单

## [v1.0.7] - 2026-10-22

### Added

- AI 知识库管理功能：知识文档/分块模型、文档异步处理 Job、知识库服务（`KnowledgeService`）、管理端文档管理与语义检索接口，以及 AI 语义检索工具 `SearchKnowledgeBase`
- AI 内容辅助服务及运营审核相关功能（含管理端接口与单元/功能测试）
- 用户分群创建工具及相关功能
- 分群规则聚合查询支持存在性、计数、求和类型，并补充多场景分段规则评估测试
- 火山引擎与阿里图像模型配置

### Changed

- 重构 AI 配置结构，移除冗余配置项并调整模型分组
- 更新默认向量模型版本
- 放宽 AI 相关操作的脚本执行时限

### Fixed

- AI 相关接口执行时限放宽至 300 秒

## [v1.0.6] - 2026-10-21

### Added

- AI 聊天支持智能体选择、模型切换和文件上传
- AI 聊天模型管理功能（`ChatModel` 模型、管理端 CRUD 接口与种子数据），替换原有财务助手配置
- 管理员头像字段支持

### Changed

- 更新火山引擎相关环境变量与配置键名

### Fixed

- 修正火山方舟 AI 服务的驱动配置
- 放宽 AI 聊天流处理超时并移除智能体权限校验

## [v1.0.5] - 2026-10-19

### Added

- `PointHelper::incr` 新增过期时间控制参数

## [v1.0.4] - 2026-10-19

### Added

- AI 工具管理员权限校验机制，覆盖用户、聊天及多个业务工具
- 钱包、审计、反馈、实名认证查询 AI 工具及对应测试
- 管理员审核提现、实名认证及向用户发送站内通知的 AI 工具与通知类
- 运营管理相关 AI 工具及测试
- 财务助手聊天及系统工具功能
- 开放平台与协议管理相关工具及测试

### Changed

- 升级 `overtrue/easy-sms` 至 3.5，新增 `telescope-debugging` 配置项
- 新增 meilisearch 与 http-factory-guzzle 依赖
- 升级 `intervention/image` 至 4.4.0
- 更新 AI 默认文本模型配置

### Fixed

- 移除用户强制删除时冗余的会话清理逻辑

## [v1.0.3] - 2026-10-14

### Added

- Follow 和 Collection 观察者，同步更新来源的互动计数

### Changed

- 点赞统计逻辑迁移至 Like 观察者，完善用户强制删除清理
- 全量统一添加 `declare(strict_types=1)` 严格类型声明

### Removed

- 移除 Follow 和 Collection 模型的启动回调逻辑（由观察者接管）

## [v1.0.2] - 2026-10-14

### Added

- 关注功能：Follow 模型、策略、迁移、前后台接口及多语言支持
- 多态实名认证契约 `IdentityVerifiable` 与 `HasIdentity` Trait，支持其他主体接入认证功能

## [v1.0.1] - 2026-10-10

### Changed

- Visit 模型迁移至 Content 命名空间，并同步调整 VisitFactory

## [v1.0.0] - 2026-10-10

企业级后台管理 API 骨架首个正式版本。基于 Laravel 13 + PHP 8.4，采用 DDD 分层架构与 RESTful 规范，配套独立前端 SPA（laravel-skel-admin）。

### 核心能力

- **后台管理（`/admin`）**：认证授权与登录历史（含节流限速）、RBAC 角色权限、树形菜单、系统配置、省市区、字典、附件上传管理（中转/直传/重命名/移动/删除）、站内通知、应用版本发布、前台用户与钱包（充值/提现/流水，支持导出）、反馈/实名认证/评论/举报审核与联动处置、Banner/公告、任务系统、用户分群（手动 + 规则，每日 02:00 增量同步、批量打标超 1000 ID 转异步）、邀请关系、操作/调度/登录审计、仪表盘、AI 流式聊天、Pulse / Horizon / Telescope 监控。
- **前台 API（`/api/v1`）**：手机/邮箱注册登录、用户中心（资料/头像自动转 WebP/社交账号/流水/实名/注销）、钱包、多态点赞收藏评论（回复 + 艾特）、访问记录（PV/UV/最近浏览）、邀请关系与统计（注册携带 `invite_code` 自动建立）、任务中心、公开 Banner/公告、通知、地址、反馈、举报、微信绑定。

### Added（发布前关键新增）

- 定时任务管理：静态定时任务迁移至数据库、手动执行日志记录
- 关闭超时充值订单的 Artisan 命令
- 开放 API V1 路由与接口返回类型完善
- 后台仪表盘财务维度统计
- 举报功能（模型/枚举/策略/服务/前后台接口，支持隐藏评论、删除评论、冻结用户等联动处置）
- 访问记录 API（PV/UV、最近浏览）
- 同站/跨站请求伪造（CSRF）防护；火山引擎 TOS 配置支持 `open` 接口
- 用户头像修改自动转 WebP、社交账号绑定查询

### 技术栈

Laravel 13.8 · PHP 8.4 · Sanctum 4 · spatie/permission 8 · Horizon 5 · Reverb 1 · Octane 2 · Pulse 1 · Telescope 5 · Scout 11 · laravel/ai 0.10 · Redis · MySQL 8 / PostgreSQL / SQLite · Tailwind CSS v4 + Vite 8 · PHPUnit 12 · Pint 1

### 测试

1725 tests / 4507 assertions 全部通过。

### 许可证

AGPL-3.0-or-later
