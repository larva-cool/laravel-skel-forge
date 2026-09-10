# 用户任务系统技术方案

## 1. 设计目标与定位

用户任务系统是一套**通用、可扩展、事件驱动**的任务奖励引擎，用于驱动用户行为、提升活跃度和留存。业务方可通过配置任务模板（任务组 + 任务），并在关键业务节点触发对应事件，即可自动完成任务进度判定与奖励发放。

### 1.1 设计原则

| 原则 | 说明 |
|------|------|
| **配置驱动** | 任务类型、条件、奖励均在数据库中配置，无需改代码即可上线新任务 |
| **事件驱动** | 业务侧只负责发事件，任务系统监听事件并判定进度，解耦业务与任务 |
| **阶梯奖励** | 支持累计型阶梯任务（如"累计观看30秒/60秒/120秒"），一次行为可同时推进多阶 |
| **奖励可扩展** | 奖励类型通过 `reward_type` 区分，支持金币、积分、VIP 时长等，新增奖励类型无需改表结构 |
| **幂等安全** | 同一任务同一周期内只奖励一次，并发场景下依靠唯一索引 + 事务保证 |
| **通用骨架** | 作为骨架项目能力，提供基础任务类型（签到、邀请、完善资料等），业务项目按需扩展 |

### 1.2 与参考项目的差异

参考 `drama-forge-base` 的任务系统是面向短剧业务定制的（看剧、看广告等），本系统作为通用骨架项目，采用以下策略：

- 保留**任务组 + 任务 + 完成日志**的三层核心结构
- 保留**事件驱动 + 监听器判定**的架构模式
- 移除短剧业务特有的任务类型（看剧、视频广告、激励视频）
- 新增通用业务常用任务类型（签到、完善资料、邀请好友、每日登录、绑定手机/邮箱等）
- 奖励体系对齐本项目现有**金币 / 积分 / VIP** 三种虚拟资产，不再引入新资产类型
- 奖励发放统一通过 `CoinHelper` / `PointHelper` / VIP 能力，禁止直接操作余额字段

---

## 2. 核心概念与数据模型

### 2.1 核心实体

```
TaskGroup（任务组） ──1:N── Task（任务） ──1:N── TaskProgress（用户任务进度）
                                              └──1:N── TaskLog（完成/领取日志）
```

### 2.2 数据表设计

#### 2.2.1 任务组表 (task_groups)

按业务场景组织任务的分组容器，如"新手任务""每日任务""成就任务"。

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT PK | 主键 |
| name | VARCHAR(64) | 任务组名称，如"每日任务" |
| code | VARCHAR(64) UNIQUE | 任务组唯一标识，如 `daily` / `newbie` / `achievement` |
| description | VARCHAR(255) | 描述 |
| reset_type | VARCHAR(20) | 重置周期：`none`(不重置) / `daily`(每日) / `weekly`(每周) / `monthly`(每月) |
| visibility | TINYINT | 前台是否可见：0 不可见 1 可见 |
| status | TINYINT | 状态：0 禁用 1 启用 |
| sort | INT | 排序 |
| task_count | INT | 任务数（冗余） |
| created_at / updated_at | TIMESTAMP | 时间戳 |
| deleted_at | TIMESTAMP | 软删除 |

**索引**：`(status, sort)`、`code` 唯一索引。

#### 2.2.2 任务表 (tasks)

