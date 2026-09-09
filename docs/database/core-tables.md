# 核心数据表

## 1. 管理员表 (admin_users)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| user_id | BIGINT | 主键（统一 user_id 命名） |
| user_name | VARCHAR | 用户名 |
| email | VARCHAR | 邮箱地址 |
| phone | VARCHAR | 手机号 |
| name | VARCHAR | 昵称/姓名 |
| avatar | VARCHAR | 头像 URL |
| status | TINYINT | 状态（0禁用，1启用） |
| password | VARCHAR | 密码哈希 |
| login_count | INT | 登录次数 |
| last_login_ip | VARCHAR | 最后登录 IP |
| last_login_at | TIMESTAMP | 最后登录时间 |
| last_active_at | TIMESTAMP | 最后活动时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

## 2. 后台菜单表 (admin_menus)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| menu_id | BIGINT | 主键 |
| parent_id | BIGINT | 父级菜单 ID |
| path | VARCHAR | 路由路径 |
| name | VARCHAR | 路由名称 |
| component | VARCHAR | 组件路径 |
| redirect | VARCHAR | 重定向路径 |
| title | VARCHAR | 菜单标题 |
| icon | VARCHAR | 菜单图标 |
| type | TINYINT | 类型（0目录，1菜单，2按钮） |
| permission | VARCHAR | 权限标识 |
| is_enable | BOOLEAN | 是否启用 |
| is_hide | BOOLEAN | 是否隐藏菜单 |
| is_hide_tab | BOOLEAN | 是否隐藏标签 |
| is_iframe | BOOLEAN | 是否 iframe 嵌入 |
| keep_alive | BOOLEAN | 是否缓存 |
| is_full_page | BOOLEAN | 是否全屏 |
| fixed_tab | BOOLEAN | 是否固定标签 |
| show_badge | BOOLEAN | 是否显示徽章 |
| show_text_badge | VARCHAR | 徽章文本 |
| active_path | VARCHAR | 激活路径 |
| link | VARCHAR | 外部链接 |
| sort | INT | 排序 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

## 3. 用户表 (users)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| user_id | BIGINT | 主键 |
| user_name | VARCHAR | 用户名 |
| email | VARCHAR | 邮箱地址 |
| mobile | VARCHAR | 手机号 |
| avatar | VARCHAR | 头像 |
| status | TINYINT | 状态 |
| available_points | INT | 可用积分 |
| available_coins | INT | 可用金币 |
| balance | DECIMAL(16,2) | 钱包可用余额（元） |
| frozen_balance | DECIMAL(16,2) | 钱包冻结余额（元） |
| vip_expires_at | TIMESTAMP | VIP 过期时间 |
| password | VARCHAR | 密码哈希 |
| login_count | INT | 登录次数 |
| last_login_ip | VARCHAR | 最后登录 IP |
| last_login_at | TIMESTAMP | 最后登录时间 |
| last_active_at | TIMESTAMP | 最后活动时间 |
| email_verified_at | TIMESTAMP | 邮箱验证时间 |
| mobile_verified_at | TIMESTAMP | 手机验证时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

## 4. 用户扩展表

| 表名 | 说明 |
|------|------|
| user_extras | 用户额外信息（性别、生日、签名等） |
| user_profiles | 用户画像/偏好 |

## 5. 角色和权限表（spatie/laravel-permission）

| 表名 | 说明 |
|------|------|
| roles | 角色表 |
| permissions | 权限表 |
| model_has_roles | 模型角色关联表（多态） |
| model_has_permissions | 模型权限关联表（多态） |
| role_has_permissions | 角色权限关联表 |

## 6. 系统配置表 (settings)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| name | VARCHAR | 配置名称 |
| key | VARCHAR | 配置键名（唯一） |
| value | TEXT | 配置值 |
| cast_type | VARCHAR | 数据类型（string/int/float/bool/json） |
| input_type | VARCHAR | 输入类型 |
| param | TEXT | 参数配置（JSON） |
| remark | VARCHAR | 备注 |
| sort | INT | 排序 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

