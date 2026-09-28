# Apifox 相关规范

## 触发条件
- 当用户提到"发布到apifox"时，自动执行完整的发布流程
- Apifox 接口模块归属与模型隔离规范
- Apifox 分页接口设计规范

## Apifox 接口模块归属与模型隔离规范

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

## Apifox 分页接口设计规范

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

## 发布到 Apifox 完整流程

1. 调用 `apifox-cli` skill 获取 Apifox CLI 使用指引
2. 根据具体场景调用对应的 apifox skill（如 `apifox-import-export`、`apifox-branch`、`apifox-workflow-api-lifecycle` 等）
3. 使用 Apifox CLI 执行实际的发布操作（如导入/导出 OpenAPI 文档、同步接口到 Apifox 项目等）
4. 执行完成后，使用 `apifox-cli-checkup` skill 验证操作结果是否生效
