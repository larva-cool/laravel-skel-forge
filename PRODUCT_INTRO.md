# Laravel Skel Forge 产品介绍

> 企业级 API 骨架 + 配套中后台前端一体化解决方案

---

## 一句话定位

**Laravel Skel Forge（LSF）** 是一套基于 Laravel 13 的 **企业级 API 骨架 + 配套中后台前端（Vue 3 + Element Plus）** 一体化方案，帮助技术团队在数小时内——而非数周——搭起一个架构规范、权限完备、可观测、可扩展的业务后台。

---

## 它解决什么问题

- 每个新项目都从零搭认证、RBAC、文件上传、监控——**重复造轮子**，且质量参差。
- 后台代码风格混乱、分层不清，**难以维护与交接**。
- 权限、审计、日志这些"看不见但必须有"的能力经常被遗漏，**上线后补不起**。
- 前后端分离项目中，前端路由与后端权限**对不齐**，菜单/按钮级控制各自为政。

LSF 把上述基础设施一次性沉淀为标准模板，新业务只需聚焦于"业务模型 + 业务逻辑"。

---

## 核心特性

### 1. 双端认证体系
- 管理员（`admin`）与前台用户（`web`）**双 Guard 隔离**，互不越权。
- 基于 Laravel Sanctum 的 Token 鉴权；管理员令牌 24h 过期、用户令牌按设置过期（默认 30 天）。
- 支持用户名 / 邮箱 / 手机号登录，微信公众号授权登录、Apple 登录（管理员端预留）。
- 短信 / 邮件验证码、密码重置、联系方式重置、登录频率限制。

### 2. 完备的 RBAC 权限
- 基于 `spatie/laravel-permission` v8：角色 → 权限 → 菜单三级联动。
- 后端**路由级 + 方法级**双重权限校验。
- 前端按权限**自动过滤菜单与按钮**，所见即所权。

### 3. 后台管理全家桶
- 用户 / 管理员管理：软删除回收站、登录历史、社交账号绑定、**积分 / 金币流水**、余额调整、VIP 延期。
- 配置中心：多数据类型（string/int/float/bool/json）、分组查看、批量更新。
- 菜单管理（目录/菜单/按钮树）、数据字典（带标签样式）、省市区地区库。
- 应用与版本管理：安卓 / iOS / 鸿蒙多平台，草稿 / 发布 / 撤回 / 废弃状态机。
- 附件与上传：文件 / 图片 / 视频 / APK 上传，兼容 S3 协议直传 Token（火山引擎 TOS 等）。
- 站内通知：列表、未读、标记已读、批量已读、清理。

### 4. 运营能力
- 评论管理：列表过滤、审核（通过 / 驳回 / 待审）、回复链下钻、导出。
- 用户反馈：统计、详情（含附件与客户端信息）、处理流转、导出。

### 5. 开箱即用的可观测性
- **Laravel Pulse** 性能监控：服务器 / 队列 / Reverb 连接 / 缓存 / 异常 / 慢查询 / 慢请求 / 慢任务。
- **Laravel Telescope** 调试面板：请求 / 异常 / 查询全量回溯。
- **Horizon** 队列管理：任务、失败重试、批处理、监控标签。
- 调度任务日志、操作审计日志（中间件自动记录路径 / 参数 / 耗时 / IP / UA）、全局登录历史——全部接入后台界面，无需另搭。

### 6. 内置 AI 助手
- 后台集成对话式 AI，支持多轮会话、会话管理。
- 内置**人机确认回路（human-in-the-loop）**：敏感操作经 `approve` 接口二次确认后执行，避免模型越权直写。

### 7. 安全基线
- CORS 来源**白名单**（非通配符 `*`）。
- 全局安全响应头：`X-Frame-Options` / `X-Content-Type-Options` / `Referrer-Policy` / `X-XSS-Protection` / `Permissions-Policy` / `Content-Security-Policy`（frame-ancestors / base-uri 收敛）。
- HSTS 仅在 HTTPS 环境启用，本地开发不锁定。

### 8. 数据导出
- 列表数据 Excel 导出：用户、积分流水、金币流水、评论、反馈、审计日志，配套单元测试。

### 9. 现代工程架构
- DDD 分层、严格类型声明（strict_types）、RESTful 资源约定。
- 队列（Horizon）、事件 / 监听、实时广播（Reverb WebSocket）。
- 全量测试覆盖（当前 **1093 项测试** 全绿）。

