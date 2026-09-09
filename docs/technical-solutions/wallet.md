# 钱包技术方案

## 1. 设计原则

- **余额热数据落在持有者主表，记录层全部多态**：余额是最高频读取的字段，直接作为 `users` 表列存储可做到查询零 Join；而流水、充值单、提现单三张记录表统一使用 `owner_type` + `owner_id` 多态关联，业务逻辑不与 `User` 耦合。
- **可扩展但不过度设计**：未来其他主体（如 `admin_users` 平台账户、未来的 `merchants` 商户）需要钱包时，只需在其表追加 `balance` / `frozen_balance` 两个字段并 `use HasWalletBalance`，记录层与 `WalletHelper` 无需改动。本期不引入独立 `wallets` 表和多币种，避免为假想需求付出复杂度。
- **增量更新而非流水汇总**：与现有 `CoinHelper`（每次 `SUM` 全表重算）不同，钱包余额采用事务内行锁 + 增量更新（`balance + amount`），流水表保存前后余额快照用于对账。真实货币场景对一致性与性能要求都更高，`SUM` 全表在数据量增长后不可接受。
- **冻结与可用余额分离**：`balance` 为可用余额，`frozen_balance` 为冻结余额，两者互不重叠。下单冻结即 `balance -= n; frozen_balance += n`，成交扣款即 `frozen_balance -= n`，取消解冻即反向操作。
- **金额精度**：统一 `decimal(16,2)`，以元为单位；PHP 侧用字符串/`decimal` cast 传递，禁止使用 `float` 参与金额运算。
- **与虚拟资产完全隔离**：钱包是真实货币账本，与 `available_points`（积分）、`available_coins`（金币）并行独立，不做迁移合并。

## 2. 代码结构

```
app/
├── Enums/
│   ├── WalletTradeType.php             # 钱包交易类型枚举（实现 HasLabel）
│   ├── WalletTradeStatus.php           # 流水状态枚举（pending/success/failed）
│   ├── WithdrawStatus.php              # 提现状态枚举（实现 HasLabel）
│   ├── PayChannel.php                  # 支付渠道枚举（wechat/alipay/apple）
│   └── WithdrawAccountType.php         # 收款账户类型枚举（wechat/alipay/bank）
├── Exceptions/
│   ├── InsufficientBalanceException.php    # 余额不足
│   └── InsufficientFrozenBalanceException.php # 冻结余额不足
├── Models/
│   ├── Traits/
│   │   └── HasWalletBalance.php        # 钱包能力 trait（多态关系 + 余额判定）
│   └── Wallet/
│       ├── WalletTrade.php             # 钱包流水（morphTo owner + morphTo source）
│       ├── Recharge.php           # 充值订单（morphTo owner）
│       └── Withdraw.php                # 提现申请（morphTo owner）
├── Support/
│   └── WalletHelper.php                # 余额引擎（incr/decr/freeze/unfreeze）
├── Services/
│   ├── RechargeService.php             # 充值下单与回调入账编排
│   └── WithdrawService.php             # 提现申请与审核流转编排
├── Events/Wallet/
│   ├── BalanceChanged.php              # 余额变动事件
│   ├── RechargeSucceeded.php           # 充值成功事件
│   ├── WithdrawApproved.php            # 提现审核通过事件
│   ├── WithdrawRejected.php            # 提现驳回事件
│   ├── WithdrawTransferred.php         # 提现打款成功事件
│   └── WithdrawFailed.php              # 提现打款失败事件
├── Notifications/Wallet/
│   ├── RechargeSucceededNotification.php
│   ├── WithdrawApprovedNotification.php
│   ├── WithdrawRejectedNotification.php
│   ├── WithdrawTransferredNotification.php
│   └── WithdrawFailedNotification.php
├── Jobs/Wallet/
│   └── CloseExpiredRechargesJob.php   # 关闭超时未支付订单
├── Policies/
│   ├── RechargePolicy.php
│   └── WithdrawPolicy.php
├── Exports/Admin/
│   ├── WalletTradeExport.php
│   ├── WithdrawExport.php
│   └── RechargeExport.php
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── WalletController.php            # 流水列表/详情/统计/调账
│   │   │   ├── WithdrawController.php          # 提现审核
│   │   │   └── RechargeController.php     # 充值订单查看
│   │   ├── Api/V1/Wallet/
│   │   │   ├── WalletController.php            # 前台钱包概览/流水/充值
│   │   │   └── WithdrawController.php          # 前台提现提交/列表/详情
│   │   └── PayCallbackController.php           # 支付渠道异步回调
│   ├── Requests/
│   │   ├── Admin/Wallet/
│   │   │   ├── WalletTradeIndexRequest.php
│   │   │   ├── WalletAdjustRequest.php
│   │   │   ├── WithdrawIndexRequest.php
│   │   │   ├── WithdrawRejectRequest.php
│   │   │   ├── WithdrawFailRequest.php
│   │   │   └── WithdrawRemarkRequest.php
│   │   └── Api/V1/Wallet/
│   │       ├── CreateRechargeRequest.php
│   │       └── CreateWithdrawRequest.php
│   └── Resources/
│       ├── Admin/
│       │   ├── WalletTradeResource.php
│       │   ├── WalletStatResource.php
│       │   ├── WithdrawResource.php
│       │   └── RechargeResource.php
│       └── Api/V1/
│           ├── WalletResource.php
│           ├── WalletTradeResource.php
│           ├── WithdrawResource.php
│           └── RechargeResource.php
database/
├── migrations/
│   ├── xxxx_add_balance_to_users_table.php     # users 表加 balance/frozen_balance
│   ├── xxxx_create_wallet_trades_table.php
│   ├── xxxx_create_recharges_table.php
│   └── xxxx_create_withdraws_table.php
└── factories/Wallet/
    ├── WalletTradeFactory.php
    ├── RechargeFactory.php
    └── WithdrawFactory.php
tests/Feature/
├── Admin/
│   ├── WalletControllerTest.php
│   ├── WithdrawAdminControllerTest.php
│   └── RechargeAdminControllerTest.php
├── Api/V1/
│   ├── WalletControllerTest.php
│   ├── RechargeControllerTest.php
│   └── WithdrawControllerTest.php
└── Wallet/
    ├── WalletHelperTest.php
    ├── RechargeServiceTest.php
    └── WithdrawServiceTest.php
```

