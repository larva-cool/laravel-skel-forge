# 核心服务

## 1. 验证码服务

| 功能 | 说明 |
|------|------|
| 短信验证码发送 | 支持多种场景（登录、注册、重置密码等），EasySMS 多网关支持 |
| 短信验证码验证 | 验证验证码的正确性，过期/错误次数限制 |
| 邮件验证码发送 | 通过邮件发送验证码 |
| 邮件验证码验证 | 验证邮件验证码 |
| 验证码记录管理 | 记录和查询验证码发送历史 |

### 验证码表

- `phone_codes`：短信验证码（phone、scene、code、state、ip、send_at）
- `mail_codes`：邮件验证码（email、scene、code、state、ip、send_at）

## 2. 文件上传服务

| 功能 | 说明 |
|------|------|
| 文件上传 | 通用文件上传接口 |
| 图片上传 | 图片专用上传接口 |
| 视频上传 | 视频专用上传接口 |
| APK 上传 | Android 安装包专用上传接口（返回附件 id 和文件元信息） |
| 上传Token生成 | 生成临时直传 Token（兼容 S3 协议） |
| 文件安全 | 上传文件类型和大小限制，自动写入附件台账 |

详细技术方案见：[附件管理技术方案](../technical-solutions/attachment-management.md)

## 3. 系统监控

| 功能 | 说明 |
|------|------|
| 应用性能监控 | Laravel Pulse 提供性能监控（/pulse） |
| 调试辅助 | Laravel Telescope 提供调试功能（/telescope，仅 local） |
| 队列管理 | Laravel Horizon 提供队列管理（/horizon） |
| 实时日志 | Laravel Pail 提供实时日志查看 |
| WebSocket | Laravel Reverb 提供实时通信服务 |
| 高性能服务 | Laravel Octane 支持 Swoole/RoadRunner |

---

> 文档来源：PRD 第 3.5 节
