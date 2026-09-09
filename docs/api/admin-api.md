# 后台管理接口（/admin）

所有后台接口前缀为 `/admin`，需 `Authorization: Bearer {token}` 请求头。

## 1. 认证接口（/admin/auth）

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/admin/auth/login` | 管理员登录（账号+密码） | 否 |
| POST | `/admin/auth/wx-login` | 微信公众号授权登录 | 否 |
| POST | `/admin/auth/apple-login` | Apple 授权登录 | 否 |
| POST | `/admin/auth/refresh-token` | 刷新 Token | 是 |
| GET | `/admin/auth/tokens` | 查询已签发的所有 Token | 是 |
| DELETE | `/admin/auth/tokens/{tokenId}` | 销毁指定 Token | 是 |
| POST | `/admin/auth/logout` | 退出登录 | 是 |
| GET | `/admin/auth/info` | 获取当前管理员信息（含角色/按钮权限） | 是 |

## 2. 管理员管理（/admin/admins）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/admins` | 管理员列表（分页） | admins.index |
| POST | `/admin/admins` | 创建管理员 | admins.create |
| GET | `/admin/admins/{id}` | 管理员详情 | admins.index |
| PUT | `/admin/admins/{id}` | 更新管理员 | admins.edit |
| DELETE | `/admin/admins/{id}` | 删除管理员 | admins.delete |
| GET | `/admin/admins/profile` | 当前管理员资料 | - |
| PUT | `/admin/admins/profile` | 更新当前管理员资料 | - |
| POST | `/admin/admins/avatar` | 更新头像 | - |
| PUT | `/admin/admins/change-password` | 修改当前管理员密码 | - |
| GET | `/admin/admins/{id}/roles` | 获取管理员角色 | admins.edit |
| PUT | `/admin/admins/{id}/roles` | 分配管理员角色 | admins.edit |
| PUT | `/admin/admins/{id}/toggle-status` | 切换管理员状态 | admins.edit |
| PUT | `/admin/admins/{id}/reset-password` | 重置管理员密码 | admins.edit |
| GET | `/admin/admins/{id}/login-histories` | 管理员登录历史 | admins.index |

## 3. 前台用户管理（/admin/users）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/users` | 用户列表（分页） | users.index |
| GET | `/admin/users/{id}` | 用户详情 | users.index |
| PUT | `/admin/users/{id}` | 更新用户信息 | users.edit |
| DELETE | `/admin/users/{id}` | 删除用户 | users.delete |
| PUT | `/admin/users/{id}/toggle-status` | 切换用户状态 | users.edit |
| PUT | `/admin/users/{id}/reset-password` | 重置用户密码 | users.edit |
| PUT | `/admin/users/{id}/reset-contact` | 重置用户手机号/邮箱 | users.edit |
| PUT | `/admin/users/{id}/adjust-balance` | 调整用户积分/金币余额 | users.edit |
| PUT | `/admin/users/{id}/extend-vip` | 延长 VIP 有效期 | users.edit |
| GET | `/admin/users/{id}/login-histories` | 用户登录历史 | users.index |
| GET | `/admin/users/{id}/socials` | 用户第三方绑定 | users.index |
| GET | `/admin/users/{id}/point-trades` | 用户积分流水 | users.index |
| GET | `/admin/users/{id}/coin-trades` | 用户金币流水 | users.index |
| GET | `/admin/users/export` | 导出用户列表（xlsx） | users.export |
| GET | `/admin/users/{id}/point-trades/export` | 导出用户积分流水（xlsx） | users.export |
| GET | `/admin/users/{id}/coin-trades/export` | 导出用户金币流水（xlsx） | users.export |
| GET | `/admin/users/{user}/wallet` | 指定用户钱包概览 | users.index |
| GET | `/admin/users/{user}/wallet-trades` | 指定用户钱包流水（分页） | users.index |
| PUT | `/admin/users/{user}/adjust-wallet` | 后台调整用户余额 | wallet.adjust |

