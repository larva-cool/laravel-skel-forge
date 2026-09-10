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

### 4.2 与注册流程的协作

```
RegisterController 收到 invite_code
        ↓
触发 InviteRegistered 事件（同步，立即返回）
        ↓
InviteRegisteredListener（队列异步）
        ↓
调用 InviteService::createRelationshipByCode()
        ↓
创建 InviteRecord → 触发 InviteRelationshipCreated 事件
```

**设计意图**：
- `InviteRegistered` 是**业务入口事件**，表示"有人用邀请码注册了"，由注册流程触发
- `InviteRegisteredListener` 实现 `ShouldQueue`，走队列异步执行，不阻塞注册响应
- `InviteRelationshipCreated` 是**数据落地事件**，表示"邀请关系已写入数据库"，供奖励、任务等下游监听

---

## 5. API 设计

### 5.1 前台用户端 API

需 `auth:sanctum` 认证，响应为 Laravel 标准分页结构（`data` / `links` / `meta`）。

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/invite/records` | 我的邀请记录列表（分页，支持 `per_page`） |
| GET | `/api/v1/invite/stats` | 邀请统计（`invitee_count`、`has_inviter`） |

> 邀请码不单独提供接口，已包含在 `UserResource` 的 `invite_code` 字段中。

**邀请记录列表响应字段**：

| 字段 | 说明 |
|------|------|
| `id` | 记录 ID |
| `invitee.id` / `.name` / `.username` / `.avatar` | 被邀请人信息 |
| `description` | 描述/备注 |
| `created_at` | 邀请时间 |

### 5.2 后台管理 API

需 `auth:admin` 认证 + 对应权限。

| 方法 | 路径 | 权限 | 说明 |
|------|------|------|------|
| GET | `/admin/invite-records` | `invite-records.index` | 邀请记录分页列表 |
| GET | `/admin/invite-records/{id}` | `invite-records.show` | 邀请记录详情 |

**列表筛选参数**（由 `InviteRecordIndexRequest` 校验，`InviteRecord::scopeFilter` 执行）：

| 参数 | 类型 | 说明 |
|------|------|------|
| `inviter_type` | string | 邀请人类型（`user` / `admin`） |
| `inviter_id` | int | 邀请人 ID |
| `invitee_type` | string | 被邀请人类型（`user` / `admin`） |
| `invitee_id` | int | 被邀请人 ID |
| `description` | string | 描述模糊匹配 |
| `created_start` | date | 邀请时间起始 |
| `created_end` | date | 邀请时间截止（须 ≥ `created_start`） |
| `per_page` | int | 每页条数（1-100） |

**响应字段**：`id`、`inviter_type`、`inviter_id`、`inviter_name`、`invitee_type`、`invitee_id`、`invitee_name`、`description`、`created_at`

### 5.3 后台菜单与权限

菜单挂在「用户管理」目录下（`user.invite-record`，sort 4），权限由迁移文件自动生成：

| 权限 key | 说明 |
|----------|------|
| `invite-records.index` | 邀请记录列表（菜单权限） |
| `invite-records.show` | 查看详情（按钮权限） |

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
| **Phase 3 查询接口** | 前台「我的邀请列表/统计」+ 后台邀请记录列表与详情 + 筛选作用域 + 权限菜单 | 前后端查询能力完整 | ✅ 已完成 |
| **Phase 4 文档与发布** | Apifox 全量发布 + README 更新 | 生产可用 | ✅ 已完成 |

> **暂缓项**（后续按需启动）：
> - ~~邀请奖励发放~~（金币/积分/VIP 奖励、双向奖励）
> - ~~与用户任务系统联动~~（触发 `invite_user` 任务进度）
> - 测试覆盖率量化（需环境安装 Xdebug 或 PCOV 后执行 `php artisan test --coverage --min=70`）

---

## 7.1 Apifox 发布记录

项目：**Laravel Skel**（`8720052`），分支 `main`。

### 数据模型（Schema）

严格遵循模块隔离，Admin 与 Api 各自独立定义，禁止跨模块 `$ref`：

| Schema ID | 名称 | 模块 | 目录 |
|-----------|------|------|------|
| `312589523` | `InviteRecord` | Api (`8396001`) | 用户相关 (`20995717`) |
| `312590135` | `InviteRecordAdminResource` | Admin (`8396002`) | 用户相关 (`20885420`) |

### 接口

| 接口 ID | 名称 | 方法 | 路径 | 模块 | 目录 |
|---------|------|------|------|------|------|
| `513246901` | 获取我的邀请记录 | GET | `/api/v1/invite/records` | Api | V1/邀请接口 (`95504233`) |
| `513246906` | 获取邀请统计 | GET | `/api/v1/invite/stats` | Api | V1/邀请接口 (`95504233`) |
| `513246910` | 获取邀请记录列表 | GET | `/admin/invite-records` | Admin | 用户管理/邀请记录 (`95504235`) |
| `513246915` | 获取邀请记录详情 | GET | `/admin/invite-records/{inviteRecord}` | Admin | 用户管理/邀请记录 (`95504235`) |

### 分页规范

两个列表接口均按项目规范启用全局参数 `page` / `per_page`，并通过 `x-apifox-refs` 引用对应模块的 `EloquentPaginate` 后覆写 `data` 字段：

| 模块 | EloquentPaginate ID |
|------|---------------------|
| Api | `307396242` |
| Admin | `304922372` |

### 标签

- 前台：`邀请`、`分页接口`（仅列表）
- 后台：`邀请记录`、`分页接口`（仅列表）

---

## 8. 代码结构

```
app/
├── Models/
│   └── Invite/
│       ├── InviteCode.php                    # 邀请码模型（多态归属 owner）
│       └── InviteRecord.php                  # 邀请记录模型（含 scopeFilter）
├── Events/
│   ├── Invite/
│   │   └── InviteRelationshipCreated.php     # 数据落地事件
│   └── User/
│       └── InviteRegistered.php              # 业务入口事件
├── Listeners/
│   └── User/
│       └── InviteRegisteredListener.php      # 邀请注册监听器（队列异步）
├── Services/
│   └── InviteService.php                     # 邀请关系建立编排
├── Support/
│   └── Traits/
│       └── HasInvitation.php                 # 邀请关联 trait
├── Observers/
│   └── UserObserver.php                      # 用户创建时自动发放邀请码
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/InviteController.php       # 前台：records / stats
│   │   └── Admin/InviteRecordController.php  # 后台：index / show
│   ├── Requests/
│   │   └── Admin/Invite/
│   │       └── InviteRecordIndexRequest.php  # 后台列表筛选校验
│   └── Resources/
│       ├── Api/V1/InviteRecordResource.php   # 前台资源
│       └── Admin/InviteRecordResource.php    # 后台资源
config/
└── morph_maps.php                            # invite_code / invite_record 别名
routes/
├── api_v1.php                                # /api/v1/invite/*
└── admin.php                                 # /admin/invite-records/*
database/
├── migrations/
│   ├── 0001_01_01_000018_create_invite_codes_table.php
│   ├── 0001_01_01_000019_create_invite_records_table.php
│   └── 2026_10_31_111934_seed_initial_admin_data.php  # 菜单与权限
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
│   ├── Listeners/InviteRegisteredListenerTest.php
│   ├── Observers/UserObserverTest.php
│   └── Services/InviteServiceTest.php
└── Feature/
    ├── Api/V1/InviteControllerTest.php
    └── Admin/InviteRecordControllerTest.php
```
