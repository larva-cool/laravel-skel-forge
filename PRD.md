# Laravel Skel Pro 产品需求文档 (PRD)

## 1. 项目概述

### 1.1 项目背景
Laravel Skel Pro 是一个基于 Laravel 13.x 的企业级 API 模板项目，采用领域驱动设计（DDD）架构，内置完整的用户认证、权限管理和后台管理系统，旨在为开发者提供一个快速构建高质量后端 API 服务的基础框架。

### 1.2 项目目标
- 提供开箱即用的企业级 API 开发模板
- 集成成熟的认证、授权和权限管理方案
- 支持多用户系统（管理员+前台用户）
- 内置常用的系统管理功能
- 提供完整的开发、测试和部署方案

### 1.3 目标用户
- **后端开发人员**：快速搭建 API 服务
- **技术团队**：统一技术栈和开发规范
- **初创企业**：快速构建 MVP 产品
- **教育机构**：学习 Laravel 最佳实践

## 2. 产品定位

### 2.1 产品类型
企业级 API 开发框架模板

### 2.2 核心价值
- **快速启动**：一键安装和配置，快速开始开发
- **安全可靠**：内置完整的认证和权限体系
- **架构清晰**：采用 DDD 架构，代码组织规范
- **功能完整**：包含常用的系统管理功能
- **易于扩展**：模块化设计，易于添加新功能

### 2.3 市场定位
- **技术层次**：中高级后端开发框架
- **应用场景**：企业管理系统、电商后台、SaaS 服务
- **目标规模**：中小型到大型企业级应用

## 3. 功能需求

### 3.1 用户系统

#### 3.1.1 管理员用户系统
| 功能 | 说明 |
|------|------|
| 管理员注册/创建 | 支持创建新管理员账号 |
| 管理员登录 | 支持用户名/邮箱/手机号三种登录方式 |
| 管理员信息管理 | 查看和修改个人资料 |
| 密码管理 | 修改密码、重置密码 |
| 状态管理 | 启用/禁用管理员账号 |
| 角色分配 | 为管理员分配角色 |
| 登录历史 | 记录管理员登录历史 |
| 权限控制 | 基于 RBAC 的权限管理 |

#### 3.1.2 前台用户系统
| 功能 | 说明 |
|------|------|
| 用户注册 | 支持多种注册方式 |
| 用户认证 | 基于 Sanctum 的 Token 认证 |
| 用户信息管理 | 查看和修改个人资料 |
| 密码重置 | 通过邮箱/手机验证码重置密码 |
| 用户状态管理 | 启用、禁用、冻结用户 |
| VIP会员系统 | 支持VIP会员功能 |
| 积分系统 | 用户积分和金币管理 |
| 登录历史 | 记录用户登录历史 |

### 3.2 认证与授权

#### 3.2.1 认证系统
| 功能 | 说明 |
|------|------|
| Sanctum Token认证 | 为API请求提供Token认证 |
| Session认证 | 为Web应用提供Session认证 |
| 多账号登录 | 支持用户名/邮箱/手机号三种登录方式 |
| 验证码服务 | 短信验证码和邮件验证码 |
| 登录防护 | 登录频率限制、IP限制 |

#### 3.2.2 权限管理
| 功能 | 说明 |
|------|------|
| 角色管理 | 创建、编辑、删除角色 |
| 权限管理 | 管理系统权限列表 |
| 角色权限分配 | 为角色分配权限 |
| 管理员角色分配 | 为管理员分配角色 |
| 权限检查 | 路由级和方法级权限控制 |
| 菜单权限 | 基于权限的菜单显示控制 |

### 3.3 后台管理系统

#### 3.3.1 系统设置
| 功能 | 说明 |
|------|------|
| 配置管理 | 管理系统配置项（支持多种数据类型） |
| 菜单管理 | 管理后台菜单结构 |
| 地区管理 | 管理省市区地区数据 |

#### 3.3.2 运营管理
| 功能 | 说明 |
|------|------|
| 短信验证码记录 | 查看短信验证码发送记录 |
| 邮件验证码记录 | 查看邮件验证码发送记录 |
| 上传服务 | 文件上传Token生成 |