## 3. HasWalletBalance Trait

任何主表含 `balance` / `frozen_balance` 两列的模型 `use HasWalletBalance` 即获得完整钱包能力：

| 方法 | 职责 |
|------|------|
| `walletTrades(): MorphMany` | 钱包流水（作为 owner） |
| `recharges(): MorphMany` | 充值订单（作为 owner） |
| `withdraws(): MorphMany` | 提现申请（作为 owner） |
| `hasBalance(float $amount): bool` | 判定可用余额是否充足 |
| `totalBalance(): float` | 总资产（可用 + 冻结） |

本期仅 `User` 接入。

## 4. WalletHelper 关键方法

| 方法 | 职责 | 余额变动 |
|------|------|---------|
| `incr($owner, $amount, $source, $type, $desc)` | 增加可用余额（充值到账、退款、后台充正） | `balance += n` |
| `decr($owner, $amount, $source, $type, $desc)` | 扣减可用余额（直接消费、后台扣减） | `balance -= n` |
| `freeze($owner, $amount, $source, $desc)` | 冻结（下单预授权、提现申请） | `balance -= n; frozen_balance += n` |
| `unfreeze($owner, $amount, $source, $desc)` | 解冻（订单取消、提现驳回） | `frozen_balance -= n; balance += n` |
| `settleFrozen($owner, $amount, $source, $type, $desc)` | 结算冻结（订单成交、提现打款成功） | `frozen_balance -= n` |
| `getBalance($owner): float` | 读取可用余额 | — |
| `getFrozenBalance($owner): float` | 读取冻结余额 | — |

### 并发安全实现要点

- 所有写方法包裹在 `DB::transaction()` 中，进入事务后先对持有者主表行执行 `lockForUpdate()` 取最新余额
- 校验余额充足性使用锁内读到的值，不使用传入模型的过期属性
- 余额更新使用增量表达式（`increment` / `decrement`）而非先读后写的赋值，避免丢失更新
- 流水记录写入 `before_balance` / `after_balance` / `before_frozen` / `after_frozen` 四个快照字段，供财务对账与差异排查
- 余额不足抛 `InsufficientBalanceException`，控制器层捕获后 `abort(422, ...)`，与现有 `InsufficientCoinsException` 处理方式一致

