# 项目结构

## 目录说明

- `app/Enums/` - 枚举类（含 `Traits/HasLabel`）
- `app/Http/Controllers/` - 控制器
- `app/Models/` - Eloquent 模型基类
  - `System/` - 系统模型（Area、MailCode、PhoneCode、Setting）
  - `Traits/` - 模型 trait（DateTimeFormatter）
- `app/Models/Sanctum/` - Sanctum 个人访问令牌
- `app/Providers/` - 服务提供者（App、Horizon、Telescope）
- `config/` - 配置文件
- `database/` - 迁移、工厂和种子器
- `resources/` - 视图和前端资产
- `routes/` - 路由定义
  - `api_v1.php` - API v1 路由
  - `web.php` - Web 路由
  - `channels.php` - 广播频道
  - `console.php` - 控制台命令
- `tests/` - 测试文件（TestCase 基类、Feature、Unit）
- `storage/` - 日志和上传文件
- `bootstrap/helpers.php` - 全局辅助函数