#### 3.3.3 附件管理（上传文件管理器）
| 功能 | 说明 |
|------|------|
| 附件列表 | 分页查询，支持类型/磁盘/上传者/关键词/时间范围筛选 |
| 附件详情 | 查看单个附件的完整元数据 |
| 附件删除 | 单个删除、批量删除，同步删除 Storage 物理文件 |
| 附件预览 | 图片/视频返回可访问 URL（公有盘直链，私有盘临时签名 URL） |
| 附件下载 | 服务端流式下载，保留原始文件名 |
| 临时 URL | 为私有磁盘生成有时效的签名访问地址 |
| 附件重命名 | 修改显示名称，不改动物理路径 |
| 附件移动 | 在同一磁盘内移动物理路径（Storage 层 move） |
| 直传登记 | 客户端直传云存储后回调登记元数据 |

### 3.4 核心服务

#### 3.4.1 验证码服务
| 功能 | 说明 |
|------|------|
| 短信验证码发送 | 支持多种场景（登录、注册、重置密码等） |
| 短信验证码验证 | 验证验证码的正确性 |
| 邮件验证码发送 | 通过邮件发送验证码 |
| 邮件验证码验证 | 验证邮件验证码 |
| 验证码记录管理 | 记录和查询验证码发送历史 |

#### 3.4.2 文件上传服务
| 功能 | 说明 |
|------|------|
| 上传Token生成 | 生成临时上传Token |
| 支持云存储 | 兼容S3协议的云存储 |
| 文件安全 | 上传文件类型和大小限制 |

#### 3.4.3 系统监控
| 功能 | 说明 |
|------|------|
| 应用性能监控 | Laravel Pulse 提供性能监控 |
| 调试辅助 | Laravel Telescope 提供调试功能 |
| 队列管理 | Laravel Horizon 提供队列管理 |
| 实时日志 | Laravel Pail 提供实时日志查看 |

## 4. 技术架构

### 4.1 技术栈

| 技术 | 版本 | 用途 |
|------|------|------|
| PHP | ^8.3 | 编程语言 |
| Laravel | ^13.8 | Web框架 |
| Laravel Sanctum | ^4.0 | API认证 |
| Spatie Laravel Permission | ^8.3 | 权限管理 |
| Laravel Horizon | ^5.48 | 队列管理 |
| Laravel Pulse | ^1.7 | 性能监控 |
| Laravel Telescope | ^5.21 | 调试工具 |
| Laravel Reverb | ^1.0 | WebSocket服务 |
| Laravel Octane | ^2.18 | 高性能应用服务 |
| Laravel Scout | ^11.4 | 全文搜索 |
| Redis | - | 缓存和队列驱动 |
| Predis | ^3.5 | Redis客户端 |
| Intervention Image | ^4.0 | 图像处理 |
| EasySMS | ^3.3 | 短信发送 |
| PHPUnit | ^12.5 | 单元测试 |
| Laravel Pint | ^1.27 | 代码格式化 |

### 4.2 项目结构

```
laravel-skel-pro/
├── app/
│   ├── Enums/              # 枚举类
│   ├── Events/             # 事件类
│   ├── Http/
│   │   ├── Controllers/    # 控制器
│   │   ├── Requests/       # 表单请求验证
│   │   └── Resources/      # API资源
│   ├── Models/             # 数据模型
│   │   ├── Admin/          # 管理员模型
│   │   ├── System/         # 系统模型
│   │   ├── User/           # 用户模型
│   │   └── Traits/         # 模型Trait
│   ├── Providers/          # 服务提供者
│   ├── Rules/              # 验证规则
│   ├── Services/           # 业务服务
│   ├── Sms/                # 短信相关
│   └── Support/            # 支持类
├── bootstrap/
├── config/                 # 配置文件
├── database/
│   ├── factories/          # 模型工厂
│   ├── migrations/         # 数据库迁移
│   └── seeders/            # 数据填充
├── lang/                   # 语言文件
├── public/
├── resources/
├── routes/                 # 路由文件
│   ├── admin.php           # 后台路由
│   ├── api_v1.php          # API v1路由
│   └── api_v2.php          # API v2路由
├── storage/
└── tests/                  # 测试文件
```

