# 监控与维护

## 1. 监控工具

| 工具 | 用途 | 访问路径 |
|------|------|----------|
| Laravel Pulse | 应用性能监控 | `/pulse` |
| Laravel Horizon | 队列监控 | `/horizon` |
| Laravel Telescope | 调试辅助 | `/telescope`（local 环境） |
| Laravel Reverb | WebSocket 统计 | `/api/laravel-reverb` 或后台 Monitor |
| Laravel Pail | 实时日志 | 命令行 `php artisan pail` |

### 后台监控面板功能

| 监控项 | 说明 |
|--------|------|
| 服务器性能 | 读取 Laravel Pulse 数据（CPU、内存、请求量） |
| 队列监控 | 队列负载、慢任务统计 |
| Reverb 监控 | WebSocket 连接数、消息吞吐 |
| 缓存监控 | 缓存命中率统计 |
| 异常监控 | 应用异常聚合统计 |
| 慢查询/慢请求/慢任务 | 数据库慢查询、慢 HTTP 请求、慢队列任务 |
| 外部请求监控 | 慢外部 HTTP 请求 |

## 2. 日常维护

| 维护项 | 命令/方式 | 频率 |
|--------|----------|------|
| 清理过期密码重置令牌 | `php artisan auth:clear-resets` | 每日（通过调度任务） |
| 清理 Telescope 记录 | `php artisan telescope:prune --hours=48` | 每日（通过调度任务） |
| 清理审计日志和调度日志 | `model:prune` | 每日（通过调度任务，按配置保留天数） |
| 数据库优化 | 索引维护、慢查询优化 | 定期 |
| 依赖安全更新检查 | `composer audit` | 定期 |
| Horizon 快照记录 | `php artisan horizon:snapshot` | 每 5 分钟（调度任务） |

---

> 文档来源：PRD 第 12 章
