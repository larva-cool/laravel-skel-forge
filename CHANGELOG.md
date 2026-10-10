# Changelog

Laravel Skel Forge 版本变更记录。格式参考 [Keep a Changelog](https://keepachangelog.com/)，版本号遵循语义化版本（SemVer）。

> v1.0.0 为首个正式版本。此前未维护变更日志，故本条目汇总该版本的核心能力，并列出发布前的关键新增。

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