单个任务的定义，包含类型、条件、奖励。

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT PK | 主键 |
| group_id | BIGINT | 所属任务组 ID |
| name | VARCHAR(64) | 任务名称，如"每日签到" |
| code | VARCHAR(64) | 任务唯一标识（同组内唯一），如 `daily_sign_in` |
| type | VARCHAR(32) | 任务类型，见 `TaskType` 枚举 |
| description | VARCHAR(255) | 任务描述 |
| condition | JSON | 任务条件，见下方"条件结构" |
| target_count | INT DEFAULT 1 | 目标次数/数量（累计型任务用，1 表示一次性任务） |
| reward_type | VARCHAR(32) | 奖励类型：`coin` / `point` / `vip_days` / `none` |
| reward_amount | INT DEFAULT 0 | 奖励数量（金币数/积分数/VIP天数） |
| claim_mode | VARCHAR(20) | 领取方式：`auto`(达成自动发放) / `manual`(用户手动领取) |
| icon | VARCHAR(255) | 任务图标 URL（可选） |
| status | TINYINT | 状态：0 禁用 1 启用 |
| sort | INT | 排序 |
| log_count | INT | 完成次数（冗余统计） |
| created_at / updated_at | TIMESTAMP | 时间戳 |
| deleted_at | TIMESTAMP | 软删除 |

**索引**：`(group_id, status, sort)`、`(type, status)`、`code` 唯一索引。

**条件结构（condition JSON）**：

不同任务类型使用不同字段，通过 `AsTaskCondition` Cast 提供默认值：

```json
{
  "serial_days": 0,              // 连续天数（签到类）
  "target_value": 0,             // 目标值（数值累计类，如邀请人数）
  "completeness_threshold": 100  // 资料完整度阈值，百分比（完善资料类）
}
```

#### 2.2.3 用户任务进度表 (task_progress)

记录每个用户在每个任务上的当前进度，用于累计型任务的中间状态。一次性任务也写入一条记录表示已完成。

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT PK | 主键 |
| user_id | BIGINT | 用户 ID |
| task_id | BIGINT | 任务 ID |
| group_id | BIGINT | 任务组 ID（冗余，便于按组查询） |
| current_value | INT DEFAULT 0 | 当前进度值 |
| is_completed | TINYINT DEFAULT 0 | 是否已完成（达成目标） |
| claimed_at | TIMESTAMP NULL | 领取时间（manual 模式用） |
| period_key | VARCHAR(32) | 周期标识，如 `2026-09-09`(日) / `2026-W37`(周) / `2026-09`(月) / `all`(永久) |
| completed_at | TIMESTAMP NULL | 完成时间 |
| created_at / updated_at | TIMESTAMP | 时间戳 |

**唯一索引**：`uniq_user_task_period (user_id, task_id, period_key)` — 同一用户同一任务同一周期只能有一条进度记录。

**索引**：`(user_id, group_id, period_key)`、`(task_id, is_completed)`。

#### 2.2.4 任务完成日志表 (task_logs)

记录用户完成任务并领取奖励的流水，用于审计和去重。

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT PK | 主键 |
| user_id | BIGINT | 用户 ID |
| task_id | BIGINT | 任务 ID |
| group_id | BIGINT | 任务组 ID（冗余） |
| reward_type | VARCHAR(32) | 奖励类型（冗余快照） |
| reward_amount | INT | 奖励数量（冗余快照） |
| reward_trade_id | BIGINT NULL | 奖励关联的交易记录 ID（coin_trade_id / point_trade_id 等） |
| period_key | VARCHAR(32) | 周期标识 |
| claimed_by | VARCHAR(20) | 领取触发方：`system`(自动) / `user`(手动) |
| created_at | TIMESTAMP | 完成时间 |

**唯一索引**：`uniq_user_task_period (user_id, task_id, period_key)` — 保证同一周期同一任务只发放一次奖励。

**索引**：`(user_id, created_at)`、`(task_id, created_at)`、`(group_id, created_at)`。

---

## 3. 任务类型体系

### 3.1 TaskType 枚举

| 枚举值 | 值 | 说明 | 重置周期建议 |
|--------|-----|------|-------------|
| `SIGN_IN` | `sign_in` | 签到任务 | daily |
| `COMPLETE_PROFILE` | `complete_profile` | 完善资料 | none（一次性） |
| `FOLLOW_OFFICIAL_ACCOUNT` | `follow_official_account` | 关注公众号 | none（一次性） |
| `INVITE_USER` | `invite_user` | 邀请好友注册 | none（累计） |
| `DAILY_LOGIN` | `daily_login` | 每日登录 | daily |
| `CUSTOM_ACTION` | `custom_action` | 自定义行为事件 | 按业务而定 |