## 4. 角色管理（/admin/roles）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/roles` | 角色列表 | roles.index |
| POST | `/admin/roles` | 创建角色 | roles.create |
| GET | `/admin/roles/{id}` | 角色详情 | roles.index |
| PUT | `/admin/roles/{id}` | 更新角色 | roles.edit |
| DELETE | `/admin/roles/{id}` | 删除角色 | roles.delete |
| GET | `/admin/roles/permissions` | 获取全部权限列表 | roles.index |
| GET | `/admin/roles/{id}/permissions` | 获取角色权限 | roles.index |
| PUT | `/admin/roles/{id}/permissions` | 分配角色权限 | roles.edit |

## 5. 菜单管理（/admin/menus）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/menus` | 获取菜单树 | menus.index |
| POST | `/admin/menus` | 创建菜单 | menus.create |
| GET | `/admin/menus/{id}` | 菜单详情 | menus.index |
| PUT | `/admin/menus/{id}` | 更新菜单 | menus.edit |
| DELETE | `/admin/menus/{id}` | 删除菜单 | menus.delete |
| GET | `/admin/routes` | 获取前端路由配置（按当前管理员权限过滤） | - |

## 6. 缓存管理（/admin/cache）

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/admin/cache/clear` | 清理应用缓存（types=all/settings，支持数组多选） | 是 |

## 7. 配置管理（/admin/settings）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/settings` | 配置列表（分页） | settings.index |
| POST | `/admin/settings` | 创建配置 | settings.create |
| GET | `/admin/settings/{id}` | 配置详情 | settings.index |
| PUT | `/admin/settings/{id}` | 更新配置 | settings.edit |
| DELETE | `/admin/settings/{id}` | 删除配置 | settings.delete |
| GET | `/admin/settings/groups` | 获取配置分组列表 | settings.index |
| GET | `/admin/settings/input-types` | 获取支持的输入类型 | settings.index |
| PUT | `/admin/settings/batch` | 批量更新配置值 | settings.edit |

## 8. 字典管理（/admin/dicts）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/dicts` | 字典类型列表（分页，支持关键字/状态筛选） | dicts.index |
| POST | `/admin/dicts` | 创建字典类型 | dicts.create |
| GET | `/admin/dicts/{id}` | 字典详情（含字典项） | dicts.index |
| PUT | `/admin/dicts/{id}` | 更新字典 | dicts.edit |
| DELETE | `/admin/dicts/{id}` | 删除字典（级联删除字典项） | dicts.delete |

## 9. 字典数据管理（/admin/dict-items）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/dict-items` | 字典项列表（分页，支持按 dict_id 筛选） | dict-items.index |
| POST | `/admin/dict-items` | 创建字典项 | dict-items.create |
| GET | `/admin/dict-items/{id}` | 字典项详情 | dict-items.index |
| PUT | `/admin/dict-items/{id}` | 更新字典项 | dict-items.edit |
| DELETE | `/admin/dict-items/{id}` | 删除字典项 | dict-items.delete |

## 10. 地区管理（/admin/areas）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/areas` | 获取地区树 | areas.index |
| POST | `/admin/areas` | 创建地区 | areas.create |
| GET | `/admin/areas/{id}` | 地区详情 | areas.index |
| PUT | `/admin/areas/{id}` | 更新地区 | areas.edit |
| DELETE | `/admin/areas/{id}` | 删除地区 | areas.delete |

