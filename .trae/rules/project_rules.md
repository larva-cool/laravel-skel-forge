# Trae AI 项目规则文件

## 项目概述
本项目基于 Laravel 13.x 构建，采用 DDD 架构并遵循 RESTful API 设计规范。项目结构清晰，模块化程度高，符合 Laravel 最佳实践。

## 技术栈
- **框架**：Laravel 13.x
- **PHP 版本**：8.4+
- **前端**：Tailwind CSS v4 + Vite 8.x + Laravel Echo v2.x
- **数据库**：MySQL / MongoDB
- **缓存**：Redis（predis/predis）
- **队列**：Laravel Horizon
- **应用服务器**：Laravel Octane
- **API 认证**：Laravel Sanctum
- **全文搜索**：Laravel Scout
- **实时通信**：Laravel Reverb
- **监控工具**：Laravel Pulse、Laravel Telescope、Laravel Pail
- **测试**：PHPUnit 12.x
- **代码风格**：Laravel Pint

## 编码时需遵循的重要规则
- 始终完成需求文档中的所有任务
- 修改 PHP 文件后必须运行 `vendor/bin/pint --dirty --format agent` 格式化代码
- 优先使用 Laravel Boost MCP 工具（`database-query`、`database-schema`、`search-docs`）而非手动操作
- 代码变更前使用 `search-docs` 查阅版本相关文档
- 未经用户明确请求，不创建文档文件（`*.md`、README 等）

## 编码标准和规范

### 1. PHP 编码标准
- 遵循 PSR-12 编码标准
- 为所有方法使用类型提示和返回类型
- 使用 PHPDoc 维护适当的方法和类文档
- 使用 PHP 8 构造器属性提升（Constructor Property Promotion）
- 使用显式返回类型声明和参数类型提示
- 建议方法最大长度为 20 行
- 建议类最大长度为 200 行
- 控制结构始终使用花括号，即使单行主体
- 优先使用 PHPDoc 块而非内联注释，仅在逻辑复杂时添加内联注释

### 2. Laravel 最佳实践
- 使用 `php artisan make:` 命令创建新文件，传递 `--no-interaction` 参数
- 使用 Laravel 内置的安全功能（CSRF、XSS 保护）
- 使用 Laravel 内置的表单请求进行验证
- 使用 Eloquent ORM 或查询构建器进行数据库操作
- 利用 Laravel 内置的缓存机制
- 遵循 RESTful 规范设计 API 端点
- API 默认使用 Eloquent API Resources 和 API 版本控制
- 生成页面链接时使用命名路由和 `route()` 函数

### 3. 数据库
- 对所有数据库更改使用迁移
- 编写有意义的迁移名称
- 使用种子器生成测试数据
- 对频繁查询的列创建索引
- 使用 `database-schema` 工具检查表结构后再编写迁移或模型

### 4. 安全指南
- 将敏感数据存储在 `.env` 文件中
- 使用 Laravel 的认证系统（Sanctum）
- 实现适当的输入验证
- 使用预编译语句进行查询
- 启用 CORS 保护
- 对 API 实现速率限制
- 使用 Laravel 的 CSP（内容安全策略）
- 定期进行安全更新和依赖检查

### 5. 测试
- 控制器、方法、模型变更后要同步编写测试
- 为所有新功能编写单元测试
- 保持至少 70% 的代码覆盖率
- 测试所有 API 接口
- 使用工厂生成测试数据
- 为关键路径编写功能测试
- 对模型的测试尽量使用 `RefreshDatabase` 特性，确保每次测试运行时数据库状态都是干净的
- 所有测试方法命名均使用 `snake_case` 格式
- 所有测试统一采用 PHPUnit 12 引入的新属性语法进行配置与标注：
  - 始终使用 `Tests\TestCase` 作为测试基类
  - `#[Test]` 用于标记测试方法（引入 `PHPUnit\Framework\Attributes\Test`）
  - `#[CoversClass(ClassName::class)]` 用于指定测试的类（引入 `PHPUnit\Framework\Attributes\CoversClass`）
  - `#[DataProvider('dataProviderMethod')]` 用于指定数据提供方法（引入 `PHPUnit\Framework\Attributes\DataProvider`）
  - `#[Depends('testMethod')]` 用于指定依赖的测试方法（引入 `PHPUnit\Framework\Attributes\Depends`）
  - `#[Group('groupName')]` 用于将测试分组（引入 `PHPUnit\Framework\Attributes\Group`）
  - `#[TestDox('Test Description')]` 用于为测试方法添加描述（引入 `PHPUnit\Framework\Attributes\TestDox`），所有测试方法都应包含此属性
- 测试应针对 `.env.testing` 中定义的现有数据库进行。如果该文件不存在，则测试失败
- 每次修改测试后立即运行该单个测试验证
- 运行测试的命令：
  - 全部测试：`php artisan test --compact`
  - 单个文件：`php artisan test --compact tests/Feature/ExampleTest.php`
  - 按名称过滤：`php artisan test --compact --filter=testName`

### 6. Git 工作流程
- 使用功能分支进行开发
- 编写有意义的提交消息
- 保持提交原子性和针对性
- 拉取请求必须通过 CI/CD 检查
- 定期与主分支进行变基操作

### 7. 性能指南
- 使用预加载以防止 N+1 查询
- 在适当的地方实现缓存
- 使用队列处理长时间运行的任务
- 优化数据库查询
- 对大型数据集使用分页加载
- 避免在视图中执行复杂的逻辑

### 8. 文档
- 记录所有 API 端点
- 记录复杂的业务逻辑
- 仅在用户明确请求时创建文档文件

### 9. 错误处理
- 适当地使用 try-catch 块
- 正确记录错误
- 返回适当的 HTTP 状态码
- 实现适当的异常处理
- 需要时使用自定义异常类

## 项目结构
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
  - `api_v2.php` - API v2 路由
  - `web.php` - Web 路由
  - `channels.php` - 广播频道
  - `console.php` - 控制台命令
- `tests/` - 测试文件（TestCase 基类、Feature、Unit）
- `storage/` - 日志和上传文件
- `bootstrap/helpers.php` - 全局辅助函数

## 开发设置
1. 将 `.env.example` 复制为 `.env`
2. 一键安装（推荐）：`composer run setup`
3. 或手动执行：
   - 安装依赖：`composer install`
   - 生成密钥：`php artisan key:generate`
   - 运行迁移：`php artisan migrate`
   - 创建存储链接：`php artisan storage:link`
   - 安装前端依赖：`npm install`
   - 编译资产：`npm run build`
4. 本地开发服务器（通过 Laravel Herd 提供服务，无需手动启动）
5. 启动开发环境：`composer run dev`（同时启动 server、queue、pail、vite）
6. 运行测试：`composer run test`
7. 检查代码风格：`composer run check-style`
8. 修复代码风格：`composer run fix-style`

## 部署指南
- 部署前运行所有测试
- 检查安全漏洞
- 迁移数据库前备份数据库
- 使用适当的部署环境变量
- 遵循零停机部署实践
- 可使用 Laravel Cloud 部署

## 监控和维护
- 使用 Laravel Horizon 进行队列监控
- 使用 Laravel Pulse 进行应用性能监控
- 使用 Laravel Telescope 进行调试辅助
- 使用 Laravel Pail 进行实时日志查看
- 定期审查和清理日志
- 进行数据库优化和维护
- 定期更新依赖
- 进行性能监控和优化

请记住遵循这些指南，以保持项目代码的质量和一致性。如有任何疑问或需要澄清，请咨询团队负责人或资深开发人员。