---

## 技术栈

| 层 | 选型 |
|----|------|
| 后端框架 | Laravel 13（PHP 8.4） |
| 鉴权 | Sanctum 4 + spatie/laravel-permission 8 |
| 实时 | Reverb（WebSocket）+ Laravel Echo |
| 队列 / 监控 | Horizon + Pulse + Telescope |
| 搜索 | Scout + Meilisearch |
| 对象存储 | Flysystem（兼容 S3，火山引擎 TOS） |
| 第三方 | EasyWeChat（微信）、EasySMS（短信） |
| 前端 | Vue 3 + TypeScript + Element Plus（独立仓库 `laravel-skel-admin`，Pear Admin 风格） |
| 数据库 | MySQL（SQLite 用于测试） |

---

## 功能模块一览

**认证与授权**：管理员登录 / 微信登录 / Token 管理 / 个人信息 / 改密 / 角色权限分配 / 登录历史

**用户与组织**：用户管理（回收站 / 状态 / 余额 / VIP / 积分金币流水）、管理员管理、角色管理、菜单管理、配置中心、字典、地区

**内容与运营**：评论审核、用户反馈

**资产与发布**：附件 / 上传、应用管理、应用版本管理、通知中心

**观测与治理**：Pulse 监控、Telescope 调试、Horizon 队列、调度日志、操作审计、登录历史

**智能化**：AI 助手（对话 + 人机确认）

**平台能力**：数据导出、安全头 / CORS、健康检查

---

## 快速开始

```bash
# 环境要求：PHP >= 8.4、Composer、MySQL、Node 22（前端）
git clone <your-repo> laravel-skel-forge
cd laravel-skel-forge
composer install
cp .env.example .env && php artisan key:generate
# 配置数据库与缓存后
php artisan migrate --seed
php artisan serve
```

配套前端：

```bash
git clone <your-repo-frontend> laravel-skel-admin
cd laravel-skel-admin && pnpm install && pnpm dev
```

> ⚠️ 安装后请**立即修改默认管理员凭据**，并复核 `user.token_expiration` 等安全相关设置项。

---

## 与同类方案的区别

| 对比项 | 若依 / FastAdmin | 纯 API 脚手架（Breeze/Jetstream） | **Laravel Skel Forge** |
|--------|------------------|-----------------------------------|------------------------|
| 形态 | PHP 单体内置后台 | 仅认证壳 | **前后端分离 API 骨架 + 独立 SPA** |
| RBAC | 有 | 无 | **完整路由级 + 方法级 + 前端联动** |
| 运营 / 监控 | 部分 | 无 | **Pulse/Telescope/Horizon/审计 全接入** |
| 适用规模 | 中小型单体 | 演示 / 起步 | **中大型、多端、SaaS** |
| 重复造轮子 | 仍需补监控/导出 | 几乎全补 | **省掉约 80% 基础设施代码** |

---

## 路线图（规划中）

以下功能已在产品路线图中规划，当前**尚未实现**，将随版本迭代交付：

- 首页 Banner 轮播图管理
- 系统公告 / 消息群发
- 敏感词过滤（DFA 匹配 + Redis 缓存）

---

## 许可与商用（⚠️ 发布前必须明确）

本项目源码中已声明 **`This is NOT a freeware, use is subject to license terms`**。正式对外发布前，需明确并公示以下事项（建议写入独立的 `LICENSE` / 授权说明页）：

- **授权模式**：按域名 / 按项目 / 买断 / 年度订阅，择一或组合。
- **交付范围**：是否提供完整源码、是否允许二次分发、是否允许用于 SaaS 商用。
- **前后端同权**：配套前端 `laravel-skel-admin` 是否与后端采用同一授权条款。
- **免责与责任边界**：数据丢失、安全事件的责任划分。

> 未明确授权条款前，不建议以"开源 / 免费"口径对外宣传。

---

## 发布前检查清单

- [ ] 确定并公示授权模式（LICENSE 页）
- [ ] 收口已知安全挂起项（AI 工具 RBAC 校验、部署钩子密钥）后再做对外承诺
- [ ] 准备默认管理员凭据的初始化与改密引导
- [ ] 撰写独立的前端 `laravel-skel-admin` 介绍与联调文档
- [ ] 完成部署文档（Herd / 服务器 / 队列 Worker / Reverb 常驻进程）
- [ ] 补充 API 文档（OpenAPI / Apifox）与示例业务模块