> `CUSTOM_ACTION` 为兜底类型，业务方可通过事件名 + `target_count` 快速接入新任务，无需新增枚举值。

### 3.2 各类型任务判定逻辑

#### 3.2.1 签到任务（sign_in）

- **触发事件**：`UserSignedIn`（携带 `user_id`、`serial_days` 连续签到天数）
- **实现方式**：**多任务 + serial_days 条件匹配**（方案A）— 每个连续签到天数对应一条独立任务，由运营在后台配置。
- **判定逻辑**：
  1. 监听 `UserSignedIn` 事件，获取当前用户的连续签到天数 `serial_days`
  2. 查询所有 `type = sign_in` 且 `status = 1` 的任务
  3. 遍历任务，按 `condition.serial_days == serial_days` 精确匹配
  4. 匹配成功则发放对应奖励（`claim_mode = auto` 自动发放）
- **period_key 策略**：
  - **每日签到基础奖励**（`serial_days = 0` 表示每日签到通用奖励）：`period_key = Y-m-d`，每日可领一次
  - **连续签到阶梯奖励**（`serial_days > 0`）：`period_key = sign_in_day_{serial_days}`，同一连续天数一生只领一次（断签重来也不重复发放，避免刷奖励）
- **奖励模式**：auto（自动发放）
- **后台配置示例**：

  | 任务名称 | condition.serial_days | reward_type | reward_amount |
  |---------|----------------------|-------------|---------------|
  | 每日签到 | 0 | coin | 10 |
  | 连续签到第2天 | 2 | coin | 15 |
  | 连续签到第3天 | 3 | coin | 20 |
  | 连续签到第7天 | 7 | coin | 100 |
  | 连续签到第30天 | 30 | coin | 500 |

- **注意**：签到任务不走通用的 `current_value >= target_count` 判定流程，由 `SignInTaskListener` 特殊处理。`condition.serial_days = 0` 的任务表示"每日签到"基础奖励，每天都能领；`serial_days > 0` 表示连续签到第 N 天的阶梯奖励，一生只领一次。

#### 3.2.2 完善资料任务（complete_profile）

- **触发事件**：`UserProfileUpdated`（携带 `user_id`，由 `UserController@modifyProfile` 和头像上传接口在用户更新资料后触发）
- **判定逻辑**：资料完整度基于 `UserProfile` 模型字段计算，共 6 个维度，每填一项算 1 分：

  | 序号 | 维度 | 所在表 | 字段 | 判定为"已填"的条件 |
  |------|------|--------|------|-------------------|
  | 1 | 昵称 | users | `name` | `name is not null`（注意：null 时 getter 会 fallback 为 username，此处必须判断数据库原始值非 null） |
  | 2 | 头像 | users | `avatar` | `avatar is not null` |
  | 3 | 性别 | user_profiles | `gender` | `gender != 0`（0=保密即未选择） |
  | 4 | 生日 | user_profiles | `birthday` | `birthday is not null` |
  | 5 | 签名 | user_profiles | `bio` | `bio is not null and bio != ''` |
  | 6 | 地区 | user_profiles | `province_id` / `city_id` / `district_id` | 三个字段均 `is not null`（省/市/区三级联动，都选才算填了地区） |

  完整度 = 已填项数 / 6（满分 100%）。当完整度达到任务 `condition.completeness_threshold`（默认 100%，即全部填完）时标记完成。阈值通过任务的 `condition` JSON 配置，方便运营灵活调整。

- **condition 字段**：`{ "completeness_threshold": 100 }`，单位为百分比
- **period_key 策略**：`all`（一次性，完成后永久有效，不重置）
- **奖励模式**：auto
- **重置周期**：none
- **注意**：资料完整度计算逻辑封装在 `TaskService::calculateProfileCompleteness($user)` 方法中，监听器调用此方法获得完整度百分比，与任务阈值对比判定是否达成。