### 4.3 架构设计

#### 4.3.1 DDD 架构
项目采用领域驱动设计思想，将代码按领域组织：

- **Models Layer**：数据模型和业务逻辑
- **Services Layer**：业务服务封装
- **Controllers Layer**：HTTP 请求处理
- **Resources Layer**：API 响应格式化
- **Requests Layer**：请求验证

#### 4.3.2 分层架构
```
┌─────────────────────────────────────┐
│        API Layer (Routes)            │
├─────────────────────────────────────┤
│     Controller Layer                 │
├─────────────────────────────────────┤
│      Service Layer                   │
├─────────────────────────────────────┤
│     Model Layer                      │
├─────────────────────────────────────┤
│     Database Layer                   │
└─────────────────────────────────────┘
```

## 5. 数据模型

### 5.1 核心数据表

#### 5.1.1 管理员表 (admin_users)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| username | VARCHAR | 用户名 |
| email | VARCHAR | 邮箱地址 |
| phone | VARCHAR | 手机号 |
| name | VARCHAR | 昵称 |
| status | TINYINT | 状态（0禁用，1启用） |
| password | VARCHAR | 密码哈希 |
| login_count | INT | 登录次数 |
| last_login_ip | VARCHAR | 最后登录IP |
| last_login_at | TIMESTAMP | 最后登录时间 |
| last_active_at | TIMESTAMP | 最后活动时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

#### 5.1.2 用户表 (users)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| username | VARCHAR | 用户名 |
| email | VARCHAR | 邮箱地址 |
| phone | VARCHAR | 手机号 |
| name | VARCHAR | 昵称 |
| avatar | VARCHAR | 头像 |
| status | TINYINT | 状态 |
| available_points | INT | 可用积分 |
| available_coins | INT | 可用金币 |
| vip_expires_at | TIMESTAMP | VIP过期时间 |
| password | VARCHAR | 密码哈希 |
| login_count | INT | 登录次数 |
| last_login_ip | VARCHAR | 最后登录IP |
| last_login_at | TIMESTAMP | 最后登录时间 |
| last_active_at | TIMESTAMP | 最后活动时间 |
| email_verified_at | TIMESTAMP | 邮箱验证时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

#### 5.1.3 角色和权限表
- roles：角色表
- permissions：权限表
- model_has_roles：模型角色关联表
- model_has_permissions：模型权限关联表
- role_has_permissions：角色权限关联表

#### 5.1.4 菜单表 (admin_menus)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| parent_id | BIGINT | 父级菜单ID |
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
| is_iframe | BOOLEAN | 是否iframe嵌入 |
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

#### 5.1.5 配置表 (settings)
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

#### 5.1.6 地区表 (areas)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| parent_id | BIGINT | 父级地区ID |
| name | VARCHAR | 地区名称 |
| area_code | INT | 地区代码 |
| city_code | VARCHAR | 城市代码 |
| lat | DECIMAL | 纬度 |
| lng | DECIMAL | 经度 |
| level | TINYINT | 层级 |
| sort | INT | 排序 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 5.1.7 短信验证码表 (phone_codes)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| phone | VARCHAR | 手机号 |
| scene | VARCHAR | 场景（login/register/reset_password等） |
| code | VARCHAR | 验证码 |
| state | TINYINT | 状态（0未使用，1已使用） |
| ip | VARCHAR | 发送IP |
| send_at | TIMESTAMP | 发送时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 5.1.8 邮件验证码表 (mail_codes)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| email | VARCHAR | 邮箱地址 |
| scene | VARCHAR | 场景 |
| code | VARCHAR | 验证码 |
| state | TINYINT | 状态（0未使用，1已使用） |
| ip | VARCHAR | 发送IP |
| send_at | TIMESTAMP | 发送时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 5.1.9 登录历史表 (login_histories)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| user_type | VARCHAR | 用户类型（App\\\\Models\\\\User/App\\\\Models\\\\Admin\\\\Admin） |
| user_id | BIGINT | 用户ID |
| ip | VARCHAR | 登录IP |
| port | INT | 端口 |
| platform | VARCHAR | 平台 |
| device | VARCHAR | 设备 |
| browser | VARCHAR | 浏览器 |
| user_agent | TEXT | User-Agent |
| address | VARCHAR | 地址 |
| login_at | TIMESTAMP | 登录时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 5.1.10 附件表 (attachments)
| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | BIGINT | 主键 |
| uploader_type | VARCHAR | 上传者类型（App\\Models\\User/App\\Models\\Admin\\Admin） |
| uploader_id | BIGINT | 上传者ID |
| disk | VARCHAR | 存储磁盘（local/public/s3等） |
| path | VARCHAR | 物理存储路径 |
| name | VARCHAR | 显示名称 |
| original_name | VARCHAR | 原始文件名 |
| extension | VARCHAR | 文件扩展名 |
| mime_type | VARCHAR | MIME类型 |
| type | VARCHAR | 附件类型（image/video/audio/document/other） |
| size | BIGINT | 文件大小（字节） |
| hash | VARCHAR | 文件哈希（用于去重校验） |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |
| deleted_at | TIMESTAMP | 删除时间（软删除） |