## 11. 通知管理（/admin/notifications）

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/admin/notifications` | 通知列表（分页） | 是 |
| GET | `/admin/notifications/unread` | 未读通知列表/未读数 | 是 |
| PUT | `/admin/notifications/mark-read` | 标记单条已读 | 是 |
| PUT | `/admin/notifications/mark-all-read` | 全部标记已读 | 是 |
| DELETE | `/admin/notifications/clear-read` | 清理已读通知 | 是 |

## 12. 评论管理（/admin/comments）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/comments` | 评论列表（分页，支持 `status`/`source_type`/`keyword`/`user_id` 过滤） | comments.index |
| GET | `/admin/comments/{comment}` | 评论详情（含 `ip_address`、`is_reply`、`parent_comment` 上级评论摘要） | comments.index |
| GET | `/admin/comments/{comment}/replies` | 回复列表（分页，返回该评论下的直接回复） | comments.index |
| POST | `/admin/comments/{comment}/review` | 审核评论（`status` 取 `approved`/`rejected`/`review`，可选 `remark`） | comments.edit |
| DELETE | `/admin/comments/{comment}` | 删除评论 | comments.delete |
| GET | `/admin/comments/export` | 导出评论列表（xlsx） | comments.export |

> 评论父子关系通过多态自关联实现：回复的 `source_type='comment'`、`source_id` 指向父评论 ID。列表接口不返回 `ip_address`，仅详情接口返回。

## 13. 实名认证（/admin/identity-verifications）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/identity-verifications` | 认证记录列表（分页，支持 `keyword`/`user_id`/`type`/`status`/`start_date`/`end_date` 过滤） | identity-verifications.index |
| GET | `/admin/identity-verifications/stats` | 认证统计（总量/待审/今日新增/已通过/已驳回） | identity-verifications.index |
| GET | `/admin/identity-verifications/{verification}` | 认证记录详情（含证件附件 URL） | identity-verifications.index |
| POST | `/admin/identity-verifications/{verification}/approve` | 审核通过（同步 `users.identity_verified`/`identity_type`） | identity-verifications.edit |
| POST | `/admin/identity-verifications/{verification}/reject` | 审核驳回（必填 `reason`、可选 `remark`） | identity-verifications.edit |

> 同一用户同时仅允许一条 `pending`/`review` 记录；已认证用户禁止再次提交，驳回后可重新提交。

## 14. 反馈管理（/admin/feedbacks）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/feedbacks` | 反馈列表（分页，支持 `keyword`/`type`/`status`/`priority` 过滤） | feedbacks.index |
| GET | `/admin/feedbacks/stats` | 反馈统计（总量、待推进、今日新增、已闭环） | feedbacks.index |
| GET | `/admin/feedbacks/{feedback}` | 反馈详情（含附件图片、客户端信息） | feedbacks.index |
| PUT | `/admin/feedbacks/{feedback}` | 处理反馈（调整 `status`/`priority`、填写 `admin_remark`） | feedbacks.edit |
| DELETE | `/admin/feedbacks/{feedback}` | 删除反馈 | feedbacks.delete |
| GET | `/admin/feedbacks/export` | 导出反馈列表（xlsx） | feedbacks.export |

## 15. 公共选项（/admin/common）

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/admin/common/source-types` | 多态源类型选项列表（基于 `config/morph_maps.php`，返回 `[{label, value}]`） | 是 |

> 与业务模块无关的通用选项、枚举、字典类接口统一由 `CommonController` 承载，仅需登录态，不绑定业务权限。

## 16. 验证码管理

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/phone-codes` | 短信验证码列表 | phone-codes.index |
| GET | `/admin/phone-codes/{id}` | 短信验证码详情 | phone-codes.index |
| GET | `/admin/mail-codes` | 邮件验证码列表 | mail-codes.index |
| GET | `/admin/mail-codes/{id}` | 邮件验证码详情 | mail-codes.index |

