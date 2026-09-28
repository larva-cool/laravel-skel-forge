# 测试规范（项目增强规范）

## PHPUnit 12 属性语法

所有测试统一采用 PHPUnit 12 引入的新属性语法进行配置与标注：

- 始终使用 `Tests\TestCase` 作为测试基类
- `#[Test]` 用于标记测试方法（引入 `PHPUnit\Framework\Attributes\Test`）
- `#[CoversClass(ClassName::class)]` 用于指定测试的类（引入 `PHPUnit\Framework\Attributes\CoversClass`）
- `#[DataProvider('dataProviderMethod')]` 用于指定数据提供方法（引入 `PHPUnit\Framework\Attributes\DataProvider`）
- `#[Depends('testMethod')]` 用于指定依赖的测试方法（引入 `PHPUnit\Framework\Attributes\Depends`）
- `#[Group('groupName')]` 用于将测试分组（引入 `PHPUnit\Framework\Attributes\Group`）
- `#[TestDox('Test Description')]` 用于为测试方法添加描述（引入 `PHPUnit\Framework\Attributes\TestDox`），所有测试方法都应包含此属性

## 测试环境与命名

- 测试应针对 `.env.testing` 中定义的现有数据库进行。如果该文件不存在，则测试失败
- 所有测试方法命名均使用 `snake_case` 格式

## 覆盖率要求

- 保持至少 70% 的代码覆盖率