## 6. API 接口说明

### 6.1 统一响应格式

所有 API 接口统一采用以下响应格式：

```json
{
  "code": 200,
  "message": "success",
  "data": {}
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| code | INT | 业务状态码，200表示成功 |
| message | STRING | 响应消息 |
| data | ANY | 响应数据 |

### 6.2 业务状态码说明

| 状态码 | 说明 |
|--------|------|
| 200 | 成功 |
| 400 | 请求参数错误 |
| 401 | 未登录或登录过期 |
| 403 | 无权限访问 |
| 404 | 资源不存在 |
| 422 | 数据验证失败 |

### 6.3 主要接口列表

#### 6.3.1 认证接口（/admin/auth）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | /admin/auth/login | 管理员登录 | 否 |
| POST | /admin/auth/logout | 退出登录 | 是 |
| GET | /admin/auth/info | 获取当前管理员信息 | 是 |

#### 6.3.2 管理员管理（/admin/admins）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | /admin/admins | 获取管理员列表 | 是 |
| POST | /admin/admins | 创建管理员 | 是 |
| GET | /admin/admins/{id} | 获取管理员详情 | 是 |
| PUT | /admin/admins/{id} | 更新管理员 | 是 |
| DELETE | /admin/admins/{id} | 删除管理员 | 是 |
| GET | /admin/admins/profile | 获取当前管理员资料 | 是 |
| PUT | /admin/admins/profile | 更新当前管理员资料 | 是 |
| PUT | /admin/admins/change-password | 修改当前管理员密码 | 是 |
| GET | /admin/admins/{id}/roles | 获取管理员角色 | 是 |
| PUT | /admin/admins/{id}/roles | 分配管理员角色 | 是 |
| PUT | /admin/admins/{id}/toggle-status | 切换管理员状态 | 是 |
| PUT | /admin/admins/{id}/reset-password | 重置管理员密码 | 是 |
| GET | /admin/admins/{id}/login-histories | 获取管理员登录历史 | 是 |

#### 6.3.3 角色管理（/admin/roles）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | /admin/roles | 获取角色列表 | 是 |
| POST | /admin/roles | 创建角色 | 是 |
| GET | /admin/roles/{id} | 获取角色详情 | 是 |
| PUT | /admin/roles/{id} | 更新角色 | 是 |
| DELETE | /admin/roles/{id} | 删除角色 | 是 |
| GET | /admin/roles/permissions | 获取全部权限列表 | 是 |
| GET | /admin/roles/{id}/permissions | 获取角色权限 | 是 |
| PUT | /admin/roles/{id}/permissions | 分配角色权限 | 是 |

#### 6.3.4 菜单管理（/admin/menus）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | /admin/menus | 获取菜单树 | 是 |
| POST | /admin/menus | 创建菜单 | 是 |
| GET | /admin/menus/{id} | 获取菜单详情 | 是 |
| PUT | /admin/menus/{id} | 更新菜单 | 是 |
| DELETE | /admin/menus/{id} | 删除菜单 | 是 |
| GET | /admin/routes | 获取前端路由配置 | 是 |

#### 6.3.5 配置管理（/admin/settings）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | /admin/settings | 获取配置列表 | 是 |
| POST | /admin/settings | 创建配置 | 是 |
| GET | /admin/settings/{id} | 获取配置详情 | 是 |
| PUT | /admin/settings/{id} | 更新配置 | 是 |
| DELETE | /admin/settings/{id} | 删除配置 | 是 |

#### 6.3.6 地区管理（/admin/areas）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | /admin/areas | 获取地区树 | 是 |
| POST | /admin/areas | 创建地区 | 是 |
| GET | /admin/areas/{id} | 获取地区详情 | 是 |
| PUT | /admin/areas/{id} | 更新地区 | 是 |
| DELETE | /admin/areas/{id} | 删除地区 | 是 |

#### 6.3.7 短信验证码（/admin/phone-codes）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | /admin/phone-codes | 获取短信验证码列表 | 是 |
| GET | /admin/phone-codes/{id} | 获取短信验证码详情 | 是 |

#### 6.3.8 邮件验证码（/admin/mail-codes）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | /admin/mail-codes | 获取邮件验证码列表 | 是 |
| GET | /admin/mail-codes/{id} | 获取邮件验证码详情 | 是 |

#### 6.3.9 上传管理（/admin/uploader）
| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | /admin/uploader/token | 获取上传Token | 是 |

#### 6.3.10 附件管理（/admin/attachments）
| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| GET | /admin/attachments | 获取附件列表（分页+筛选） | attachments.index |
| GET | /admin/attachments/{id} | 获取附件详情 | attachments.index |
| DELETE | /admin/attachments/{id} | 删除单个附件 | attachments.delete |
| DELETE | /admin/attachments | 批量删除附件（ids 数组） | attachments.delete |
| GET | /admin/attachments/{id}/download | 流式下载附件 | attachments.index |
| GET | /admin/attachments/{id}/temporary-url | 获取临时签名访问地址 | attachments.index |
| PUT | /admin/attachments/{id}/rename | 重命名附件显示名 | attachments.edit |
| PUT | /admin/attachments/{id}/move | 移动附件物理路径 | attachments.edit |
| POST | /admin/attachments/register | 直传完成后登记元数据 | attachments.create |

##### 列表请求参数
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| per_page | int | 否 | 每页条数，1-100，默认15 |
| type | string | 否 | 附件类型（image/video/audio/document/other） |
| disk | string | 否 | 存储磁盘名 |
| keyword | string | 否 | 按显示名/原始文件名模糊匹配 |
| extension | string | 否 | 按扩展名精确匹配 |
| uploader_id | int | 否 | 上传者ID |
| start_date | date | 否 | 创建时间起始 |
| end_date | date | 否 | 创建时间截止 |

##### 附件资源字段（AttachmentResource）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 附件ID |
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

## 7. 附件管理技术方案

### 7.1 设计原则
- **元数据入库、字节流交给 Storage**：`attachments` 表只保存 `disk + path` 等元数据，所有物理读写通过 `Storage::disk($disk)` 完成，不出现任何 `storage_path()` 硬编码，保证 local/public/s3 三种驱动行为一致。
- **复用现有上传链路**：不改动 [UploaderController](file:///Users/xutongle/Skel/laravel-skel-pro/app/Http/Controllers/Admin/UploaderController.php) 的对外契约，在 [UploadRequest](file:///Users/xutongle/Skel/laravel-skel-pro/app/Http/Requests/Admin/Uploader/UploadRequest.php) 落盘成功后追加台账写入，上传返回体在原字段基础上增加 `id`。
- **分层职责清晰**：Controller 只做 HTTP 编排，物理文件操作与台账一致性收敛到 `AttachmentService`。
- **失败可回滚**：删除、移动等涉及"数据库 + 物理文件"两段操作的动作，以数据库事务包裹，物理操作失败即回滚台账。

### 7.2 代码结构

```
app/
├── Enums/
│   └── AttachmentType.php              # 附件类型枚举（image/video/audio/document/other）
├── Models/
│   └── System/
│       └── Attachment.php              # 附件模型（软删除 + morphTo uploader）
├── Services/
│   └── AttachmentService.php           # 台账 + Storage 的一致性编排
├── Http/
│   ├── Controllers/Admin/
│   │   └── AttachmentController.php    # 后台附件管理控制器
│   ├── Requests/Admin/Attachment/
│   │   ├── AttachmentIndexRequest.php  # 列表筛选参数校验
│   │   ├── AttachmentRenameRequest.php # 重命名校验
│   │   ├── AttachmentMoveRequest.php   # 移动路径校验
│   │   ├── AttachmentDestroyRequest.php# 批量删除 ids 校验
│   │   └── AttachmentRegisterRequest.php # 直传登记校验
│   └── Resources/Admin/
│       └── AttachmentResource.php      # 附件资源
database/
├── migrations/
│   └── xxxx_create_attachments_table.php
└── factories/System/
    └── AttachmentFactory.php
