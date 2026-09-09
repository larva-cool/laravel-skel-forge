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

### 2.1 数据表：invite_codes

邀请码表，每条记录代表一个主体（User、Admin 等）持有的唯一邀请码。邀请码通过多态关联归属主体，从原 `user_extras.invite_code` 字段抽取独立建模。

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | BIGINT UNSIGNED PK | 主键 |
| `code` | VARCHAR(26) | 邀请码（小写 ULID，全局唯一） |
| `owner_id` | BIGINT UNSIGNED | 所属主体 ID |
| `owner_type` | VARCHAR(64) | 所属主体类型（多态类型别名，如 `user`、`admin`） |
| `created_at` | TIMESTAMP NULL | 创建时间 |
| `updated_at` | TIMESTAMP NULL | 更新时间 |

#### 索引

| 索引名 | 字段 | 类型 | 说明 |
|--------|------|------|------|
| `uniq_code` | `code` | 唯一索引 | 邀请码全局唯一 |
| `uniq_owner` | `owner_id, owner_type` | 唯一索引 | 一个主体仅持有一个邀请码 |

---

### 2.2 模型：InviteCode

**命名空间**：`App\Models\Invite\InviteCode`
**表名**：`invite_codes`

#### 关联

```php
/**
 * 所属主体（多态）
 */
public function owner(): MorphTo
```

#### Model 结构

- 使用 `#[Table]`、`#[Fillable]` 等属性注解（对齐项目现有风格）
- `code`、`owner_id`、`owner_type` 为 fillable
- `creating` 钩子在未显式指定 `code` 时自动生成小写 ULID；`generateUniqueCode()` 应用层查重 + DB 唯一索引双重兜底
- 用户创建时由 `UserObserver` 自动为其创建邀请码；用户硬删除时一并清理

---

### 2.3 数据表：invite_records

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

### 2.4 模型：InviteRecord

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

### 2.5 多态映射

在 `config/morph_maps.php` 中注册的类型均可作为邀请方/被邀请方，邀请码模型自身也注册了多态别名：

| 类型别名 | 模型类 | 典型场景 |
|----------|--------|---------|
| `user` | `App\Models\User` | 用户邀请用户（主流场景） |
| `admin` | `App\Models\Admin\Admin` | 后台管理员手动添加邀请关系 |
| `invite_code` | `App\Models\Invite\InviteCode` | 邀请码多态归属 |
| `invite_record` | `App\Models\Invite\InviteRecord` | 邀请记录多态关联 |

---

## 3. 核心流程

### 3.1 邀请关系建立（注册时）

```
用户注册携带 invite_code
        ↓
校验邀请码是否存在（invite_codes.code）
        ↓
通过邀请码反查所属主体 owner（多态，即邀请人）
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

旧版 `InviteRegistered` 事件（`app/Events/User/InviteRegistered.php`）仅作为占位事件存在，未被任何地方触发，**已废弃**。

新的 `InviteRelationshipCreated` 事件聚焦于"邀请关系已建立"，是更底层的事实事件。`RegisterController` 中收到邀请码后直接调用 `InviteService::createRelationshipByCode()`，由该服务内部负责创建记录并触发事件。

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

通过 trait（如 `HasInvitation`）为 User、Admin 等模型追加：

```php
// 我的邀请码（多态归属，morph 名 owner，一个主体一个码）
$this->inviteCode(): MorphOne

// 我邀请的人
$this->invitees(): MorphMany  // 通过 InviteRecord 的 inviter 反向关联

// 我的邀请人
$this->inviterRelation(): MorphOne    // 通过 InviteRecord 的 invitee 反向关联

// 快捷方法
$this->hasInviter(): bool
$this->getInviteCode(): string
```

用户创建时 `UserObserver` 自动创建 InviteCode，用户硬删除时一并清理；UserExtra 不再承载邀请码。

### 6.2 注册流程

在 `RegisterController` 中，收到 `invite_code` 后：
1. 表单校验：`Rule::exists(InviteCode::class, 'code')` 校验邀请码有效
2. 新增逻辑：`InviteService` 通过邀请码反查 owner（邀请人），创建 `InviteRecord`，触发 `InviteRelationshipCreated` 事件
3. 邀请码无效、自邀或已存在邀请关系时静默跳过，不影响注册主流程

### 6.3 奖励系统（暂缓）

邀请奖励（金币/积分/VIP）后续可通过监听 `InviteRelationshipCreated` 事件处理，不写入邀请记录表本身。奖励类型枚举 `CoinType::TYPE_INVITE_REGISTER` 和 `PointType::TYPE_INVITE_REGISTER` 已预置，待需要时接入。

---

## 7. 实施阶段

| 阶段 | 内容 | 交付物 | 状态 |
|------|------|--------|------|
| **Phase 1 数据模型** | `invite_codes` / `invite_records` 表迁移 + `InviteCode`、`InviteRecord` 模型 + 多态映射 + 工厂 + 单元测试 | 邀请码与邀请关系数据层 | ✅ 已完成 |
| **Phase 2 关系建立** | 用户创建自动发放邀请码 + 注册流程集成（创建邀请关系）+ `InviteRelationshipCreated` 事件 + 功能测试 | 邀请码自动生成、邀请关系可正常建立 | ✅ 已完成 |
| **Phase 3 查询接口** | 前台「我的邀请码/邀请列表/统计」+ 后台邀请记录管理 + 权限菜单 | 前后端查询能力完整 | ⏳ 待启动 |
| **Phase 4 文档与发布** | Apifox 全量发布 + README 更新 + 测试覆盖率达标 | 生产可用 | ⏳ 待启动 |

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
│       ├── InviteCode.php          # 邀请码模型（多态归属 owner）
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
│   ├── xxxx_create_invite_codes_table.php
│   └── xxxx_create_invite_records_table.php
└── factories/
    └── Invite/
        ├── InviteCodeFactory.php
        └── InviteRecordFactory.php
tests/
├── Unit/
│   ├── Models/Invite/
│   │   ├── InviteCodeTest.php
│   │   └── InviteRecordTest.php
│   ├── Models/User/HasInvitationTest.php
│   ├── Observers/UserObserverTest.php
│   └── Services/InviteServiceTest.php
└── Feature/
    ├── Invite/
    │   ├── InviteRelationshipTest.php
    │   └── InviteRewardTest.php
    └── Admin/
        └── InviteRecordControllerTest.php
```
