# 附件管理技术方案

## 1. 设计原则

- **元数据入库、字节流交给 Storage**：`attachments` 表只保存 `disk + path` 等元数据，所有物理读写通过 `Storage::disk($disk)` 完成，不出现任何 `storage_path()` 硬编码，保证 local/public/s3 三种驱动行为一致。
- **复用现有上传链路**：不改动 `UploaderController` 的对外契约，上传落盘成功后由 `AttachmentService` 追加台账写入，返回体增加 `id` 字段。
- **分层职责清晰**：Controller 只做 HTTP 编排，物理文件操作与台账一致性收敛到 `AttachmentService`。
- **失败可回滚**：删除、移动等涉及"数据库 + 物理文件"两段操作以数据库事务包裹，物理操作失败即回滚台账。

## 2. 代码结构

```
app/
├── Enums/
│   └── AttachmentType.php              # 附件类型枚举（image/video/audio/document/other，实现 HasLabel）
├── Models/System/
│   └── Attachment.php                  # 附件模型（软删除 + morphTo uploader）
├── Services/
│   └── AttachmentService.php           # 台账 + Storage 一致性编排
├── Http/
│   ├── Controllers/Admin/
│   │   └── AttachmentController.php    # 后台附件管理控制器
│   ├── Requests/Admin/Attachment/
│   │   ├── AttachmentIndexRequest.php  # 列表筛选参数校验
│   │   ├── AttachmentRenameRequest.php # 重命名校验
│   │   ├── AttachmentMoveRequest.php   # 移动路径校验
│   │   ├── AttachmentDestroyRequest.php# 批量删除 ids 校验
│   │   └── AttachmentRegisterRequest.php # 直传登记校验
│   └── Resources/Admin/
│       └── AttachmentResource.php      # 附件资源
database/
├── migrations/
│   └── 0001_01_01_000016_create_attachments_table.php
└── factories/System/
    └── AttachmentFactory.php
tests/Feature/Admin/
└── AttachmentControllerTest.php
```

## 3. 数据表设计要点

- 主键从 `10000000` 起（与其他业务表保持一致风格），带表注释与字段注释。
- 索引：`(uploader_type, uploader_id)` 复合索引、`type` 索引、`hash` 索引、`(disk, path)` 唯一索引（防止同一物理文件重复登记）。
- 使用软删除 `deleted_at`：接口默认执行"硬删除物理文件 + 软删除台账"，保留审计线索。
- `size` 使用 `unsignedBigInteger`，`hash` 使用 `char(32)`（md5）并允许为空（直传场景可能拿不到内容哈希）。

## 4. AttachmentService 关键方法

| 方法 | 职责 | Storage 调用 |
|------|------|-------------|
| `record(array $meta): Attachment` | 上传成功后写台账 | 无 |
| `url(Attachment $a): ?string` | 公有盘返回直链 | `Storage::disk()->url()` |
| `temporaryUrl(Attachment $a, int $minutes): string` | 私有盘签名地址；本地盘回退直链 | `Storage::disk()->temporaryUrl()` |
| `download(Attachment $a): StreamedResponse` | 流式下载，保留原始名 | `Storage::disk()->download()` |
| `move(Attachment $a, string $newPath): Attachment` | 同盘移动，事务内先改库再移文件 | `Storage::disk()->move()` |
| `delete(Attachment $a): bool` | 删物理文件后软删台账 | `Storage::disk()->delete()` |
| `deleteMany(array $ids): int` | 按磁盘分组批量删除 | `Storage::disk()->delete(array)` |
| `exists(Attachment $a): bool` | 校验物理文件是否仍存在 | `Storage::disk()->exists()` |

### 关键实现要点

- `temporaryUrl()` 需捕获驱动不支持的异常（local 驱动在未启用 `serve` 时不支持），降级为 `url()` 或 422 提示。
- `move()` 只允许同磁盘内移动，目标路径必须落在 `uploads/` 前缀下，且先做 `exists()` 冲突检查，避免覆盖既有文件。
- `deleteMany()` 按 `disk` 分组后一次性传数组给 `Storage::disk()->delete()`，减少远端请求次数。
- 物理文件已丢失（`exists() === false`）时删除仍视为成功，只清理台账，避免脏数据无法删除。

## 5. 上传链路改造

在 `UploadRequest::handleUpload()` 落盘成功后调用 `AttachmentService::record()`，`uploader` 取 `$this->user()`（morphTo 兼容 Admin 与 User 两种守卫）。返回结构在原有字段上追加 `id`。

直传场景（`uploader/token`）文件不经过服务端，改为前端上传成功后调用 `POST /admin/attachments/register` 补登记，服务端用 `Storage::disk()->exists()` 与 `size()` 校验真实存在后再入库，防止伪造记录。

## 6. 权限与菜单

- 新增权限：`attachments.index`、`attachments.create`、`attachments.edit`、`attachments.delete`，在控制器构造器中以 `permission:` 中间件绑定。
- 新增菜单：`system.attachment`，挂在"系统管理"目录下，含"删除附件""重命名附件""移动附件"按钮权限。
- 通过迁移追加权限与菜单，不修改已执行过的历史迁移。

## 7. 配置项

沿用现有 `upload.*` 配置（`upload.storage`、`upload.allow_extension` 等），新增：

| 配置键 | 默认值 | 说明 |
|--------|--------|------|
| upload.temporary_url_minutes | 10 | 临时签名 URL 有效期（分钟） |
| upload.prune_orphan_days | 0 | 孤儿附件自动清理天数，0 表示不清理 |

---

> 文档来源：PRD 第 7 章