## 7. 地区表 (areas)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| parent_id | BIGINT | 父级地区 ID |
| name | VARCHAR | 地区名称 |
| area_code | INT | 地区代码 |
| city_code | VARCHAR | 城市代码 |
| lat | DECIMAL | 纬度 |
| lng | DECIMAL | 经度 |
| level | TINYINT | 层级 |
| sort | INT | 排序 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

## 8. 验证码表

| 表名 | 说明 | 关键字段 |
|------|------|----------|
| phone_codes | 短信验证码 | phone、scene、code、state、ip、send_at |
| mail_codes | 邮件验证码 | email、scene、code、state、ip、send_at |

## 9. 登录历史表 (login_histories)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| user_type | VARCHAR | 用户类型（多态：App\Models\User / App\Models\Admin\Admin） |
| user_id | BIGINT | 用户 ID |
| ip | VARCHAR | 登录 IP |
| port | INT | 端口 |
| platform | VARCHAR | 平台 |
| device | VARCHAR | 设备 |
| browser | VARCHAR | 浏览器 |
| user_agent | TEXT | User-Agent |
| address | VARCHAR | 地址 |
| login_at | TIMESTAMP | 登录时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

## 10. 附件表 (attachments)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键（从 10000000 起） |
| uploader_type | VARCHAR | 上传者类型（多态） |
| uploader_id | BIGINT | 上传者 ID |
| disk | VARCHAR | 存储磁盘（local/public/s3 等） |
| path | VARCHAR | 物理存储路径 |
| name | VARCHAR | 显示名称 |
| original_name | VARCHAR | 原始文件名 |
| extension | VARCHAR | 文件扩展名 |
| mime_type | VARCHAR | MIME 类型 |
| type | VARCHAR | 附件类型（image/video/audio/document/other） |
| size | BIGINT | 文件大小（字节） |
| hash | CHAR(32) | 文件 MD5 哈希（用于去重校验） |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

**索引**：`(uploader_type, uploader_id)` 复合索引、`type` 索引、`hash` 索引、`(disk, path)` 唯一索引。

## 11. 审计日志表 (audit_logs)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| admin_id | BIGINT | 操作管理员 ID |
| method | VARCHAR | HTTP 方法 |
| path | VARCHAR | 请求路径 |
| query | TEXT | Query 参数（JSON） |
| body | TEXT | 请求体（JSON，脱敏） |
| status_code | INT | 响应状态码 |
| ip | VARCHAR | 请求 IP |
| user_agent | VARCHAR | User-Agent |
| duration_ms | INT | 请求耗时（毫秒） |
| created_at | TIMESTAMP | 创建时间 |

## 12. 调度任务日志表 (schedule_logs)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| task_name | VARCHAR | 任务名称 |
| task_type | VARCHAR | 任务类型（command/callback/exec） |
| expression | VARCHAR | Cron 表达式 |
| status | VARCHAR | 执行状态（running/success/failed/skipped） |
| exit_code | INT | 退出码 |
| duration_ms | INT | 执行耗时（毫秒） |
| exception | TEXT | 异常信息摘要 |
| started_at | TIMESTAMP | 开始时间 |
| finished_at | TIMESTAMP | 结束时间 |
| created_at | TIMESTAMP | 创建时间 |

通过 `Schedule::task` 的事件监听自动记录，支持按 `schedule.log_prunable_days` 设置自动清理。

## 13. AI 对话表

| 表名 | 说明 |
|------|------|
| agent_conversations | AI 会话列表（管理员 ID、标题、模型、创建/更新时间） |
| agent_messages | AI 消息记录（会话 ID、角色 user/assistant/tool、内容、工具调用信息等） |

## 14. 其他核心表

| 表名 | 说明 |
|------|------|
| personal_access_tokens | Sanctum 个人访问令牌 |
| notifications | 站内通知（多态） |
| socials | 第三方账号绑定 |
| password_reset_tokens | 密码重置令牌 |

---

> 文档来源：PRD 第 5.1 节
