# 定时任务

在 `routes/console.php` 中定义定时任务，执行情况自动记录到 `schedule_logs` 表，可在后台"监控管理-调度日志"查看。

## 任务清单

| 任务 | 频率 | 说明 |
|------|------|------|
| `auth:clear-resets` | 每日 01:00 | 清理过期密码重置令牌 |
| `model:prune` | 每日 01:05 | 清理已标记删除的模型（含调度日志过期清理） |
| `stats:user` | 每日 01:10 | 统计昨日用户数据 |
| `telescope:prune --hours=48 --keep-exceptions` | 每日 01:15 | 清理 Telescope 记录 |
| `horizon:snapshot` | 每 5 分钟 | 记录应用状态快照 |
| `CloseExpiredRechargesJob` | 每 5 分钟 | 关闭超时未支付的充值订单（钱包 Phase 2 交付） |

## 调度日志

调度任务执行情况自动记录到 `schedule_logs` 表：

| 字段 | 说明 |
|------|------|
| task_name | 任务名称 |
| task_type | 任务类型（command/callback/exec） |
| expression | Cron 表达式 |
| status | 执行状态（running/success/failed/skipped） |
| exit_code | 退出码 |
| duration_ms | 执行耗时（毫秒） |
| exception | 异常信息摘要 |
| started_at | 开始时间 |
| finished_at | 结束时间 |

支持按 `schedule.log_prunable_days` 配置自动清理过期日志。

---

> 文档来源：PRD 第 13 章
