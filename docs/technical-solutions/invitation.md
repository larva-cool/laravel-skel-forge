# 邀请系统技术方案

## 1. 设计目标与定位

邀请系统用于记录用户之间的邀请关系，是后续邀请奖励、分销结算、拉新统计等功能的基础数据层。本系统以**轻量、通用、可扩展**为设计原则，仅记录邀请关系本身，奖励发放等业务逻辑通过事件 + 监听器的方式解耦。

### 1.1 设计原则

| 原则 | 说明 |
|------|------|
| **关系唯一** | 同一被邀请人只能绑定一个邀请人，绑定后不可变更 |
| **多态设计** | 邀请人与被邀请人均采用多态关联，支持未来扩展到 Admin 邀请、第三方账号邀请等场景 |
| **仅记事实** | 模型只记录邀请关系，不承载奖励、状态等业务字段，保持数据层纯粹 |
| **事件驱动** | 邀请关系建立后触发事件，奖励/统计等逻辑由监听器处理，便于扩展 |

---

## 2. 数据模型

### 2.1 数据表：invite_records

邀请记录表，每条记录代表一个邀请关系。

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | BIGINT UNSIGNED PK | 主键 |
| `inviter_id` | BIGINT UNSIGNED | 邀请人 ID |
| `inviter_type` | VARCHAR(64) | 邀请人类型（多态类型别名，如 `user`、`admin`） |
| `invitee_id` | BIGINT UNSIGNED | 被邀请人 ID |
| `invitee_type` | VARCHAR(64) | 被邀请人类型（多态类型别名） |
| `description` | VARCHAR(255) | 描述/备注，如「通过邀请码注册」 |
| `created_at` | TIMESTAMP NULL | 创建时间 |

#### 索引

| 索引名 | 字段 | 类型 | 说明 |
|--------|------|------|------|
| `idx_inviter` | `inviter_id, inviter_type, created_at` | 普通索引 | 查询某邀请人的邀请列表 |
| `uniq_invitee` | `invitee_id, invitee_type` | 唯一索引 | 同一被邀请人只能有一条邀请记录 |

---

### 2.2 模型：InviteRecord

**命名空间**：`App\Models\Invite\InviteRecord`
**表名**：`invite_records`

#### 关联

```php
/**
 * 邀请人（多态）
 */
public function inviter(): MorphTo

/**
 * 被邀请人（多态）
 */
public function invitee(): MorphTo
```

#### Model 结构

- 使用 `#[Table]`、`#[Fillable]` 等属性注解（对齐项目现有风格）
- `inviter_id`、`inviter_type`、`invitee_id`、`invitee_type`、`description` 为 fillable
- 无 `updated_at`（模型中设置 `const UPDATED_AT = null`）

---

### 2.3 多态映射

在 `config/morph_maps.php` 中已注册的类型均可作为邀请方/被邀请方：

| 类型别名 | 模型类 | 典型场景 |
|----------|--------|---------|
| `user` | `App\Models\User` | 用户邀请用户（主流场景） |
| `admin` | `App\Models\Admin\Admin` | 后台管理员手动添加邀请关系 |

---

## 3. 核心流程

### 3.1 邀请关系建立（注册时）

```
用户注册携带 invite_code
        ↓
校验邀请码是否存在（user_extras.invite_code）
        ↓
通过邀请码反查邀请人 user_id
        ↓
创建 InviteRecord 记录（inviter=邀请人, invitee=新用户）
        ↓
触发 InviteRelationshipCreated 事件
        ↓
监听器处理奖励发放等后续逻辑
```

**幂等保障**：依靠 `uniq_invitee` 唯一索引，同一用户重复触发也只会有一条邀请记录。

---

## 4. 事件系统

### 4.1 事件列表

| 事件类 | 触发场景 | 携带数据 |
|--------|---------|---------|
| `Events\Invite\InviteRelationshipCreated` | 邀请关系建立成功 | `inviteRecord`（邀请记录模型实例） |

### 4.2 与现有事件的关系

现有 `InviteRegistered` 事件（`app/Events/User/InviteRegistered.php`）携带 `$user` 和 `$inviteCode`，用于通知"有人用邀请码注册了"。

