# Laravel Skel Forge 项目文档

> 本文档由 PRD 拆分而成，按模块组织，便于查阅和维护。

## 文档结构

### 一、项目概述
- [项目概述与产品定位](overview/project-overview.md) — 项目背景、目标、定位与核心价值

### 二、功能需求
- [用户系统](features/user-system.md) — 管理员用户系统、前台用户系统
- [认证与授权](features/auth-and-permission.md) — 认证体系、RBAC 权限管理
- [后台管理系统](features/admin-system.md) — 系统设置、运营管理、附件管理、监控、调试、队列、数据概览
- [AI 助手](features/ai-assistant.md) — SSE 流式对话、工具调用、工具审批、对话历史
- [核心服务](features/core-services.md) — 验证码服务、文件上传服务、系统监控

### 三、技术架构
- [技术栈](architecture/tech-stack.md) — 完整技术栈与版本
- [项目结构](architecture/project-structure.md) — 目录结构说明
- [架构设计](architecture/architecture-design.md) — DDD 分层、请求流转、AI 聊天流转

### 四、数据模型
- [核心数据表](database/core-tables.md) — 管理员、用户、菜单、配置、地区、验证码、登录历史、附件、审计日志、调度日志等
- [业务数据表](database/business-tables.md) — 字典、应用发布/版本、内容互动、钱包相关表

### 五、API 接口
- [响应格式与状态码](api/response-format.md) — 响应结构、HTTP 状态码规范
- [后台管理接口](api/admin-api.md) — `/admin` 前缀所有接口
- [前台 API v1 接口](api/api-v1.md) — `/api/v1` 前缀所有接口

### 六、技术方案
- [附件管理技术方案](technical-solutions/attachment-management.md) — 设计原则、代码结构、数据表、服务层、上传链路
- [钱包技术方案](technical-solutions/wallet.md) — 设计原则、代码结构、Trait、Helper、充值/提现链路、实施阶段

### 七、运维与开发
- [非功能性需求](operations/non-functional-requirements.md) — 性能、安全、可扩展性、可维护性、可靠性
- [开发流程](operations/development-guide.md) — 环境要求、快速开始、开发命令、代码规范、全局辅助函数
- [部署方案](operations/deployment.md) — 生产环境配置、部署方式、服务配置、备份策略
- [监控与维护](operations/monitoring.md) — 监控工具、日常维护
- [定时任务](operations/scheduled-tasks.md) — 定时任务清单与调度日志

### 八、路线图与附录
- [Roadmap 路线图](roadmap/roadmap.md) — P0/P1/P2/P3 需求规划、实施顺序、技术演进方向
- [附录](roadmap/appendix.md) — 默认账号、相关文档、联系方式

---

**文档版本**: v2.8
**最后更新**: 2026-09-09
**维护人员**: Tongle Xu