tests/Feature/Admin/
└── AttachmentControllerTest.php
```

### 7.3 数据表设计要点
- 主键从 `10000000` 起（与 `mail_codes` 等表保持一致风格），带表注释与字段注释。
- 索引：`(uploader_type, uploader_id)` 复合索引、`type` 索引、`hash` 索引、`(disk, path)` 唯一索引（防止同一物理文件重复登记）。
- 使用软删除 `deleted_at`：接口默认执行"硬删除物理文件 + 软删除台账"，保留审计线索。
- `size` 使用 `unsignedBigInteger`，`hash` 使用 `char(32)`（md5）并允许为空（直传场景可能拿不到内容哈希）。

### 7.4 AttachmentService 关键方法

| 方法 | 职责 | Storage 调用 |
|------|------|-------------|
| `record(array $meta): Attachment` | 上传成功后写台账 | 无 |
| `url(Attachment $a): ?string` | 公有盘返回直链 | `Storage::disk()->url()` |
| `temporaryUrl(Attachment $a, int $minutes): string` | 私有盘签名地址；本地盘回退直链 | `Storage::disk()->temporaryUrl()` |
| `download(Attachment $a): StreamedResponse` | 流式下载，保留原始名 | `Storage::disk()->download()` |
| `move(Attachment $a, string $newPath): Attachment` | 同盘移动，事务内先改库再移文件 | `Storage::disk()->move()` |
| `delete(Attachment $a): bool` | 删物理文件后软删台账 | `Storage::disk()->delete()` |
| `deleteMany(array $ids): int` | 按磁盘分组批量删除 | `Storage::disk()->delete(array)` |
| `exists(Attachment $a): bool` | 校验物理文件是否仍存在 | `Storage::disk()->exists()` |

要点说明：
- `temporaryUrl()` 需捕获驱动不支持的异常（local 驱动在未启用 `serve` 时不支持），降级为 `url()` 或 422 提示。
- `move()` 只允许同磁盘内移动，目标路径必须落在 `uploads/` 前缀下，且先做 `exists()` 冲突检查，避免覆盖既有文件。
- `deleteMany()` 按 `disk` 分组后一次性传数组给 `Storage::disk()->delete()`，减少远端请求次数。
- 物理文件已丢失（`exists() === false`）时删除仍视为成功，只清理台账，避免脏数据无法删除。

### 7.5 上传链路改造
在 [UploadRequest::handleUpload()](file:///Users/xutongle/Skel/laravel-skel-pro/app/Http/Requests/Admin/Uploader/UploadRequest.php#L56-L70) 落盘成功后调用 `AttachmentService::record()`，`uploader` 取 `$this->user()`（morphTo 兼容 Admin 与 User 两种守卫）。返回结构在原有字段上追加 `id`，前端可凭 `id` 直接进入附件管理器操作，旧调用方不受影响。

直传场景（`uploader/token`）由于文件不经过服务端，改为前端上传成功后调用 `POST /admin/attachments/register` 补登记，服务端用 `Storage::disk()->exists()` 与 `size()` 校验真实存在后再入库，防止伪造记录。

### 7.6 权限与菜单
- 新增权限：`attachments.index`、`attachments.create`、`attachments.edit`、`attachments.delete`，在控制器构造器中以 `permission:` 中间件绑定，与 [AreaController](file:///Users/xutongle/Skel/laravel-skel-pro/app/Http/Controllers/Admin/AreaController.php#L26-L33) 保持同一模式。
- 新增菜单：`system.attachment`，挂在"系统管理"目录下，含"删除附件""重命名附件"按钮权限。
- 通过新建迁移追加权限与菜单，不修改已执行过的历史迁移。

### 7.7 配置项
沿用现有 `upload.*` 配置（`upload.storage`、`upload.allow_extension` 等），新增两项：

| 配置键 | 默认值 | 说明 |
|--------|--------|------|
| upload.temporary_url_minutes | 10 | 临时签名 URL 有效期（分钟） |
| upload.prune_orphan_days | 0 | 孤儿附件自动清理天数，0 表示不清理 |

### 7.8 提示文案
后台提示统一走语言包 `lang/{zh_CN,en}/admin.php`，新增键：

| Key | 中文 |
|-----|------|
| attachment_delete_success | 附件删除成功 |
| attachment_batch_delete_success | 批量删除成功 |
| attachment_rename_success | 附件重命名成功 |
| attachment_move_success | 附件移动成功 |
| attachment_register_success | 附件登记成功 |
| attachment_file_missing | 物理文件不存在 |
| attachment_target_exists | 目标路径已存在同名文件 |
| attachment_invalid_target_path | 目标路径不合法 |
| attachment_temporary_url_unsupported | 当前存储驱动不支持临时访问地址 |

### 7.9 测试计划
`tests/Feature/Admin/AttachmentControllerTest.php` 使用 `RefreshDatabase` + `Storage::fake()`，PHPUnit 12 属性语法（`#[Test]`、`#[CoversClass]`、`#[TestDox]`），覆盖：