#### 3.2.3 关注公众号任务（follow_official_account）

- **触发事件**：`UserFollowedOfficialAccount`（公众号关注/取关回调时触发，携带 `user_id`、`openid`、`is_followed`）
- **判定逻辑**：
  1. 公众号关注回调（`is_followed = true`）时，通过 `openid` 匹配本地用户（`socials` 表中 `provider = wechat_official` 的记录）
  2. 匹配成功则对用户的 `follow_official_account` 类型任务标记完成
  3. 取关回调（`is_followed = false`）不做回滚（已发放的奖励不回收，但可在前台标记任务为未完成）
- **奖励模式**：auto
- **重置周期**：none
- **注意**：依赖项目已有 `WechatService` 和公众号回调处理，关注事件由微信服务器推送，需在 `WechatController` 中处理 `event` 消息并触发事件。用户与公众号的关联通过 `socials` 表的 `provider = wechat_official` 记录建立。

#### 3.2.4 邀请好友任务（invite_user）

- **触发事件**：`App\Events\Invite\InviteRelationshipCreated`（已有事件，邀请关系建立时触发，携带 `InviteRecord` 模型）
- **判定逻辑**：
  1. 从事件中获取 `InviteRecord`，通过 `$inviteRecord->inviter` 获取邀请人
  2. 查询邀请人的 `invite_user` 类型任务
  3. 对匹配任务的进度 `current_value + 1`
  4. 当 `current_value >= target_count` 时标记完成
- **奖励模式**：auto 或 manual
- **重置周期**：none
- **注意**：该事件由 `InviteService::createRelationshipByCode()` 在邀请关系写入 `invite_records` 表后触发，任务系统监听此事件即可接入奖励。已有 `CoinType::TYPE_INVITE_REGISTER` 和 `PointType::TYPE_INVITE_REGISTER` 枚举可用。

#### 3.2.5 每日登录任务（daily_login）

- **触发事件**：`UserLoggedIn`
- **判定逻辑**：当日有登录记录即完成，每日一次
- **奖励模式**：auto
- **重置周期**：daily

#### 3.2.6 自定义行为任务（custom_action）

- **触发事件**：`TaskActionTriggered`（携带 `action` 和 `value`）
- **判定逻辑**：
  1. 匹配 `condition.action == event.action` 的任务
  2. 进度值累加 `value`
  3. 达到 `target_count` 即完成
- **奖励模式**：manual 或 auto
- **重置周期**：依业务而定

---

## 4. 奖励体系

### 4.1 奖励类型

| 类型 | 值 | 发放方式 | 说明 |
|------|-----|---------|------|
| 金币 | `coin` | `CoinHelper::incr()` | 对应 `users.available_coins` |
| 积分 | `point` | `PointHelper::incr()` | 对应 `users.available_points` |
| VIP 天数 | `vip_days` | `UserHelper::extendVip()` | 对应 `users.vip_expires_at` |
| 无奖励 | `none` | — | 纯成就/解锁性质 |

### 4.2 发放流程

```
事件触发 → 监听器 → 更新 task_progress → 判定是否达成
                              ↓ 达成且 claim_mode=auto
                         创建 task_log（事务内）
                              ↓
                    调用对应奖励 Helper 的 incr 方法
                              ↓
                    回填 reward_trade_id 到 task_log
```

### 4.3 并发与幂等保障

1. **唯一索引防重**：`task_progress` 和 `task_logs` 均有 `(user_id, task_id, period_key)` 唯一索引，并发下重复插入会抛唯一键冲突，捕获后直接返回成功。
2. **事务包裹**：进度更新 + 奖励发放 + 日志写入在同一个 DB 事务中。
3. **先写日志后发奖励**：在事务内先 insert task_log（获取行锁），再发奖励，确保奖励只发一次。

