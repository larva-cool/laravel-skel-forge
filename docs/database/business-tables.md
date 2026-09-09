# 业务数据表

## 1. 数据字典表

### 1.1 字典类型表 (dicts)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| name | VARCHAR | 字典名称 |
| code | VARCHAR | 字典编码（唯一） |
| status | TINYINT | 状态（0 禁用，1 启用） |
| sort | INT | 排序权重（越小越靠前） |
| remark | VARCHAR | 备注 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 1.2 字典数据表 (dict_items)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| dict_id | BIGINT | 所属字典 ID |
| label | VARCHAR | 字典项标签 |
| value | VARCHAR | 字典项值 |
| label_type | VARCHAR | 标签样式（default/primary/success/warning/danger/info，对应 Element Plus Tag type） |
| status | TINYINT | 状态（0 禁用，1 启用） |
| sort | INT | 排序权重 |
| remark | VARCHAR | 备注 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

> 删除字典时通过模型 `booted` 事件级联删除其所有字典项。

## 2. 应用发布表 (app_releases)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| name | VARCHAR | 应用名称 |
| slug | VARCHAR | 应用唯一标识（客户端传参用） |
| description | VARCHAR | 应用描述 |
| icon | VARCHAR | 应用图标 URL |
| sort | INT | 排序权重 |
| status | BOOLEAN | 启用状态 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

## 3. 应用版本表 (app_versions)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| app_release_id | BIGINT | 所属应用 ID |
| platform | VARCHAR | 平台（android/ios/harmony，枚举 AppPlatform） |
| version | VARCHAR | 语义化版本号（如 1.2.0） |
| build | INT | 构建号（整数递增，用于版本比较） |
| download_url | VARCHAR | 安装包下载地址 |
| release_notes | TEXT | 更新说明 |
| min_supported_version | VARCHAR | 最低支持版本号 |
| min_supported_build | INT | 最低支持构建号（低于此构建号需强制更新） |
| force_update | BOOLEAN | 是否强制更新 |
| file_size | BIGINT | 安装包大小（字节） |
| file_hash | VARCHAR | 文件哈希（校验用） |
| status | VARCHAR | 发布状态（draft/published/withdrawn/obsolete，枚举 ReleaseStatus） |
| published_at | TIMESTAMP | 发布时间 |
| created_by | BIGINT | 创建人管理员 ID |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

> 发布版本时会自动将同应用同平台下其他已发布版本置为 `obsolete`；已发布状态的版本不可删除，需先撤回或废弃。

**版本状态流转**：`draft` → `published`（publish）→ `withdrawn`（withdraw）/ `obsolete`（obsolete 或被新版本自动替换）。

## 4. 内容互动表

### 4.1 点赞表 (likes)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| user_id | bigint | 点赞用户 |
| source_type / source_id | string / bigint | 多态目标（morphs） |
| extra | json | 扩展信息 |
| created_at / updated_at | timestamp | 时间戳 |

- 唯一约束 `uniq_user_like`：`user_id + source_type + source_id`，同一用户对同一资源只能点赞一次
- 模型事件：`created` 时对目标 `like_count` 自增，`deleted` 时自减

### 4.2 收藏表 (collections)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| user_id | bigint | 收藏用户 |
| source_type / source_id | string / bigint | 多态目标（morphs） |
| extra | json | 扩展信息 |
| created_at / updated_at | timestamp | 时间戳 |

- 唯一约束 `uniq_user_collection`：`user_id + source_type + source_id`
- 模型事件：`created` 时对目标 `collection_count` 自增，`deleted` 时自减

### 4.3 评论表 (comments)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| user_id | bigint | 评论用户 |
| source_type / source_id | string / bigint | 多态目标（自关联可实现楼层回复） |
| is_top | boolean | 是否置顶 |
| status | string | 审核状态 |
| like_count / comment_count | int | 点赞数 / 回复数 |
| content | string(1000) | 评论内容 |
| mentioned_users | json | 艾特用户 ID 列表 |
| ip_address | ip | 评论 IP |
| created_at | timestamp | 评论时间（无 `updated_at`） |

### 4.4 互动能力 Trait

被互动的业务模型通过 trait 接入，目标表需自备对应计数列：

| Trait | 提供方法 | 依赖计数列 |
|-------|---------|-----------|
| `HasLikeable` | `likes()`、`isLikedBy($user)` | `like_count` |
| `HasCollectionable` | `collections()`、`isCollectedBy($user)` | `collection_count` |
| `HasCommentable` | `comments()` | `comment_count` |

- `source_type` 仅接受 `config/morph_maps.php` 中注册的多态别名
- 计数列由模型事件自动维护，接入前须确认目标表已有对应字段
- **评论楼层回复约定**：当 `source_type = 'comment'` 时表示本条为对另一条评论的回复，`source_id` 即父评论 ID；顶级评论的 `source_type` 指向业务实体（如 `user`、`post`）。后台判定回复关系与查询回复链均基于此约定，无需额外 `parent_id` 字段

## 5. 钱包相关表（真实货币）

> 设计原则：**余额热数据落在持有者主表字段上（查询零 Join），记录层全部多态**。
> 未来其他模型（如 `admin_users`、未来的 `merchants`）需要支持钱包时，只需在其表追加 `balance` / `frozen_balance` 两个字段并 `use HasWalletBalance`，记录层无需任何改动。

