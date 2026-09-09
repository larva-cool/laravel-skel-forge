# 项目结构

```
laravel-skel-forge/
├── app/
│   ├── Ai/                  # AI 能力
│   │   ├── Agents/          # AI Agent（Assistant）
│   │   └── Tools/           # AI 工具（Audit/Settings/Stats/System/Users/Web 等）
│   ├── Console/
│   │   └── Commands/        # Artisan 命令
│   ├── Enums/               # 枚举类（含 Traits/HasLabel）
│   │   ├── AppPlatform.php          # 应用平台枚举（android/ios/harmony）
│   │   ├── DictLabelType.php        # 字典标签样式枚举
│   │   ├── DictStatus.php           # 字典状态枚举
│   │   └── ReleaseStatus.php        # 版本发布状态枚举（draft/published/withdrawn/obsolete）
│   ├── Events/              # 事件类（Admin/、User/、System/ 等子目录）
│   ├── Exceptions/          # 自定义异常
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/       # 后台控制器（Auth、Admin、User、Role、Menu、Setting、Area、Attachment、Uploader、PhoneCode、MailCode、Notification、Chat、Dashboard、Monitor、Debug、Queue、AuditLog、ScheduleLog、AppRelease、AppVersion、Dict、DictItem、Main）
│   │   │   └── Api/V1/      # API v1 控制器（Auth、Register、Common、User、App）
│   │   ├── Middleware/      # 中间件（RecordAuditLog）
│   │   ├── Requests/        # 表单请求验证（Admin/、Api/V1/）
│   │   └── Resources/       # API 资源（Admin/、Api/V1/）
│   ├── Jobs/                # 队列任务（User/DeleteAccessTokenJob、StatUserJob）
│   ├── Listeners/           # 事件监听器（Admin/、System/、User/）
│   ├── Mail/                # 邮件类（MailVerifyCode）
│   ├── Models/
│   │   ├── Admin/           # 管理员模型（Admin、AdminMenu）
│   │   ├── App/             # 应用版本模型（AppRelease、AppVersion）
│   │   ├── Coin/            # 金币模型（CoinTrade）
│   │   ├── Point/           # 积分模型（PointRecord、PointTrade）
│   │   ├── Wallet/          # 钱包模型（WalletTrade、RechargeOrder、WithdrawOrder，均为多态 owner）
│   │   ├── System/          # 系统模型（Area、Attachment、AuditLog、Dict、DictItem、Setting、Role、Permission、Notification、MailCode、PhoneCode、ScheduleLog 等）
│   │   ├── User/            # 用户扩展模型（UserExtra、UserProfile、UserStat）
│   │   ├── Traits/          # 模型 Trait（DateTimeFormatter、HasSettingsProperty、HasUser、HasWalletBalance）
│   │   ├── Model.php        # 模型基类
│   │   └── User.php         # 前台用户模型
│   ├── Observers/           # 模型观察者（UserObserver）
│   ├── Policies/            # 授权策略（UserPolicy）
│   ├── Providers/           # 服务提供者（App、DynamicConfig、Horizon、Telescope）
│   ├── Rules/               # 自定义验证规则（PhoneRule、MailCaptchaRule 等）
│   ├── Services/            # 业务服务（AttachmentService、SmsCaptchaService、WechatService 等）
│   ├── Sms/                 # 短信消息类（VerifyCodeMessage）
│   └── Support/             # 辅助类（CoinHelper、PointHelper、UserHelper、WalletHelper）
├── bootstrap/
│   └── helpers.php          # 全局辅助函数
├── config/                  # 配置文件
├── database/
│   ├── factories/           # 模型工厂
│   ├── migrations/          # 数据库迁移
│   └── seeders/             # 数据填充
├── lang/                    # 语言文件（zh_CN、en）
├── public/
├── resources/
├── routes/                  # 路由文件
│   ├── web.php              # Web 路由
│   ├── admin.php            # 后台路由（前缀 /admin，通过 bootstrap/app.php 注册）
│   ├── api_v1.php           # API v1 路由
│   ├── channels.php         # 广播频道
│   └── console.php          # 定时任务
├── stubs/                   # 自定义 Artisan stub（agent、tool）
├── storage/
└── tests/                   # 测试文件
```

---

> 文档来源：PRD 第 4.2 节
