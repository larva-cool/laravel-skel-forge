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
│       ├── RechargeOrder.php           # 充值订单（morphTo owner）
│       └── WithdrawOrder.php           # 提现申请（morphTo owner）
├── Support/
│   └── WalletHelper.php                # 余额引擎（incr/decr/freeze/unfreeze）
├── Services/
│   ├── RechargeService.php             # 充值下单与回调入账编排
│   └── WithdrawService.php             # 提现申请与审核流转编排
├── Events/Wallet/
│   ├── BalanceChanged.php              # 余额变动事件
│   ├── RechargeSucceeded.php           # 充值成功事件
│   ├── WithdrawApproved.php            # 提现审核通过事件
│   └── WithdrawRejected.php            # 提现驳回事件
├── Notifications/Wallet/
│   ├── RechargeSucceededNotification.php
│   ├── WithdrawApprovedNotification.php
│   ├── WithdrawRejectedNotification.php
│   └── WithdrawTransferredNotification.php
├── Jobs/Wallet/
│   └── CloseExpiredRechargeOrdersJob.php   # 关闭超时未支付订单
├── Policies/
│   ├── RechargeOrderPolicy.php
│   └── WithdrawOrderPolicy.php
├── Exports/Admin/
│   ├── WalletTradeExport.php
│   ├── WithdrawOrderExport.php
│   └── RechargeOrderExport.php
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── WalletController.php            # 流水列表/详情/统计/调账
│   │   │   ├── WithdrawOrderController.php     # 提现审核
│   │   │   └── RechargeOrderController.php     # 充值订单查看
│   │   ├── Api/V1/User/
│   │   │   └── WalletController.php            # 前台钱包概览/流水/充值/提现
│   │   └── PayCallbackController.php           # 支付渠道异步回调
│   ├── Requests/
│   │   ├── Admin/Wallet/
│   │   │   ├── WalletTradeIndexRequest.php
│   │   │   ├── WalletAdjustRequest.php
│   │   │   ├── WithdrawIndexRequest.php
│   │   │   └── WithdrawRejectRequest.php
│   │   └── Api/V1/Wallet/
│   │       ├── CreateRechargeRequest.php
│   │       └── CreateWithdrawRequest.php
│   └── Resources/
│       ├── Admin/
│       │   ├── WalletTradeResource.php
│       │   ├── WalletStatResource.php
│       │   ├── WithdrawOrderResource.php
│       │   └── RechargeOrderResource.php
│       └── Api/V1/
│           ├── WalletResource.php
│           ├── WalletTradeResource.php
│           ├── WithdrawOrderResource.php
│           └── RechargeOrderResource.php
database/
├── migrations/
│   ├── xxxx_add_balance_to_users_table.php     # users 表加 balance/frozen_balance
│   ├── xxxx_create_wallet_trades_table.php
│   ├── xxxx_create_recharge_orders_table.php
│   └── xxxx_create_withdraw_orders_table.php
└── factories/Wallet/
    ├── WalletTradeFactory.php
    ├── RechargeOrderFactory.php
    └── WithdrawOrderFactory.php
tests/Feature/
├── Admin/
│   ├── WalletControllerTest.php
│   ├── WithdrawOrderControllerTest.php
│   └── RechargeOrderControllerTest.php
└── Api/V1/
    └── WalletControllerTest.php
```

## 3. HasWalletBalance Trait

任何主表含 `balance` / `frozen_balance` 两列的模型 `use HasWalletBalance` 即获得完整钱包能力：

| 方法 | 职责 |
|------|------|
| `walletTrades(): MorphMany` | 钱包流水（作为 owner） |
| `rechargeOrders(): MorphMany` | 充值订单（作为 owner） |
| `withdrawOrders(): MorphMany` | 提现申请（作为 owner） |
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

```
POST /api/v1/wallet/recharge
  → CreateRechargeRequest 校验金额区间
  → RechargeService::create()
      → 计算赠送金额（wallet.recharge_bonus_rate）
      → 生成 order_no（R + 日期 + 随机串）
      → 创建 RechargeOrder(pay_status=pending, expired_at=now+30min)
      → 调用支付渠道预下单，返回客户端支付参数

POST /callback/pay/{channel}
  → PayCallbackController 完成渠道验签
  → RechargeService::handleCallback()
      → 事务内按 order_no 加锁查单，已 success 直接返回（幂等）
      → 置 pay_status=success、paid_at
      → WalletHelper::incr() 入账（amount + bonus_amount）
      → 派发 RechargeSucceeded 事件 → 队列发站内信