- 列表：分页、按 type/disk/keyword/extension/时间范围筛选、未认证 401、无权限 403
- 详情：正常返回、`exists` 字段准确、不存在返回 404
- 删除：物理文件被删除且台账软删除；物理文件缺失时仍删除成功；批量删除返回删除条数
- 下载：响应头 `Content-Disposition` 含原始文件名
- 临时 URL：s3 fake 返回签名地址；不支持的驱动返回 422
- 重命名：仅显示名变更，`path` 不变
- 移动：物理文件在新路径存在、旧路径不存在；目标已存在返回 422；跨磁盘返回 422
- 直传登记：物理文件不存在时拒绝入库

### 7.10 实施步骤
1. 新建 `AttachmentType` 枚举（实现 `HasLabel`）
2. 新建 `attachments` 表迁移 + `Attachment` 模型 + `AttachmentFactory`
3. 实现 `AttachmentService`
4. 新建 FormRequest 与 `AttachmentResource`
5. 实现 `AttachmentController` 并在 [routes/admin.php](file:///Users/xutongle/Skel/laravel-skel-pro/routes/admin.php) 注册路由
6. 改造 `UploadRequest` 写入台账，新增 `register` 接口
7. 追加权限、菜单、配置项、语言包迁移与文案
8. 编写并运行功能测试，执行 `vendor/bin/pint --dirty --format agent`

## 8. 非功能性需求

### 8.1 性能要求
- API 响应时间：< 200ms（95% 请求）
- 支持并发用户数：1000+
- 数据库查询优化：避免 N+1 查询
- 缓存策略：热点数据使用 Redis 缓存

### 8.2 安全要求
- 密码使用 Bcrypt 加密（rounds = 12）
- API 接口使用 Sanctum Token 认证
- 敏感接口使用权限验证
- SQL 注入防护：使用 ORM 查询
- XSS 防护：输出转义
- CSRF 防护：Web 接口启用
- 请求频率限制：防止恶意请求

### 8.3 可扩展性
- 模块化设计：功能独立封装
- 微服务友好：易于拆分
- 支持水平扩展：无状态设计
- 队列系统：异步任务处理

### 8.4 可维护性
- 代码规范：遵循 PSR-12 标准
- 注释完整：关键逻辑添加注释
- 文档齐全：API 文档完整
- 测试覆盖：单元测试 + 功能测试

### 8.5 可靠性
- 错误处理：统一的异常处理机制
- 日志记录：完整的日志记录
- 数据备份：支持数据库备份
- 故障恢复：快速恢复机制

## 9. 开发流程

### 9.1 环境要求
- PHP >= 8.3
- MySQL 8.0+ / PostgreSQL / SQLite
- Redis 6.0+
- Node.js >= 20.0（前端开发）
- Composer 2.x

### 9.2 快速开始

#### 9.2.1 安装依赖
```bash
composer install
```

#### 9.2.2 环境配置
```bash
cp .env.example .env
php artisan key:generate
```

#### 9.2.3 数据库迁移
```bash
php artisan migrate
php artisan storage:link
```

#### 9.2.4 快速安装（推荐）
```bash
composer run setup
```

### 9.3 开发命令

| 命令 | 说明 |
|------|------|
| composer run dev | 启动开发环境（服务、队列、日志、Vite） |
| composer run test | 运行全部测试 |
| composer run check-style | 检查代码风格 |
| composer run fix-style | 修复代码风格 |
| php artisan tinker | 交互式命令行 |
| php artisan horizon | 启动队列服务 |
| php artisan pail | 查看实时日志 |

### 9.4 代码规范
- 遵循 PSR-12 编码标准
- 使用 PHP 8 构造器属性提升
- 所有方法添加类型声明
- 使用 PHPUnit 12 新属性语法
- 修改 PHP 文件后运行 `vendor/bin/pint` 格式化

## 10. 部署方案

### 10.1 生产环境配置

#### 10.1.1 环境变量配置
```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_CLIENT=predis

BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=s3
```

#### 10.1.2 优化配置
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
```

### 10.2 部署方式

#### 10.2.1 使用 Laravel Cloud（推荐）
Laravel Cloud 是官方提供的部署平台，提供自动化部署和托管服务。

#### 10.2.2 使用 Octane + Swoole/RoadRunner
使用 Laravel Octane 提供高性能服务。

#### 10.2.3 传统 FPM 部署
使用 Nginx + PHP-FPM 的传统部署方式。

### 10.3 服务配置

| 服务 | 说明 | 建议配置 |
|------|------|----------|
| Horizon | 队列管理 | Supervisor 守护 |
| Reverb | WebSocket服务 | 独立进程 |
| Pulse | 性能监控 | 访问控制保护 |
| Telescope | 调试工具 | 生产环境禁用 |

### 10.4 备份策略
- 数据库每日备份
- 重要配置备份
- 上传文件备份
- 备份保留30天

## 11. 监控与维护

### 11.1 监控工具
| 工具 | 用途 | 访问路径 |
|------|------|----------|
| Laravel Pulse | 应用性能监控 | /pulse |
| Laravel Horizon | 队列监控 | /horizon |
| Laravel Telescope | 调试辅助 | /telescope |
| Laravel Pail | 实时日志 | 命令行 |

### 11.2 日常维护
- 定期清理过期验证码
- 清理旧日志文件
- 数据库优化
- 依赖安全更新检查

## 12. 未来规划

### 12.1 短期规划（1-3个月）
- [ ] 完善单元测试覆盖
- [ ] 添加更多开箱即用的功能
- [ ] 完善文档和示例
- [ ] 性能优化和基准测试

### 12.2 中期规划（3-6个月）
- [ ] 添加更多认证方式（OAuth、Socialite）
- [ ] 完善多语言支持
- [ ] 添加 GraphQL 支持
- [ ] 集成审计日志功能

### 12.3 长期规划（6-12个月）
- [ ] 微服务架构支持
- [ ] 多云部署支持
- [ ] 更多开箱即用的业务模块
- [ ] 可视化代码生成工具

## 13. 附录

### 13.1 相关文档
- Laravel 官方文档：https://laravel.com/docs
- Laravel Skel Pro README.md
- API 文档：docs/admin-api-response.md

### 13.2 联系方式
- 项目地址：https://github.com/larva-cool/laravel-skel-pro
- 作者：Tongle Xu <xutongle@msn.com>

---

**文档版本**：v1.1  
**最后更新**：2026-08-17  
**维护人员**：Tongle Xu