新的 `InviteRelationshipCreated` 事件聚焦于"邀请关系已建立"，是更底层的事实事件。两者职责区分：

- `InviteRegistered` — 注册入口事件，携带邀请码原始信息
- `InviteRelationshipCreated` — 关系落地事件，携带已持久化的邀请记录

---

## 5. API 设计（规划中）

> 本节为后续接口规划，本期仅实现数据模型。

### 5.1 前台用户端 API

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/invite/my-code` | 获取我的邀请码 |
| GET | `/api/v1/invite/records` | 我的邀请记录列表（分页） |
| GET | `/api/v1/invite/stats` | 邀请统计（邀请人数、累计奖励等） |

### 5.2 后台管理 API

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/invite-records/data` | 邀请记录分页列表 |
| GET | `/admin/invite-records/{id}` | 邀请记录详情 |

---

## 6. 与现有系统的集成点

### 6.1 User 模型

通过 trait（如 `HasInvitation`）为 User 模型追加：

```php
// 我邀请的人
$this->invitees(): MorphMany  // 通过 InviteRecord 的 inviter 反向关联

// 我的邀请人
$this->inviter(): MorphOne    // 通过 InviteRecord 的 invitee 反向关联

// 快捷方法
$this->hasInviter(): bool
$this->getInviteCode(): string
```

### 6.2 注册流程

在 `RegisterController` 中，收到 `invite_code` 后：
1. 现有逻辑：触发 `InviteRegistered` 事件
2. 新增逻辑：通过邀请码找到邀请人，创建 `InviteRecord`，触发 `InviteRelationshipCreated` 事件

### 6.3 奖励系统（暂缓）

邀请奖励（金币/积分/VIP）后续可通过监听 `InviteRelationshipCreated` 事件处理，不写入邀请记录表本身。奖励类型枚举 `CoinType::TYPE_INVITE_REGISTER` 和 `PointType::TYPE_INVITE_REGISTER` 已预置，待需要时接入。

---

## 7. 实施阶段

| 阶段 | 内容 | 交付物 |
|------|------|--------|
| **Phase 1 数据模型** | `invite_records` 表迁移 + `InviteRecord` 模型 + 多态映射 + 工厂 + 单元测试 | 可查询、可写入的邀请记录数据层 |
| **Phase 2 关系建立** | 注册流程集成（创建邀请关系）+ `InviteRelationshipCreated` 事件 + 功能测试 | 邀请关系可正常建立 |
| **Phase 3 查询接口** | 前台「我的邀请码/邀请列表/统计」+ 后台邀请记录管理 + 权限菜单 | 前后端查询能力完整 |
| **Phase 4 文档与发布** | Apifox 全量发布 + README 更新 + 测试覆盖率达标 | 生产可用 |

> **暂缓项**（后续按需启动）：
> - ~~邀请奖励发放~~（金币/积分/VIP 奖励、双向奖励）
> - ~~与用户任务系统联动~~（触发 `invite_user` 任务进度）

---

## 8. 代码结构（规划）

```
app/
├── Enums/
│   └── Invite/
│       └── InviteType.php          # 邀请类型枚举（预留，本期可空）
├── Models/
│   └── Invite/
│       └── InviteRecord.php        # 邀请记录模型
├── Events/
│   └── Invite/
│       └── InviteRelationshipCreated.php
├── Services/
│   └── InviteService.php           # 邀请关系建立编排
├── Support/
│   └── Traits/
│       └── HasInvitation.php       # User 模型的邀请关联 trait
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/
│   │   │   └── InviteController.php
│   │   └── Admin/
│   │       └── InviteRecordController.php
│   ├── Requests/
│   │   └── Admin/Invite/
│   └── Resources/
│       ├── Admin/Invite/
│       └── Api/V1/Invite/
database/
├── migrations/
│   └── xxxx_create_invite_records_table.php
└── factories/
    └── Invite/
        └── InviteRecordFactory.php
tests/
└── Feature/
    ├── Invite/
    │   ├── InviteRelationshipTest.php
    │   └── InviteRewardTest.php
    └── Admin/
        └── InviteRecordControllerTest.php
```