```

超时未支付订单由 `CloseExpiredRechargeOrdersJob` 定时置为 `expired`，注册到 `routes/console.php`。

## 6. 提现链路

```
POST /api/v1/wallet/withdraw
  → CreateWithdrawRequest 校验金额、限额、当日次数
  → WithdrawService::submit()
      → 事务内校验可用余额
      → 计算手续费与实际到账金额
      → WalletHelper::freeze() 冻结申请金额
      → 创建 WithdrawOrder(status=pending)

POST /admin/wallet/withdraws/{id}/approve
  → WithdrawService::approve()
      → 校验当前状态非终态（参照 IdentityVerificationController::authorizeAction）
      → 置 status=approved、reviewed_by、reviewed_at
      → 派发 WithdrawApproved 事件 → 通知用户

POST /admin/wallet/withdraws/{id}/reject
  → WithdrawService::reject()
      → 置 status=rejected、reject_reason
      → WalletHelper::unfreeze() 解冻退回可用余额
      → 派发 WithdrawRejected 事件 → 通知用户

POST /admin/wallet/withdraws/{id}/transfer
  → WithdrawService::markTransferred()
      → 置 status=success、transfer_at
      → WalletHelper::settleFrozen() 扣减冻结余额（资金真正流出）
      → 通知用户到账
```

打款失败置 `failed` 并同样执行 `unfreeze()` 退回余额。

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

按钮权限：钱包流水页挂「调账」（`wallet.adjust`）、「导出」（`wallet.export`）；提现审核页挂「审核」（`wallet.withdraws`）、「导出」（`wallet.export`）。

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
| wallet.recharge_order_expire_minutes | 30 | int | 充值订单超时时长（分钟） |
| wallet.min_withdraw_amount | 100 | int | 单笔最低提现金额（元） |
| wallet.max_withdraw_amount | 50000 | int | 单笔最高提现金额（元） |
| wallet.withdraw_fee_rate | 0 | float | 提现手续费率（%） |
| wallet.daily_withdraw_limit | 3 | int | 每日提现申请次数上限 |
| wallet.withdraw_throttle | 3,1 | string | 提现接口限流（次数,分钟） |

## 9. 多态别名

`config/morph_maps.php` 新增：

```php
'wallet_trade'   => \App\Models\Wallet\WalletTrade::class,
'recharge_order' => \App\Models\Wallet\RechargeOrder::class,
'withdraw_order' => \App\Models\Wallet\WithdrawOrder::class,
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

| 阶段 | 内容 | 交付物 |
|------|------|--------|
| **Phase 1 基础账本** | `users` 表加字段；`wallet_trades` 表；`WalletTradeType` / `WalletTradeStatus` 枚举；`HasWalletBalance` trait；`WalletHelper` 四个核心方法 + 结算方法；后台流水列表/详情/统计/调账；用户详情弹窗钱包 Tab；前台钱包概览与流水接口；PHPUnit 覆盖并发扣减与余额不足场景 | 可查询、可调账的完整账本 |
| **Phase 2 充值闭环** | `recharge_orders` 表 + 模型 + Policy；`PayChannel` 枚举；`RechargeService`；微信支付渠道对接与回调验签；幂等入账；超时关单 Job + 定时任务注册；后台充值订单管理；前台充值接口；`RechargeSucceededNotification` | 用户可充值到账 |
| **Phase 3 提现审核** | `withdraw_orders` 表 + 模型 + Policy；`WithdrawStatus` / `WithdrawAccountType` 枚举；`WithdrawService` 完整状态流转；冻结/解冻/结算联动；后台提现审核页面；前台提现接口 + 限流；三个提现通知类；导出类 | 提现申请到打款全流程闭环 |
| **Phase 4 集成与验收** | 订单/优惠券等业务侧接入冻结与结算；钱包指标并入业务数据看板；Apifox 按 Admin/Api 模块规范全量发布；补齐测试至项目 70% 覆盖率要求；README 与本 PRD 同步 | 生产可用 |

### 前置依赖

- Phase 2 需先在 `config/services.php` 配置微信支付/支付宝商户参数与证书
- Phase 4 的冻结链路需支付与订单模块先落地，否则冻结无实际业务场景
- `wallet.adjust` 高风险操作建议与敏感操作二次验证协同推进

---

> 文档来源：PRD 第 8 章