## 5. 充值链路

> **当前状态**：支付渠道对接已挂起（见第 12 章「挂起待办」）。Phase 2 只实现订单生命周期与管理员手动确认到账，下方渠道相关步骤为渠道接入后的目标形态。

**Phase 2 实现的链路（管理员手动确认）**

```
POST /api/v1/user/wallet/recharges
  → CreateRechargeRequest 校验开关与金额区间
  → RechargeService::create()
      → 计算赠送金额（wallet.recharge_bonus_rate）
      → 生成 order_no（R + YmdHis + 随机串）
      → 创建 Recharge(pay_status=pending, expired_at=now+30min)
      → 返回订单信息（暂不返回渠道支付参数）

POST /admin/wallet/recharges/{recharge}/confirm
  → RechargeService::confirmManually()
      → 事务内按 order_no 加锁查单
      → 幂等判定：已 success 直接返回
      → 置 pay_status=success、paid_at、confirmed_by
      → WalletHelper::incr() 入账（amount + bonus_amount）
      → 派发 RechargeSucceeded 事件 → 队列发站内信
```

**渠道接入后的目标链路（挂起）**

```
POST /api/v1/user/wallet/recharges
  → ... 同上 ...
  → 调用支付渠道预下单，返回客户端支付参数

POST /callback/pay/{channel}
  → PayCallbackController 完成渠道验签
  → RechargeService::handleCallback()
      → 与 confirmManually() 复用同一入账内核（保证两条路径行为一致）
```

超时未支付订单由 `CloseExpiredRechargesJob` 定时置为 `expired`，注册到 `routes/console.php`。

## 6. 提现链路

```
POST /api/v1/user/wallet/withdraws
  → CreateWithdrawRequest 校验开关/金额区间/当日次数/待审核单
  → WithdrawService::submit()
      → 事务内按 owner 行锁查最新余额
      → 计算手续费（wallet.withdraw_fee_rate）与实际到账金额
      → WalletHelper::freeze() 冻结申请金额（source = Withdraw 本身）
      → 创建 Withdraw(status=pending)
      → 返回 201（无 data 包裹）

POST /admin/wallet/withdraws/{id}/approve
  → WithdrawService::approve()
      → 校验 status=pending（非终态）
      → 置 status=approved、reviewed_by、reviewed_at
      → 派发 WithdrawApproved 事件 → 队列发站内信

POST /admin/wallet/withdraws/{id}/reject
  → WithdrawService::reject()
      → 校验 status=pending，reason 必填
      → 置 status=rejected、reject_reason
      → WalletHelper::unfreeze() 解冻退回可用余额
      → 派发 WithdrawRejected 事件 → 队列发站内信（含原因）

POST /admin/wallet/withdraws/{id}/transfer
  → WithdrawService::markTransferred()
      → 校验 status=approved
      → 置 status=success、transfer_at
      → WalletHelper::settleFrozen() 扣减冻结余额（资金真正流出，WalletTradeType::WITHDRAW）
      → 派发 WithdrawTransferred 事件 → 队列发站内信

POST /admin/wallet/withdraws/{id}/fail
  → WithdrawService::markFailed()
      → 校验 status=approved，reason 必填
      → 置 status=failed、fail_reason
      → WalletHelper::unfreeze() 解冻退回可用余额
      → 派发 WithdrawFailed 事件 → 队列发站内信（含原因）
```

打款失败置 `failed` 并执行 `unfreeze()` 退回余额；所有状态变更均幂等（重复调用已终态记录直接返回）。

## 7. 权限与菜单

### 7.1 新增权限

| 权限标识 | 说明 |
|---------|------|
| wallet.index | 查看钱包流水与统计 |
| wallet.withdraws | 提现审核管理（列表/详情/审核/打款） |
| wallet.orders | 充值订单查看 |
| wallet.adjust | 后台调整余额（高风险操作） |
| wallet.export | 导出钱包流水/提现记录/充值订单 |

### 7.2 新增菜单

（挂在「运营管理」目录下，通过编辑初始数据迁移追加）