### 5.1 钱包流水表 (wallet_trades)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键（从 10000000 起） |
| owner_type | VARCHAR(64) | 持有者类型（多态，取 `config/morph_maps.php` 别名，如 `user`） |
| owner_id | BIGINT | 持有者 ID |
| amount | DECIMAL(16,2) | 本次变动金额（正增负减） |
| before_balance | DECIMAL(16,2) | 变动前可用余额快照 |
| after_balance | DECIMAL(16,2) | 变动后可用余额快照 |
| before_frozen | DECIMAL(16,2) | 变动前冻结余额快照 |
| after_frozen | DECIMAL(16,2) | 变动后冻结余额快照 |
| type | VARCHAR(32) | 交易类型（枚举 `WalletTradeType`） |
| source_type | VARCHAR(64) | 业务来源类型（多态，如订单、提现单） |
| source_id | BIGINT | 业务来源 ID |
| status | VARCHAR(20) | 流水状态（pending/success/failed） |
| description | VARCHAR(255) | 交易描述 |
| created_at | TIMESTAMP | 交易时间 |

**索引**：`(owner_type, owner_id, created_at)` 复合索引、`(source_type, source_id)` 复合索引、`type` 索引。

> 冻结/解冻操作只变动冻结余额而不动可用余额，因此必须同时保留 `before_frozen` / `after_frozen` 快照，否则无法完整审计对账。

### 5.2 充值订单表 (recharge_orders)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| owner_type / owner_id | VARCHAR(64) / BIGINT | 持有者（多态） |
| order_no | VARCHAR(64) | 订单号（唯一，前缀 `R` + 日期 + 随机串） |
| amount | DECIMAL(16,2) | 充值金额 |
| bonus_amount | DECIMAL(16,2) | 赠送金额（按 `wallet.recharge_bonus_rate` 计算） |
| pay_channel | VARCHAR(32) | 支付渠道（wechat/alipay/apple） |
| pay_status | VARCHAR(20) | 支付状态（pending/success/failed/expired） |
| paid_at | TIMESTAMP | 支付完成时间 |
| expired_at | TIMESTAMP | 订单过期时间（默认创建后 30 分钟） |
| created_at / updated_at | TIMESTAMP | 时间戳 |

**索引**：`order_no` 唯一索引、`(owner_type, owner_id)` 复合索引、`pay_status` 索引。

### 5.3 提现申请表 (withdraw_orders)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| owner_type / owner_id | VARCHAR(64) / BIGINT | 持有者（多态） |
| order_no | VARCHAR(64) | 提现单号（唯一，前缀 `W`） |
| amount | DECIMAL(16,2) | 申请提现金额 |
| fee | DECIMAL(16,2) | 手续费（按 `wallet.withdraw_fee_rate` 计算） |
| actual_amount | DECIMAL(16,2) | 实际到账金额（amount - fee） |
| account_type | VARCHAR(32) | 收款账户类型（wechat/alipay/bank） |
| account_info | JSON | 收款账户信息（脱敏展示） |
| status | VARCHAR(20) | 状态（枚举 `WithdrawStatus`） |
| reviewed_by | BIGINT | 审核管理员 ID |
| reviewed_at | TIMESTAMP | 审核时间 |
| reject_reason | VARCHAR(255) | 驳回原因 |
| transfer_at | TIMESTAMP | 打款完成时间 |
| created_at / updated_at | TIMESTAMP | 时间戳 |

**索引**：`order_no` 唯一索引、`(owner_type, owner_id)` 复合索引、`status` 索引。

### 5.4 钱包相关枚举

**WalletTradeType（钱包交易类型）**：

| 值 | 说明 |
|----|------|
| recharge | 充值 |
| withdraw | 提现 |
| freeze | 冻结 |
| unfreeze | 解冻 |
| payment | 消费 |
| refund | 退款 |
| admin_recharge | 后台充正 |
| admin_deduct | 后台扣减 |

**WithdrawStatus（提现状态）**：

| 值 | 说明 |
|----|------|
| pending | 待审核 |
| review | 需复审 |
| approved | 审核通过，待打款 |
| rejected | 已驳回（自动解冻） |
| transferring | 打款中 |
| success | 打款成功 |
| failed | 打款失败（自动解冻） |

**与现有积分/金币体系的关系**：钱包是独立的**真实货币**账本，与现有 `available_points`（积分）、`available_coins`（金币）两套虚拟资产体系**并行且互不影响**，本期不做迁移或合并。

## 6. 虚拟资产流水表

| 表名 | 说明 |
|------|------|
| coin_trades | 金币流水记录 |
| point_records | 积分记录 |
| point_trades | 积分流水记录 |

## 7. 系统基础表

| 表名 | 说明 |
|------|------|
| sessions | 会话存储 |
| cache / cache_locks | 缓存/缓存锁 |
| jobs / job_batches / failed_jobs | 队列任务/批次/失败任务 |
| pulse_values / pulse_entries / pulse_aggregates | Laravel Pulse 监控数据 |

---

> 文档来源：PRD 第 5.1 节
