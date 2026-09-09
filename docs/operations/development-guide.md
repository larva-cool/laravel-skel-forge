# 开发流程指南

## 1. 环境要求

- PHP >= 8.4
- PHP 扩展：`pdo`、`mbstring`、`openssl`、`redis`、`bcmath`、`ctype`、`json`、`fileinfo`、`tokenizer`、`xml`
- Composer >= 2.x
- MySQL 8.0+ / PostgreSQL / SQLite
- Redis 6.0+
- Node.js >= 20.x（前端资源构建）
- 推荐使用 [Laravel Herd](https://herd.laravel.com/) 作为本地开发环境

## 2. 快速开始

```bash
# 一键安装（推荐）
composer run setup

# 启动开发环境
composer run dev
```

手动步骤：

```bash
composer install
cp .env.example .env
php artisan key:generate
# 配置 .env 数据库连接后
php artisan migrate
php artisan storage:link
npm install
npm run build
```

本地访问地址：`http://laravel-skel-forge.test`（Herd 自动提供）。

## 3. 开发命令

| 命令 | 说明 |
|------|------|
| `composer run setup` | 一键安装（依赖、密钥、迁移、存储链接、前端构建） |
| `composer run dev` | 启动开发环境（server/queue/pail/vite） |
| `composer run test` | 运行全部测试 |
| `composer run check-style` | 检查代码风格（Pint） |
| `composer run fix-style` | 修复代码风格（Pint） |
| `php artisan route:list` | 查看路由列表 |
| `php artisan route:list --path=admin` | 查看后台路由 |
| `php artisan horizon` | 启动队列服务（生产用 Supervisor 守护） |
| `php artisan pail` | 查看实时日志 |
| `php artisan schedule:work` | 本地运行调度器 |

## 4. 代码规范

- 遵循 PSR-12 编码标准
- 使用 PHP 8.4 特性：构造器属性提升、命名参数、枚举、match 表达式
- 所有 PHP 文件强制 `declare(strict_types=1)` 和文件头 License 注释
- 所有方法添加参数类型声明和返回类型声明
- 使用 PHPUnit 12 新属性语法（`#[Test]`、`#[CoversClass]`、`#[Group]`、`#[TestDox]`）
- 修改 PHP 文件后必须运行 `vendor/bin/pint --dirty --format agent`
- 使用 `php artisan make:` 命令创建文件，传递 `--no-interaction` 参数
- AI Agent 使用 `php artisan make:agent` / `php artisan make:tool`（自定义 stub 在 stubs/）

## 5. 全局辅助函数（bootstrap/helpers.php）

| 函数 | 说明 |
|------|------|
| `cpu_count(): int` | 获取 CPU 核心数 |
| `per_page(Request $request, int $limit = 15): int` | 获取分页大小（限制 1~100） |
| `mobile_replace(?string $value): string` | 手机号脱敏 |
| `settings(string $key, $default): mixed` | 快捷读取系统设置 |
| `validation_exception($field, $message): void` | 快速抛出表单验证异常 |

---

> 文档来源：PRD 第 10 章
