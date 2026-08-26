# Trae AI 项目规则文件

## 项目概述
本项目基于 Laravel 13.x 构建，采用 DDD 架构并遵循 RESTful API 设计规范。项目结构清晰，模块化程度高。

## 项目专属重要规则
以下为本项目特有的规则（通用 Laravel 规范请参考根目录 AGENTS.md）：

### 1. Apifox 相关规范
- 当用户提到"发布到apifox"时，自动执行完整的发布流程
- Apifox 接口模块归属与模型隔离规范
- Apifox 分页接口设计规范

### 2. 自动化流程
创建新功能或接口后的自动化流程：
  1. 将新接口同步发布到 Apifox
  2. 更新 README.md 中相关功能说明
  3. 更新 PRD 文档中对应模块的需求描述与接口定义

### 3. 测试（项目增强规范）
- 所有测试统一采用 PHPUnit 12 引入的新属性语法进行配置与标注：
  - 始终使用 `Tests\TestCase` 作为测试基类
  - `#[Test]` 用于标记测试方法（引入 `PHPUnit\Framework\Attributes\Test`）
  - `#[CoversClass(ClassName::class)]` 用于指定测试的类（引入 `PHPUnit\Framework\Attributes\CoversClass`）
  - `#[DataProvider('dataProviderMethod')]` 用于指定数据提供方法（引入 `PHPUnit\Framework\Attributes\DataProvider`）
  - `#[Depends('testMethod')]` 用于指定依赖的测试方法（引入 `PHPUnit\Framework\Attributes\Depends`）
  - `#[Group('groupName')]` 用于将测试分组（引入 `PHPUnit\Framework\Attributes\Group`）
  - `#[TestDox('Test Description')]` 用于为测试方法添加描述（引入 `PHPUnit\Framework\Attributes\TestDox`），所有测试方法都应包含此属性
- 测试应针对 `.env.testing` 中定义的现有数据库进行。如果该文件不存在，则测试失败
- 所有测试方法命名均使用 `snake_case` 格式
- 保持至少 70% 的代码覆盖率

### 4. 项目结构
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

### 5. 开发设置
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

### 6. Apifox 详细规范（完整版）
#### Apifox 接口模块归属与模型隔离规范：
  - 接口归属必须严格按路由前缀划分：
    - `/admin` 前缀的接口 → 发布到 Apifox 的 `Admin` 模块（对应管理后台相关目录，如"管理员管理"、"用户管理"等）
    - `/api` 前缀的接口 → 发布到 Apifox 的 `Api` 模块（对应 API v1 相关目录，如"用户接口"、"认证授权"、"用户注册"等）
    - 其他非 `/admin` 且非 `/api` 前缀的接口（如 `/wechat`、`/horizon` 等）按业务语义归入对应业务模块或者`Web` 模块
  - **禁止跨模块引用数据模型与响应**：
    - Admin 模块下的接口只能引用 Admin 模块下定义的 Schema（数据模型、响应组件、分页模型中的 `data` 字段等）
    - Api 模块下的接口只能引用 Api 模块下定义的 Schema
    - 同一 Schema 不得同时被 Admin 和 Api 两个模块下的接口引用
    - 业务模型（如 User、Admin 等）的展示形态在两个模块下必须各自定义独立的 Resource/Response Schema，禁止 `$ref` 跨模块共享
  - 如需在两个模块下展示同一业务实体，应分别创建 `<实体名>AdminResource` 与 `<实体名>ApiResource`（或对应 Schema），保持模块边界清晰
  - **重要：Admin 模块和 Api 模块的所有请求（Request）定义都不要引用任何数据模型，直接使用内联的 JSON Schema 定义，避免冗余模型引用**
  - 清理所有冗余的请求模型（Request Schema），确保 Apifox 项目中不存在未被任何接口使用的冗余请求模型定义

#### Apifox 分页接口设计规范：
  - 向 Apifox 发布接口时要创建合适的 `tag`（如 `分页接口`）
  - Apifox 项目中已预定义两个全局查询参数：`page`（分页页码，默认值 1）和 `per_page`（每页条数，默认值 15）
  - 这两个参数默认处于**禁用**状态，仅在需要分页的接口中启用
  - 设计分页接口时，必须先启用这两个全局参数，禁止重复创建新的分页参数
  - 分页接口使用 Laravel 标准分页（`paginate()` + `Resource::collection()`），响应体为 Laravel 默认分页结构，包含以下字段：`data`（当前页数据数组）、`current_page`（当前页码）、`last_page`（最后一页页码）、`per_page`（每页条数）、`total`（总条数）、`from`（当前页起始条目序号）、`to`（当前页结束条目序号）、`first_page_url`、`last_page_url`、`next_page_url`、`prev_page_url`、`path`、`links`（分页导航链接数组）
  - Apifox 中分页响应禁止单独创建分页模型，必须使用 `x-apifox-refs` 引用 `EloquentPaginate`（集合分页）并覆写 `data` 字段为具体数据模型
  - `EloquentPaginate` 在 Admin 与 Api 两个模块下各有一份独立定义，必须按接口所属模块引用对应 ID，禁止跨模块引用：
    - Admin 模块（`/admin` 前缀接口）→ `#/definitions/304922372`
    - Api 模块（`/api` 前缀接口）→ `#/definitions/307396242`
  - 格式如下（以 Api 模块为例）：
    ```json
    {
      "type": "object",
      "x-apifox-refs": {
        "<随机ID>": {
          "$ref": "#/definitions/307396242",
          "x-apifox-overrides": {}
        }
      },
      "properties": {
        "data": {
          "type": "array",
          "items": {"$ref": "#/definitions/具体数据模型ID"}
        }
      },
      "x-apifox-orders": ["<随机ID>", "data"]
    }
    ```

#### 发布到apifox 完整流程：
  1. 调用 `apifox-cli` skill 获取 Apifox CLI 使用指引
  2. 根据具体场景调用对应的 apifox skill（如 `apifox-import-export`、`apifox-branch`、`apifox-workflow-api-lifecycle` 等）
  3. 使用 Apifox CLI 执行实际的发布操作（如导入/导出 OpenAPI 文档、同步接口到 Apifox 项目等）
  4. 执行完成后，使用 `apifox-cli-checkup` skill 验证操作结果是否生效

### 7. 其他项目专属约定
- 始终完成需求文档中的所有任务

请记住遵循这些项目专属指南，通用 Laravel 开发规范请参考根目录 AGENTS.md。如有任何疑问或需要澄清，请咨询团队负责人或资深开发人员。
