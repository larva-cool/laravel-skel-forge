# 架构设计

## 1. DDD 分层

项目采用领域驱动设计思想，按业务领域组织代码：

| 层次 | 目录 | 职责 |
|------|------|------|
| **Models Layer** | `app/Models/` | 数据模型和业务逻辑，按领域分子目录（Admin/System/User/Coin/Point/Wallet） |
| **Services Layer** | `app/Services/` | 业务服务封装，处理跨模型事务逻辑（如附件台账+Storage一致性） |
| **Controllers Layer** | `app/Http/Controllers/` | HTTP 请求编排，调用 Service 和 Resource |
| **Resources Layer** | `app/Http/Resources/` | API 响应格式化 |
| **Requests Layer** | `app/Http/Requests/` | 请求参数校验 |

## 2. 请求流转

```
HTTP Request → Route → Middleware (Auth/Permission/Audit) → FormRequest Validation
→ Controller → Service (业务逻辑) → Model (Eloquent) → Database
                                            → Resource (响应格式化) → JSON Response
```

### AI 聊天特殊流转

```
POST /admin/chat → ChatController → AI Agent (laravel/ai) → Tool 调用 → SSE Stream 响应
                                            ↓ (敏感操作)
                                       审批等待 → POST /admin/chat/approve → 继续执行
```

## 3. 目录按领域划分

模型层按业务领域分子目录，实现高内聚低耦合：

| 目录 | 包含模型 |
|------|---------|
| `Admin/` | Admin、AdminMenu |
| `App/` | AppRelease、AppVersion |
| `Coin/` | CoinTrade |
| `Content/` | Collection、Comment、Like |
| `Operation/` | Announcement、Banner |
| `Point/` | PointRecord、PointTrade |
| `System/` | Area、Attachment、AuditLog、Dict、DictItem、Feedback、LoginHistory、MailCode、Permission、PersonalAccessToken、PhoneCode、Role、ScheduleLog、Setting、Social |
| `User/` | Address、IdentityVerification、UserExtra、UserProfile、UserStat |
| `UserSegment/` | Membership、Segment、SegmentTask |
| `Wallet/` | WalletTrade、Recharge、WithdrawOrder |

---

> 文档来源：PRD 第 4.3 节