---

## 5. 事件系统设计

### 5.1 事件列表

| 事件类 | 触发场景 | 携带数据 |
|--------|---------|---------|
| `Events\User\UserSignedIn` | 用户签到 | `user_id`、`serial_days` |
| `Events\User\UserLoggedIn` | 用户登录 | `user_id`、`ip` |
| `Events\User\UserProfileUpdated` | 用户更新资料 | `user_id`、`completeness` |
| `Events\User\UserFollowedOfficialAccount` | 公众号关注回调 | `user_id`、`openid`、`is_followed` |
| `Events\Invite\InviteRelationshipCreated` | 邀请关系建立（已有事件） | `inviteRecord`（含 `inviter`、`invitee` 关联） |
| `Events\Task\TaskActionTriggered` | 通用任务行为触发 | `user_id`、`action`、`value` |

### 5.2 监听器列表

| 监听器 | 监听事件 | 职责 |
|--------|---------|------|
| `Listeners\Task\SignInTaskListener` | `UserSignedIn` | 处理签到类任务进度 |
| `Listeners\Task\LoginTaskListener` | `UserLoggedIn` | 处理每日登录任务 |
| `Listeners\Task\ProfileTaskListener` | `UserProfileUpdated` | 处理完善资料任务 |
| `Listeners\Task\FollowOfficialAccountTaskListener` | `UserFollowedOfficialAccount` | 处理关注公众号任务 |
| `Listeners\Task\InviteTaskListener` | `InviteRelationshipCreated` | 处理邀请任务 |
| `Listeners\Task\CustomActionTaskListener` | `TaskActionTriggered` | 处理自定义行为任务 |

每个监听器内部按以下通用流程处理：

```php
// 伪代码
public function handle($event): void
{
    // 1. 找出该类型下所有启用的任务
    $tasks = Task::where('type', $this->taskType)->where('status', 1)->get();

    foreach ($tasks as $task) {
        // 2. 计算 period_key
        $periodKey = $task->group->getPeriodKey();

        // 3. upsert 进度（靠唯一索引防并发）
        $progress = TaskProgress::updateOrCreate(
            ['user_id' => $event->userId, 'task_id' => $task->id, 'period_key' => $periodKey],
            [...增量更新 current_value...]
        );

        // 4. 判定是否达成
        if ($progress->isCompleted() && !$progress->is_claimed) {
            // 5. 发放奖励（事务内）
            $task->grantReward($event->userId, $periodKey);
        }
    }
}
```

#### 5.2.1 特殊监听器：SignInTaskListener

签到任务不走通用的 `current_value` 累加判定流程，因为连续签到天数由签到系统维护，任务系统只负责"匹配 + 发放"。

```php
// SignInTaskListener 伪代码
public function handle(UserSignedIn $event): void
{
    $serialDays = $event->serial_days;

    // 1. 查询所有启用的签到任务
    $tasks = Task::where('type', TaskType::SIGN_IN)
        ->where('status', 1)
        ->with('group')
        ->get();

    foreach ($tasks as $task) {
        $conditionSerialDays = (int) ($task->condition['serial_days'] ?? 0);

        // 2. serial_days = 0 表示每日签到基础奖励，每天都匹配
        if ($conditionSerialDays === 0) {
            $periodKey = date('Y-m-d');
            $this->tryGrantReward($event->user_id, $task, $periodKey);
            continue;
        }

        // 3. serial_days > 0 表示连续签到阶梯奖励，精确匹配
        if ($conditionSerialDays === $serialDays) {
            $periodKey = "sign_in_day_{$conditionSerialDays}";
            $this->tryGrantReward($event->user_id, $task, $periodKey);
        }
    }
}
```

> 签到任务同样写入 `task_progress` 和 `task_logs`，保持数据一致性，方便前台列表统一展示。

---

## 6. API 设计

