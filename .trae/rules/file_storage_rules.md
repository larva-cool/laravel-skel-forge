# 文件存储规范

## 核心原则

**禁止在代码中硬编码 `Storage::disk('local')` / `Storage::disk('public')` 等 disk 名**

## 示例

```php
// ✅ 正确：使用框架默认磁盘
$disk = Storage::put($path, $content);

// ❌ 错误：硬编码 disk 名
Storage::disk('local')->put($path, $content);
```

## 详细规则

- 调用方通过 `Storage::` 门面方法（如 `Storage::put`、`Storage::get`、`Storage::url`、`Storage::delete` 等）直接使用，由 `config/filesystems.php` 中 `default` 配置决定落盘位置
- 跨 disk 集成（例如同时写本地磁盘与对象存储）必须通过新增配置项 + 服务容器绑定完成，禁止在业务代码里硬编码 disk 名
- 单元测试或功能测试中如需指定磁盘，使用 `Storage::fake('disk-name')`；业务代码本身不要直接传入 disk 名字符串
