# 部署方案

## 1. 生产环境配置

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_CLIENT=phpredis

BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=s3
```

### 优化命令

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
```

## 2. 部署方式

| 方式 | 说明 |
|------|------|
| Octane + Swoole/RoadRunner | 高性能常驻内存模式 |
| Nginx + PHP-FPM | 传统部署方式 |

可通过 [Laravel Cloud](https://cloud.laravel.com/) 快速部署。

## 3. 服务配置

| 服务 | 建议配置 |
|------|----------|
| Horizon | Supervisor 守护，`php artisan horizon` |
| Reverb | 独立进程或 Supervisor 守护，`php artisan reverb:start` |
| Scheduler | Cron 每分钟执行 `php artisan schedule:run` |
| Pulse | 访问控制保护（Gate 鉴权） |
| Telescope | 生产环境禁用或仅限管理员访问 |

## 4. 备份策略

- 数据库每日备份
- 重要配置备份
- 上传文件备份（如使用 S3 可开启版本控制）
- 备份保留 30 天

---

> 文档来源：PRD 第 11 章