## 17. 上传管理（/admin/uploader）

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/admin/uploader/file` | 上传通用文件 | 是 |
| POST | `/admin/uploader/image` | 上传图片 | 是 |
| POST | `/admin/uploader/video` | 上传视频 | 是 |
| POST | `/admin/uploader/apk` | 上传 APK 安装包 | 是 |
| POST | `/admin/uploader/token` | 获取直传 Token（S3 兼容） | 是 |

上传成功后返回字段包含附件 `id`，前端可直接用于附件管理。

## 18. 附件管理（/admin/attachments）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/attachments/disks` | 获取可用存储磁盘列表 | attachments.index |
| GET | `/admin/attachments` | 附件列表（分页+筛选） | attachments.index |
| POST | `/admin/attachments/register` | 直传完成后登记元数据 | attachments.create |
| GET | `/admin/attachments/{id}` | 附件详情 | attachments.index |
| PUT | `/admin/attachments/{id}/rename` | 重命名显示名 | attachments.edit |
| PUT | `/admin/attachments/{id}/move` | 移动物理路径 | attachments.edit |
| DELETE | `/admin/attachments/{id}` | 删除单个附件 | attachments.delete |
| DELETE | `/admin/attachments` | 批量删除附件（ids 数组） | attachments.delete |
| GET | `/admin/attachments/{id}/download` | 流式下载 | attachments.index |
| GET | `/admin/attachments/{id}/temporary-url` | 临时签名 URL | attachments.index |

列表筛选参数：`per_page`、`type`、`disk`、`keyword`、`extension`、`uploader_id`、`start_date`、`end_date`。

附件资源字段：

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 附件 ID |
| name | string | 显示名称 |
| original_name | string | 原始文件名 |
| disk | string | 存储磁盘 |
| path | string | 存储路径 |
| url | string\|null | 可访问 URL（公有盘直链，私有盘为 null） |
| type | object | 附件类型（value + label） |
| extension | string | 扩展名 |
| mime_type | string | MIME 类型 |
| size | int | 字节数 |
| size_text | string | 人类可读大小 |
| exists | bool | 物理文件是否存在（仅详情接口返回） |
| uploader | object\|null | 上传者摘要（id + name） |
| created_at | string | 创建时间 |

## 19. 应用管理（/admin/apps）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/apps` | 应用列表（分页，支持关键字/状态/日期筛选，预加载各平台最新版本） | apps.index |
| POST | `/admin/apps` | 创建应用 | apps.create |
| GET | `/admin/apps/{id}` | 应用详情 | apps.index |
| PUT | `/admin/apps/{id}` | 更新应用 | apps.edit |
| DELETE | `/admin/apps/{id}` | 删除应用（软删除） | apps.delete |

应用资源字段包含 `latest_versions`（各平台最多 3 条已发布版本，按 build 倒序）。

## 20. 应用版本管理（/admin/apps/{app}/versions）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/apps/{app}/versions` | 版本列表（分页，支持平台/状态/版本号/强制更新/日期筛选） | app-versions.index |
| POST | `/admin/apps/{app}/versions` | 创建版本 | app-versions.create |
| GET | `/admin/apps/{app}/versions/{version}` | 版本详情 | app-versions.index |
| PUT | `/admin/apps/{app}/versions/{version}` | 更新版本 | app-versions.edit |
| DELETE | `/admin/apps/{app}/versions/{version}` | 删除版本（已发布状态不可删除） | app-versions.delete |
| POST | `/admin/apps/{app}/versions/{version}/publish` | 发布版本（自动将同平台旧已发布版本置为废弃） | app-versions.edit |
| POST | `/admin/apps/{app}/versions/{version}/withdraw` | 撤回版本（仅已发布状态可撤回） | app-versions.edit |
| POST | `/admin/apps/{app}/versions/{version}/obsolete` | 废弃版本 | app-versions.edit |

## 21. 审计日志（/admin/audit-logs）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/audit-logs` | 审计日志列表（分页，支持时间范围/管理员/方法筛选） | audit-logs.index |
| GET | `/admin/audit-logs/{id}` | 审计日志详情（含请求体、响应） | audit-logs.index |
| GET | `/admin/audit-logs/export` | 导出审计日志（xlsx） | audit-logs.export |

## 22. 调度任务日志（/admin/schedule-logs）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/schedule-logs` | 调度日志列表（分页，支持任务名/状态/时间范围筛选） | schedule-logs.index |
| GET | `/admin/schedule-logs/{id}` | 调度日志详情 | schedule-logs.index |

