# 项目概述与开发设置

## 项目概述
本项目基于 Laravel 13.x 构建，采用 DDD 架构并遵循 RESTful API 设计规范。项目结构清晰，模块化程度高。

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
