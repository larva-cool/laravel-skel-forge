# AI 助手

基于 `laravel/ai` 集成的后台 AI 助手能力，为管理员提供智能对话和工具调用能力。

## 功能说明

| 功能 | 说明 |
|------|------|
| SSE 流式对话 | 以 Server-Sent Events 实时推送 AI 响应，支持思考过程（reasoning）和文本输出 |
| 工具调用 | AI 可调用内置工具查询数据或执行操作（审计日志查询、验证码查询、系统设置读写、仪表盘统计、地区查询、系统信息、用户管理、网页抓取等） |
| 工具审批 | 敏感操作（如调整用户余额、VIP 延期、重置密码等）需管理员二次审批后继续执行 |
| 对话历史 | 按会话（Conversation）保存对话记录，支持查看历史会话、删除会话 |

## 内置 AI 工具

| 工具分类 | 工具名称 | 说明 |
|---------|---------|------|
| Audit | ListLoginHistories | 查询登录历史 |
| Audit | ListVerificationCodes | 查询验证码记录 |
| Settings | GetSetting | 获取系统设置 |
| Settings | SetSetting | 设置系统配置值 |
| Stats | GetDashboardStats | 获取仪表盘统计数据 |
| System | GetAreas | 查询地区数据 |
| System | SystemInfo | 获取系统信息 |
| Users | GetUser | 获取用户详情 |
| Users | ListUsers | 查询用户列表 |
| Users | AdjustUserBalance | 调整用户积分/金币余额（需审批） |
| Users | ExtendUserVip | 延长用户VIP（需审批） |
| Users | GetUserSocials | 获取用户第三方绑定 |
| Users | ResetUserContact | 重置用户联系方式（需审批） |
| Users | ResetUserPassword | 重置用户密码（需审批） |
| Users | SetUserStatus | 设置用户状态 |
| Web | WebFetch | 网页抓取 |

## SSE 事件类型

聊天接口通过 `text/event-stream` 逐行推送以下事件类型：

| 事件类型 | 说明 |
|---------|------|
| `text` | 文本增量输出 |
| `reasoning` | 思考过程展示 |
| `tool_call` | 工具调用信息 |
| `tool_approval_required` | 需要人工审批的敏感操作 |
| `error` | 错误信息 |
| `done` | 流结束标记 |

## 聊天请求流转

```
POST /admin/chat → ChatController → AI Agent (laravel/ai) → Tool 调用 → SSE Stream 响应
                                            ↓ (敏感操作)
                                       审批等待 → POST /admin/chat/approve → 继续执行
```

---

> 文档来源：PRD 第 3.4 节