## 23. 钱包管理（/admin/wallet）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/wallet/stats` | 钱包统计（总余额、总冻结、累计充值、累计提现、待审提现数） | wallet.index |
| GET | `/admin/wallet/trades` | 全局钱包流水列表（分页，支持 `owner_type`/`owner_id`/`type`/`status`/关键词/时间范围筛选） | wallet.index |
| GET | `/admin/wallet/trades/{trade}` | 钱包流水详情（含持有者摘要、业务来源摘要、前后余额快照） | wallet.index |
| GET | `/admin/wallet/trades/export` | 导出钱包流水（xlsx） | wallet.export |

## 24. 提现审核（/admin/wallet/withdraws）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/wallet/withdraws/stats` | 提现统计（总量/待审/今日新增/已通过/已驳回/打款成功/打款失败） | wallet.withdraws |
| GET | `/admin/wallet/withdraws` | 提现申请列表（分页，支持 `owner_type`/`owner_id`/`status`/`account_type`/`order_no`/`start_date`/`end_date` 筛选） | wallet.withdraws |
| GET | `/admin/wallet/withdraws/{withdraw}` | 提现申请详情（含 `owner` 关联、收款账户脱敏展示） | wallet.withdraws |
| POST | `/admin/wallet/withdraws/{withdraw}/approve` | 审核通过（可选 `remark`，状态转 `approved`，保持冻结待打款） | wallet.withdraws |
| POST | `/admin/wallet/withdraws/{withdraw}/reject` | 审核驳回（必填 `reason`、可选 `remark`，自动解冻并通知用户） | wallet.withdraws |
| POST | `/admin/wallet/withdraws/{withdraw}/transfer` | 标记已打款（可选 `remark`，状态转 `success`，扣减冻结余额并通知用户） | wallet.withdraws |
| POST | `/admin/wallet/withdraws/{withdraw}/fail` | 标记打款失败（必填 `reason`、可选 `remark`，自动解冻并通知用户） | wallet.withdraws |
| GET | `/admin/wallet/withdraws/export` | 导出提现申请（xlsx，阈值 2000 条同步导出） | wallet.export |

> 提现状态流转：`pending` →（approve）`approved` →（transfer）`success`；`pending` →（reject）`rejected`（自动解冻退回）；`approved` →（fail）`failed`（自动解冻退回）。已进入终态（`success`/`rejected`/`failed`）的记录不可重复操作，接口幂等返回。响应使用 `Admin\WithdrawResource`，收款账号通过 `maskedAccountInfo()` 脱敏（前 2 + `****` + 后 4）。

## 25. 充值订单（/admin/wallet/recharges）

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | `/admin/wallet/recharges` | 充值订单列表（分页，支持 `owner_type`/`owner_id`/`pay_status`/`pay_channel`/`order_no`/时间范围筛选） | wallet.orders |
| GET | `/admin/wallet/recharges/stats` | 充值统计（累计/今日金额、赠送总额、订单数、成功率） | wallet.orders |
| GET | `/admin/wallet/recharges/{recharge}` | 充值订单详情（含渠道原始回调报文） | wallet.orders |
| POST | `/admin/wallet/recharges/{recharge}/confirm` | 手动确认到账（幂等，入账并发站内信通知） | wallet.orders |
| POST | `/admin/wallet/recharges/{recharge}/close` | 关闭订单（仅待支付可关闭） | wallet.orders |
| GET | `/admin/wallet/recharges/export` | 导出充值订单（xlsx） | wallet.export |

> 支付渠道对接已挂起，当前到账依赖管理员手动确认。确认接口具备三重幂等保障（行锁 + 状态判定 + `transaction_id` 唯一索引），重复调用不会重复入账。详见 `docs/technical-solutions/wallet.md` 第 12.1 节。

