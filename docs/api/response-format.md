# 响应格式与 HTTP 状态码规范

## 1. 响应格式

项目使用 Laravel 原生响应 + Eloquent API Resources，**不使用统一 code/msg/data 包装**。调用 `JsonResource::withoutWrapping()` 取消默认 data 包裹，通过 HTTP 状态码表达结果。

### 1.1 单条资源（GET 详情、POST 创建）

```json
{
  "id": 1,
  "name": "...",
  "...": "其他字段"
}
```

### 1.2 分页列表（GET 列表）

```json
{
  "data": [ { "...": "资源对象" } ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 100 }
}
```

### 1.3 操作成功（PUT/PATCH/DELETE）

`204 No Content` 或 `{ "message": "操作成功" }`

### 1.4 认证成功（POST login）

```json
{
  "access_token": "1|laravel_sanctum_token_xxx",
  "user": { "user_id": 1, "user_name": "admin", "...": "..." }
}
```

### 1.5 错误响应

```json
// 401 未认证
{ "message": "Unauthenticated." }

// 403 无权限
{ "message": "This action is unauthorized." }

// 422 验证失败
{ "message": "The given data was invalid.", "errors": { "field": ["错误信息"] } }
```

## 2. HTTP 状态码规范

| 状态码 | 含义 |
|--------|------|
| 200 | 成功（查询/更新/同步二进制下载） |
| 201 | 创建成功 |
| 202 | 已接受（异步入队，如大批量导出任务，响应体携带任务入队提示消息） |
| 204 | 删除/操作成功，无返回内容 |
| 400 | 请求参数错误 |
| 401 | 未认证或 Token 过期 |
| 403 | 无权限访问 |
| 404 | 资源不存在 |
| 422 | 数据验证失败 |
| 429 | 请求频率超限 |
| 500 | 服务器错误 |

---

> 文档来源：PRD 第 6.1、6.2 节