| 菜单 key | path | component | title | permission |
|----------|------|-----------|-------|------------|
| operation.wallet | wallet | /operation/wallet | 钱包流水 | wallet.index |
| operation.withdraw | withdraw | /operation/withdraw | 提现审核 | wallet.withdraws |
| operation.recharge | recharge | /operation/recharge | 充值订单 | wallet.orders |

按钮权限：钱包流水页挂「调账」（`wallet.adjust`）、「导出」（`wallet.export`）；提现审核页挂「审核通过」「审核驳回」「标记打款」「标记失败」（均为 `wallet.withdraws`）、「导出提现申请」（`wallet.export`）。

`wallet.adjust` 属高风险操作，后续接入敏感操作二次验证时需纳入覆盖范围。

## 8. 配置项

新增 `wallet.*` 分组系统设置（写入 settings 表种子）：

| 配置键 | 默认值 | 类型 | 说明 |
|--------|--------|------|------|
| wallet.enable_recharge | true | bool | 是否开放充值 |
| wallet.enable_withdraw | true | bool | 是否开放提现 |
| wallet.min_recharge_amount | 1 | int | 单笔最低充值金额（元） |
| wallet.max_recharge_amount | 50000 | int | 单笔最高充值金额（元） |
| wallet.recharge_bonus_rate | 0 | float | 充值赠送比例（%） |
| wallet.recharge_expire_minutes | 30 | int | 充值订单超时时长（分钟） |
| wallet.min_withdraw_amount | 100 | int | 单笔最低提现金额（元） |
| wallet.max_withdraw_amount | 50000 | int | 单笔最高提现金额（元） |
| wallet.withdraw_fee_rate | 0 | float | 提现手续费率（%） |
| wallet.daily_withdraw_limit | 3 | int | 每日提现申请次数上限 |
| wallet.withdraw_throttle | 5,1 | string | 提现接口限流（次数,分钟） |

## 9. 多态别名

`config/morph_maps.php` 新增：

```php
'wallet_trade'   => \App\Models\Wallet\WalletTrade::class,
'recharge' => \App\Models\Wallet\Recharge::class,
'withdraw' => \App\Models\Wallet\Withdraw::class,
```

`owner_type` 直接复用已注册的 `user` / `admin` 别名，无需额外注册。

## 10. 前端落地清单（laravel-skel-admin）

| 文件 | 说明 |
|------|------|
| `src/api/wallet-manage.ts` | 钱包流水、提现审核、充值订单全部 API 函数 |
| `src/types/api/api.d.ts` | 新增 `Api.WalletManage` 命名空间类型定义 |
| `src/views/operation/wallet/index.vue` | 钱包流水列表（统计卡片 + 表格） |
| `src/views/operation/wallet/modules/wallet-search.vue` | 流水搜索栏 |
| `src/views/operation/wallet/modules/wallet-trade-detail-dialog.vue` | 流水详情弹窗（含前后余额快照） |
| `src/views/operation/wallet/modules/wallet-adjust-dialog.vue` | 后台调账弹窗 |
| `src/views/operation/withdraw/index.vue` | 提现审核列表 |
| `src/views/operation/withdraw/modules/withdraw-search.vue` | 提现搜索栏 |
| `src/views/operation/withdraw/modules/withdraw-stats.vue` | 提现统计卡片 |
| `src/views/operation/withdraw/modules/withdraw-detail-drawer.vue` | 提现详情抽屉 |
| `src/views/operation/withdraw/modules/withdraw-reject-dialog.vue` | 驳回弹窗 |
| `src/views/operation/recharge/index.vue` | 充值订单列表 |
| `src/views/operation/recharge/modules/recharge-search.vue` | 充值订单搜索栏 |
| `src/views/operation/recharge/modules/recharge-detail-dialog.vue` | 充值订单详情弹窗 |
| `src/views/user/list/modules/user-detail-dialog.vue` | 现有文件追加「钱包」Tab（余额 + 流水 + 导出） |

页面路由由后端菜单动态注册，前端无需手写路由配置。

## 11. 实施阶段