## 26. 数据概览（/admin/dashboard）

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/admin/dashboard/stats` | 仪表盘统计数据 | 是 |

## 27. 监控接口（/admin/monitor，读取 Pulse 数据）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/monitor/servers` | 服务器性能指标 |
| GET | `/admin/monitor/queues` | 队列负载 |
| GET | `/admin/monitor/reverb/connections` | Reverb 连接数 |
| GET | `/admin/monitor/reverb/messages` | Reverb 消息吞吐 |
| GET | `/admin/monitor/cache` | 缓存命中率 |
| GET | `/admin/monitor/exceptions` | 异常统计 |
| GET | `/admin/monitor/slow-queries` | 慢查询 |
| GET | `/admin/monitor/slow-requests` | 慢请求 |
| GET | `/admin/monitor/slow-jobs` | 慢任务 |
| GET | `/admin/monitor/slow-outgoing-requests` | 慢外部请求 |
| GET | `/admin/monitor/usage` | 使用情况 |

## 28. 调试接口（/admin/debug，仅 local 环境，读取 Telescope）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/debug/tags` | 获取标签列表 |
| POST | `/admin/debug/tags` | 监控指定标签 |
| DELETE | `/admin/debug/tags` | 取消监控标签 |
| POST | `/admin/debug/toggle-recording` | 开启/暂停记录 |
| GET | `/admin/debug/entries` | 条目列表（分页） |
| GET | `/admin/debug/entries/{id}` | 条目详情 |
| PUT | `/admin/debug/entries/{id}/resolve` | 标记已解决 |
| DELETE | `/admin/debug/entries` | 删除条目 |

## 29. 队列管理（/admin/queue，代理 Horizon 数据）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/queue/stats` | 队列总体统计 |
| GET | `/admin/queue/workload` | 队列工作负载 |
| GET | `/admin/queue/masters` | Master 进程状态 |
| GET | `/admin/queue/monitoring/tags` | 监控标签列表 |
| GET | `/admin/queue/monitoring/jobs` | 监控任务列表 |
| POST | `/admin/queue/monitoring/tags` | 添加监控标签 |
| DELETE | `/admin/queue/monitoring/tags/{tag}` | 移除监控标签 |
| GET | `/admin/queue/metrics/jobs` | 任务吞吐指标 |
| GET | `/admin/queue/metrics/jobs/{id}` | 任务指标详情 |
| GET | `/admin/queue/metrics/queues` | 队列指标 |
| GET | `/admin/queue/metrics/queues/{id}` | 队列指标详情 |
| GET | `/admin/queue/batches` | 批处理列表 |
| GET | `/admin/queue/batches/{id}` | 批处理详情 |
| POST | `/admin/queue/batches/{id}/retry` | 重试失败批次 |
| GET | `/admin/queue/jobs/pending` | 待处理任务 |
| GET | `/admin/queue/jobs/completed` | 已完成任务 |
| GET | `/admin/queue/jobs/silenced` | 静默任务 |
| GET | `/admin/queue/jobs/failed` | 失败任务 |
| GET | `/admin/queue/jobs/failed/{id}` | 失败任务详情 |
| POST | `/admin/queue/jobs/failed/{id}/retry` | 重试失败任务 |
| GET | `/admin/queue/jobs/{id}` | 任务详情 |

## 30. AI 聊天（/admin/chat，SSE 流式）

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/admin/chat/conversations` | 会话列表 | 是 |
| GET | `/admin/chat/conversations/{id}` | 会话详情（含消息历史） | 是 |
| POST | `/admin/chat` | 发送消息（SSE 流式响应） | 是 |
| POST | `/admin/chat/approve` | 审批工具调用并继续执行 | 是 |
| DELETE | `/admin/chat/conversations/{id}` | 删除会话 | 是 |

SSE 流式响应通过 `text/event-stream` 逐行推送以下事件类型：
- `text`：文本增量
- `reasoning`：思考过程
- `tool_call`：工具调用信息
- `tool_approval_required`：需要人工审批的敏感操作
- `error`：错误
- `done`：流结束

---

> 文档来源：PRD 第 6.3 节
