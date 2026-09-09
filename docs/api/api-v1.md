# 前台 API v1 接口（/api/v1）

## 1. 用户认证与账户

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/api/v1/auth/login` | 用户登录（密码/手机验证码/微信/Apple） |
| POST | `/api/v1/auth/register` | 用户注册（手机/邮箱） |
| POST | `/api/v1/auth/logout` | 退出登录 |
| GET | `/api/v1/auth/info` | 当前用户信息 |

## 2. 公共接口

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/common/*` | 公共接口（短信/邮件验证码、地区等） |
| POST | `/api/v1/common/sms-captcha` | 发送短信验证码 |
| POST | `/api/v1/common/mail-captcha` | 发送邮件验证码 |

## 3. 应用版本

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/api/v1/apps/check-update` | App 版本检查更新（传 slug/platform/build，返回是否有更新、是否强制、最新版本信息） |

## 4. 用户个人中心

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/user/*` | 用户个人中心（资料、密码、通知、积分、金币、登录历史等） |
| PUT | `/api/v1/user/profile` | 修改个人资料 |
| PUT | `/api/v1/user/password` | 修改密码 |
| PUT | `/api/v1/user/phone` | 修改手机号 |
| PUT | `/api/v1/user/email` | 修改邮箱 |
| GET | `/api/v1/user/notifications` | 通知列表 |
| GET | `/api/v1/user/login-histories` | 登录历史 |
| GET | `/api/v1/user/identity` | 当前用户最新一条实名认证记录 |
| GET | `/api/v1/user/identity/status` | 简要实名认证状态 |
| POST | `/api/v1/user/identity` | 提交/重新提交实名认证（personal 个人 / enterprise 企业） |
| GET | `/api/v1/user/addresses` | 收货地址列表 |
| POST | `/api/v1/user/addresses` | 新增收货地址 |
| PUT | `/api/v1/user/addresses/{id}` | 更新收货地址 |
| DELETE | `/api/v1/user/addresses/{id}` | 删除收货地址 |

## 5. 内容互动接口

### 5.1 点赞（/api/v1/likes）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/likes` | 我的点赞列表（分页，可按 `source_type` 过滤） |
| POST | `/api/v1/likes` | 添加点赞 |
| DELETE | `/api/v1/likes/{like}` | 取消点赞 |

### 5.2 收藏（/api/v1/collections）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/collections` | 我的收藏列表（分页，可按 `source_type` 过滤） |
| POST | `/api/v1/collections` | 添加收藏 |
| DELETE | `/api/v1/collections/{collection}` | 取消收藏 |

### 5.3 评论（/api/v1/comments）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/comments` | 评论列表（分页，可按 `source_type` + `source_id` 过滤；公开接口） |
| POST | `/api/v1/comments` | 发布评论（支持 `parent_id` 回复、`mentioned_users` 艾特） |
| DELETE | `/api/v1/comments/{comment}` | 删除评论（仅本人） |

### 5.4 内容互动约定

- 点赞、收藏全部接口需 Sanctum 认证；评论仅 `POST`/`DELETE` 需认证，列表公开
- 写入参数：`source_type`（必填，须为 `config/morph_maps.php` 已注册别名）、`source_id`（必填）、`extra`（可选，JSON）
- 点赞/收藏对同一资源重复提交返回 `422`，由 `Rule::unique` 拦截
- 越权删除他人记录返回 `403`，由 `LikePolicy` / `CollectionPolicy` / `CommentPolicy` 校验
- 响应中的 `user_id` 通过模型 `#[Hidden]` 隐藏
- 列表接口使用 Laravel 标准分页，支持 `page` / `per_page`（1-100，默认 15）

## 6. 公告

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/announcements` | 公告列表 |
| GET | `/api/v1/announcements/{id}` | 公告详情 |

## 7. Banner

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/banners` | 获取公开 Banner 列表（自动过滤禁用/未开始/已过期） |

## 8. 反馈

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/api/v1/feedbacks` | 提交用户反馈 |

## 9. 钱包接口（/api/v1/user/wallet）

| 方法 | 路径 | 说明 | 状态 |
|------|------|------|------|
| GET | `/api/v1/user/wallet` | 我的钱包概览（可用余额、冻结余额、累计收入、累计支出） | ✅ 已交付 |
| GET | `/api/v1/user/wallet/trades` | 我的钱包流水（分页，可按 `type`/时间范围过滤） | ✅ 已交付 |
| POST | `/api/v1/user/wallet/recharges` | 创建充值订单（返回 `order_no`，成功返回 201） | ✅ 已交付 |
| GET | `/api/v1/user/wallet/recharges` | 我的充值订单列表（分页，可按 `pay_status` 过滤） | ✅ 已交付 |
| GET | `/api/v1/user/wallet/recharges/{recharge}` | 充值订单详情（用于轮询到账状态） | ✅ 已交付 |
| POST | `/api/v1/user/wallet/withdraws` | 提交提现申请（`amount`、`account_type`∈wechat/alipay/bank、`account_info:{account,real_name}`，成功返回 201，冻结对应金额） | ✅ 已交付 |
| GET | `/api/v1/user/wallet/withdraws` | 我的提现列表（分页，可按 `status` 过滤，收款账号脱敏） | ✅ 已交付 |
| GET | `/api/v1/user/wallet/withdraws/{withdraw}` | 提现详情（Policy 校验归属，收款账号脱敏） | ✅ 已交付 |

### 钱包接口约定

- 全部接口需 Sanctum 认证，内部统一取 `auth()->user()` 作为 owner，前台无需感知多态
- 充值下单走 `wallet.recharge_throttle` 限流，提现提交走 `wallet.withdraw_throttle`（默认 5 次/分钟），防止刷单
- 越权访问他人订单返回 `403`，由 `RechargePolicy` / `WithdrawPolicy` 校验
- 提现提交需校验：开关（`wallet.enable_withdraw`）、金额区间（`wallet.min_withdraw_amount`/`wallet.max_withdraw_amount`）、当日次数（`wallet.daily_withdraw_limit`）、不存在待审核单；余额不足返回 `422`
- 金额字段统一以元为单位、`decimal(16,2)` 精度返回字符串，避免前端浮点精度丢失
- 前台提现响应使用 `Api\V1\WithdrawResource`，脱敏展示，不返回 `owner`、`client_ip`、`reviewed_by`、`remark` 等内部字段
- 支付渠道对接已挂起，`pay_channel` 当前仅接受 `manual`，到账由管理员在后台确认

## 10. 字典公开接口

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/dict-items` | 公开字典项查询（按 code 获取字典数据） |

## 11. 支付回调（/callback）

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/callback/pay/wechat` | 微信支付异步回调（验签后置订单成功并入账） |
| POST | `/callback/pay/alipay` | 支付宝异步回调（验签后置订单成功并入账） |

回调接口豁免 CSRF 与认证中间件，必须完成渠道验签；处理逻辑幂等（同一 `order_no` 重复回调只入账一次）。

---

> 文档来源：PRD 第 6.4 节
