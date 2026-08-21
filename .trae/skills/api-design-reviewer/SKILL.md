---
name: API Design Reviewer
description: 审查 RESTful API 设计是否符合行业最佳实践和规范。
---

# API Design Reviewer

## Description
审查 RESTful API 设计是否符合行业最佳实践和规范。

## When to use
- 设计新 API 接口时
- 评审 API 变更时
- 重构现有 API 时

## Instructions
1. **URL 设计审查**
   - 使用名词复数形式（/users 而非 /user）
   - 层级不超过 3 层
   - 避免动词，用 HTTP 方法表达操作
   - 使用 kebab-case 命名

2. **HTTP 方法正确性**
   - GET：查询，幂等，无副作用
   - POST：创建，非幂等
   - PUT：完整更新，幂等
   - PATCH：部分更新，幂等
   - DELETE：删除，幂等

3. **响应格式规范**
   - 统一使用 JSON 格式
   - 成功响应返回适当的状态码
   - 错误响应包含错误码和详细信息
   - 分页数据包含元信息

4. **版本控制策略**
   - URL 版本（/v1/users）或 Header 版本
   - 破坏性变更必须升级版本
   - 旧版本保留合理的废弃期

## Output Format
### 符合规范
- [列出符合规范的点]

### 需要改进
- [问题] → [建议]

### 参考规范
- [相关规范链接]