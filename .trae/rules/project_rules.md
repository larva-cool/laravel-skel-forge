# Trae AI 项目规则文件

## 项目概述
本项目基于 Laravel 13.x 构建，采用 DDD 架构并遵循 RESTful API 设计规范。项目结构清晰，模块化程度高。

## 项目专属重要规则
以下为本项目特有的规则（通用 Laravel 规范请参考根目录 AGENTS.md），已按功能和场景拆分为独立的规则文件：

| 规则文件 | 说明 |
| --- | --- |
| [project_overview.md](./project_overview.md) | 项目概述与开发设置 |
| [apifox_rules.md](./apifox_rules.md) | Apifox 接口发布、模块归属与分页设计规范 |
| [automation_workflow.md](./automation_workflow.md) | 新功能/接口创建后的自动化流程 |
| [testing_rules.md](./testing_rules.md) | 测试规范（PHPUnit 12 属性语法、覆盖率要求） |
| [project_structure.md](./project_structure.md) | 项目目录结构与文件组织 |
| [menu_permission_rules.md](./menu_permission_rules.md) | 后台菜单与权限变更规范 |
| [file_storage_rules.md](./file_storage_rules.md) | 文件存储规范（禁止硬编码 disk 名） |
| [controller_request_rules.md](./controller_request_rules.md) | HTTP 控制器与 Request 职责划分规范 |
| [misc_rules.md](./misc_rules.md) | 其他项目专属约定 |

请记住遵循这些项目专属指南，通用 Laravel 开发规范请参考根目录 AGENTS.md。如有任何疑问或需要澄清，请咨询团队负责人或资深开发人员。