### 6.1 前台用户端 API（/api/v1）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/api/v1/tasks` | 任务列表（按组分，含用户当前进度） | 已登录用户 |
| GET | `/api/v1/tasks/{group_code}` | 单个任务组详情 | 已登录用户 |
| POST | `/api/v1/tasks/{task_id}/claim` | 手动领取任务奖励 | 已登录用户 |
| POST | `/api/v1/tasks/sign-in` | 签到（同时触发签到事件） | 已登录用户 |

#### 6.1.1 GET /api/v1/tasks 响应结构

```json
{
  "data": [
    {
      "group_id": 1,
      "group_name": "每日任务",
      "code": "daily",
      "reset_type": "daily",
      "tasks": [
        {
          "task_id": 1,
          "name": "每日签到",
          "description": "每日签到获得10金币",
          "type": "sign_in",
          "icon": "",
          "reward_type": "coin",
          "reward_amount": 10,
          "target_count": 1,
          "current_value": 1,
          "is_completed": true,
          "is_claimed": true,
          "claim_mode": "auto",
          "sort": 1
        }
      ],
      "completed_count": 1,
      "total_count": 3
    }
  ]
}
```

#### 6.1.2 POST /api/v1/tasks/{task_id}/claim

请求体：无
成功响应：200 `{ data: { reward_type: "coin", reward_amount: 10 } }`
错误：422 任务未达成 / 已领取 / 已禁用

### 6.2 后台管理 API（/admin）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/task-groups/data` | 任务组分页列表 | task-groups.index |
| GET/POST/PUT/DELETE | `/admin/task-groups` | 任务组 CRUD | task-groups.* |
| POST | `/admin/task-groups/{id}/status` | 切换任务组状态 | task-groups.edit |
| GET | `/admin/tasks/data` | 任务分页列表（可按 group_id 筛选） | tasks.index |
| GET/POST/PUT/DELETE | `/admin/tasks` | 任务 CRUD | tasks.* |
| GET | `/admin/task-logs/data` | 任务完成日志列表 | task-logs.index |
| GET | `/admin/task-logs/{id}` | 任务完成日志详情 | task-logs.index |
| POST | `/admin/tasks/{id}/reward` | 补发/调整奖励（高权限） | tasks.reward |

---

## 7. 核心代码结构

```
app/
├── Enums/
│   └── Task/
│       ├── TaskType.php              # 任务类型枚举（HasLabel）
│       ├── TaskResetType.php         # 重置周期枚举
│       ├── TaskRewardType.php        # 奖励类型枚举
│       └── TaskClaimMode.php         # 领取方式枚举
├── Models/
│   └── Task/
│       ├── TaskGroup.php             # 任务组模型
│       ├── Task.php                  # 任务模型
│       ├── TaskProgress.php          # 用户任务进度
│       └── TaskLog.php               # 完成日志
├── Casts/
│   └── AsTaskCondition.php           # 任务条件 JSON Cast
├── Events/
│   ├── Task/
│   │   └── TaskActionTriggered.php   # 通用行为触发事件
│   └── User/
│       └── UserSignedIn.php          # 签到事件（复用已有 Invite\InviteRelationshipCreated）
├── Listeners/
│   └── Task/
│       ├── SignInTaskListener.php
│       ├── LoginTaskListener.php
│       ├── ProfileTaskListener.php
│       ├── FollowOfficialAccountTaskListener.php
│       ├── InviteTaskListener.php
│       └── CustomActionTaskListener.php
├── Services/
│   └── TaskService.php               # 任务进度判定 + 奖励发放编排
├── Support/
│   └── Traits/
│       └── HasTaskProgress.php       # 用户模型的任务关联 trait
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/
│   │   │   └── TaskController.php    # 前台任务接口
│   │   └── Admin/
│   │       ├── TaskGroupController.php
│   │       ├── TaskController.php
│   │       └── TaskLogController.php
│   ├── Requests/
│   │   ├── Admin/Task/               # 后台表单请求
│   │   └── Api/V1/Task/              # 前台表单请求
│   └── Resources/
│       ├── Admin/Task/               # 后台资源
│       └── Api/V1/Task/              # 前台资源
├── Policies/
│   └── TaskPolicy.php
database/
├── migrations/
│   └── xxxx_create_task_tables.php   # 4 张表
├── seeders/
│   └── TaskSeeder.php                # 默认任务（每日签到、完善资料等）
└── factories/
    └── Task/                         # 模型工厂
tests/
└── Feature/
    ├── Api/V1/TaskControllerTest.php
    └── Admin/
        ├── TaskGroupControllerTest.php
        ├── TaskControllerTest.php
        └── TaskLogControllerTest.php
```