| 阶段 | 内容 | 交付物 | 状态 |
|------|------|--------|------|
| **Phase 1 基础账本** | `users` 表加字段；`wallet_trades` 表；`WalletTradeType` / `WalletTradeStatus` 枚举；`HasWalletBalance` trait；`WalletHelper` 四个核心方法 + 结算方法；后台流水列表/详情/统计/调账；前台钱包概览与流水接口；PHPUnit 覆盖并发扣减与余额不足场景 | 可查询、可调账的完整账本 | ✅ **已完成**（35 用例 / 137 断言全绿） |
| **Phase 2 充值订单** | `recharges` 表 + 模型 + Policy；`PayChannel` / `RechargeStatus` 枚举；`RechargeService`（下单 + 管理员手动确认到账 + 幂等 + 超时关单）；超时关单 Job + 定时任务注册；后台充值订单管理（列表/详情/统计/确认/导出）；前台充值下单与订单查询接口；`RechargeSucceededNotification` | 订单生命周期完整，管理员可手动确认到账 | 🔄 进行中 |
| **Phase 3 提现审核** | `withdraws` 表 + 模型 + Policy；`WithdrawStatus` / `WithdrawAccountType` 枚举；`WithdrawService` 完整状态流转（submit/approve/reject/markTransferred/markFailed）；冻结/解冻/结算联动（流水 source_type=withdraw）；后台提现审核页面（列表/详情/统计/审核/打款/失败/导出）；前台提现提交（限流 `wallet.withdraw_throttle`）+ 列表 + 详情接口；4 个事件（ShouldBroadcast + ShouldQueue）与 4 个站内信通知；导出类（阈值 2000）；收款账号响应脱敏 | 提现申请到打款全流程闭环 | ✅ **已完成** 2026-09-09（42 用例全绿） |
| **Phase 4 集成与验收** | 订单/优惠券等业务侧接入冻结与结算；钱包指标并入业务数据看板；Apifox 按 Admin/Api 模块规范全量发布；补齐测试至项目 70% 覆盖率要求；README 与文档同步 | 生产可用 | 未开始 |

### 前置依赖

- Phase 4 的冻结链路需支付与订单模块先落地，否则冻结无实际业务场景
- `wallet.adjust` 高风险操作建议与敏感操作二次验证协同推进

## 12. 挂起待办

以下能力已从当前排期中挂起，待条件满足后启动。挂起不影响 Phase 2/3 交付，因为入账内核与订单状态机均已按可插拔方式设计。

### 12.1 支付渠道对接（挂起）

| 项目 | 说明 |
|------|------|
| 挂起原因 | 本地无商户号与支付证书，真实支付链路无法验证；强行实现会导致核心的幂等与入账逻辑缺乏可靠测试覆盖 |
| 恢复条件 | 提供微信支付/支付宝商户号、API 密钥与证书，并配置到 `config/services.php` |
| 待补内容 | ① `app/Payments/Contracts/PaymentGateway.php` 网关契约（`prepay` / `verifyCallback` / `queryOrder` 三个方法）；② `PaymentManager` 渠道路由；③ `WechatPayGateway`、`AlipayGateway` 实现类；④ `PayCallbackController` 统一回调入口（`POST /callback/pay/{channel}`，豁免认证与 CSRF）；⑤ `RechargeService::handleCallback()` 验签入账；⑥ `queryAndSync()` 主动查单补偿 |
| 设计约束 | 渠道接入时**不得修改** `RechargeService` 的入账内核与 `Recharge` 状态机，只允许新增网关实现类并在 `PaymentManager` 注册；回调入账必须复用 Phase 2 已验证的幂等逻辑 |
| 数据准备 | `recharges` 表在 Phase 2 已预留 `pay_channel`、`transaction_id`（唯一索引防重复入账）、`callback_payload` 三个字段，渠道接入无需再改表结构 |

### 12.2 充值套餐配置（挂起）

| 项目 | 说明 |
|------|------|
| 挂起原因 | 属运营增强功能，不阻塞资金链路 |
| 待补内容 | `recharge_plans` 表（面额、赠送金额、排序、启用状态）+ 后台 CRUD + 前台套餐列表接口 |
| 现状替代 | Phase 2 仅支持自定义金额，按 `wallet.min_recharge_amount` / `wallet.max_recharge_amount` 校验区间 |

---

> 文档来源：PRD 第 8 章