### 7.1 TaskService 核心方法

| 方法 | 说明 |
|------|------|
| `getUserTaskGroups($user)` | 获取用户视角的任务组列表（含进度） |
| `getUserProgress($user, $taskId)` | 获取用户某任务的进度 |
| `incrementProgress($userId, $task, $value, $periodKey)` | 增加任务进度，返回是否新达成 |
| `grantReward($userId, $task, $periodKey, $claimedBy)` | 发放奖励并写日志，幂等 |
| `claimReward($userId, $taskId)` | 手动领取奖励（manual 模式用） |

### 7.2 User 模型集成

通过 `HasTaskProgress` trait 给 `User` 模型追加：

```php
// 关联
$this->taskProgresses(): HasMany
$this->taskLogs(): HasMany

// 快捷方法
$this->hasCompletedTask($taskCode): bool
$this->getTaskProgress($taskCode): ?TaskProgress
```

---

## 8. 初始种子数据

`TaskSeeder` 中预置以下任务组和任务，方便开箱即用：

### 8.1 新手任务组（newbie，不重置）

| 任务 | 类型 | 奖励 | 奖励数量 |
|------|------|------|---------|
| 完善个人资料 | complete_profile | coin | 50 |
| 关注公众号 | follow_official_account | coin | 30 |
| 首次邀请好友 | invite_user | coin | 100 |

### 8.2 签到任务组（sign_in，不重置 — 阶梯奖励永久有效；每日签到基础奖按日重置）

| 任务 | type | condition.serial_days | reward_type | reward_amount | period_key 策略 |
|------|------|----------------------|-------------|---------------|----------------|
| 每日签到 | sign_in | 0 | coin | 10 | `Y-m-d`，每日重置 |
| 连续签到第2天 | sign_in | 2 | coin | 15 | `sign_in_day_2`，永久唯一 |
| 连续签到第3天 | sign_in | 3 | coin | 20 | `sign_in_day_3`，永久唯一 |
| 连续签到第7天 | sign_in | 7 | coin | 100 | `sign_in_day_7`，永久唯一 |
| 连续签到第30天 | sign_in | 30 | coin | 500 | `sign_in_day_30`，永久唯一 |

### 8.3 每日任务组（daily，每日重置）

| 任务 | 类型 | 奖励 | 奖励数量 |
|------|------|------|---------|
| 每日登录 | daily_login | point | 5 |

> 业务项目可在自身的 Seeder 中追加更多任务，或通过后台手动创建。

---

## 9. 权限与菜单

### 9.1 新增权限

| 权限标识 | 说明 |
|---------|------|
| task-groups.index | 查看任务组列表 |
| task-groups.create | 创建任务组 |
| task-groups.edit | 编辑任务组 |
| task-groups.delete | 删除任务组 |
| tasks.index | 查看任务列表 |
| tasks.create | 创建任务 |
| tasks.edit | 编辑任务 |
| tasks.delete | 删除任务 |
| tasks.reward | 补发/调整任务奖励（高权限） |
| task-logs.index | 查看任务完成日志 |

### 9.2 新增菜单

挂在「运营管理」目录下：

| 菜单 key | path | component | title | permission |
|----------|------|-----------|-------|------------|
| operation.task-group | task-group | /operation/task-group | 任务组管理 | task-groups.index |
| operation.task | task | /operation/task | 任务管理 | tasks.index |
| operation.task-log | task-log | /operation/task-log | 任务记录 | task-logs.index |

菜单数据追加到 `seed_initial_admin_data.php` 迁移文件中。

---

## 10. 与现有系统的集成点

### 10.1 金币 / 积分 / VIP

- 金币奖励：调用 `CoinHelper::incr($user, $amount, $taskLog, 'task', '完成任务奖励')`，签到类用 `CoinType::TYPE_SIGN_IN`，邀请类用 `CoinType::TYPE_INVITE_REGISTER`
- 积分奖励：调用 `PointHelper::incr(...)`，同上区分类型
- VIP 奖励：调用 `UserHelper::extendVip($user, $days)`
- 所有奖励操作均通过已有 Helper，不直接修改 `users` 表字段

### 10.2 事件系统

- 复用现有 `UserLoggedIn`、`UserProfileUpdated` 等事件
- 复用已有 `InviteRelationshipCreated` 事件接入邀请任务奖励
- 新增 `UserSignedIn`、`TaskActionTriggered` 等事件
- 项目使用 Laravel 11+ 自动事件发现机制，监听器放在 `app/Listeners/` 下自动注册，无需手动绑定

### 10.3 多态映射

在 `config/morph_maps.php` 中追加：

```php
'task_group'   => \App\Models\Task\TaskGroup::class,
'task'         => \App\Models\Task\Task::class,
'task_log'     => \App\Models\Task\TaskLog::class,
```

---

## 11. 实施阶段

| 阶段 | 内容 | 交付物 |
|------|------|--------|
| **Phase 1 数据模型** | 4 张表迁移 + 4 个模型 + 枚举 + Cast + 工厂 + Seeder；`HasTaskProgress` trait；PHPUnit 覆盖模型关系与基础查询 | 可查询、可配置的任务数据层 |
| **Phase 2 任务引擎** | `TaskService` 核心逻辑（进度更新 + 达成判定 + 奖励发放）；6 个事件 + 6 个监听器；并发安全与幂等保障；单元测试覆盖各任务类型的达成逻辑和并发场景 | 完整可用的任务奖励引擎 |
| **Phase 3 前台 API** | `TaskController` 前台接口（列表/详情/领取/签到）；Request + Resource；功能测试覆盖各接口的正常、失败、权限场景 | 前台用户端可用 |
| **Phase 4 后台管理** | 任务组 CRUD + 状态切换；任务 CRUD；任务日志列表/详情；权限与菜单接入；后台功能测试 | 运营可配置任务 |
| **Phase 5 前端落地** | laravel-skel-admin 中新增运营管理下 3 个页面（任务组、任务、任务记录） | 后台管理 UI 完整 |
| **Phase 6 文档与发布** | Apifox 全量发布（Admin + Api 双模块）；更新 README；补齐测试覆盖率至 70%+ | 生产可用 |

---

## 12. 风险与注意事项

| 风险 | 说明 | 应对策略 |
|------|------|---------|
| 重复发放奖励 | 并发下同一用户同一任务可能被多个请求同时触发 | 唯一索引 + 事务 + 捕获唯一键冲突返回成功 |
| 事件丢失 | 事件监听器抛异常可能导致任务进度未更新 | 关键事件启用 ShouldQueue + 失败重试 + failed_jobs 监控 |
| 重置周期边界 | 跨日/跨周时旧周期数据残留 | 所有查询均带 `period_key` 过滤，后台可定期归档历史数据 |
| 奖励发放失败 | 金币/积分 Helper 抛异常导致部分成功 | 全部操作在同一事务内，任何一步失败整体回滚 |
| 配置错误导致异常奖励 | 运营配置 reward_amount 过大 | 后台创建任务时增加最大奖励数量校验 |
