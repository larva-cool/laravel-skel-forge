<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Services;

use App\Enums\AttachmentType;
use App\Models\System\Attachment;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 附件服务
 *
 * 负责 attachments 台账与底层 Storage 的一致性编排：元数据入库、物理字节流全部交由
 * Storage::disk() 处理，从而保证 local / public / s3 等驱动行为一致。
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class AttachmentService
{
    /**
     * 写入附件台账。
     *
     * @param  array{disk: string, path: string, name?: string, original_name?: string, extension?: string, mime_type?: string, size?: int, hash?: string|null}  $meta
     */
    public function record(array $meta, ?Model $uploader = null): Attachment
    {
        $mimeType = $meta['mime_type'] ?? '';
        $path = $meta['path'];

        return Attachment::query()->create([
            'uploader_id' => $uploader?->getKey(),
            'uploader_type' => $uploader?->getMorphClass(),
            'disk' => $meta['disk'],
            'path' => $path,
            'name' => $meta['name'] ?? basename($path),
            'original_name' => $meta['original_name'] ?? basename($path),
            'extension' => $meta['extension'] ?? pathinfo($path, PATHINFO_EXTENSION),
            'mime_type' => $mimeType,
            'type' => AttachmentType::fromMimeType($mimeType),
            'size' => $meta['size'] ?? 0,
            'hash' => $meta['hash'] ?? null,
        ]);
    }

    /**
     * 获取附件可访问地址，私有磁盘返回 null。
     */
    public function url(Attachment $attachment): ?string
    {
        if (! $this->isPublic($attachment)) {
            return null;
        }

        try {
            return $this->disk($attachment)->url($attachment->path);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * 获取附件临时签名访问地址。
     */
    public function temporaryUrl(Attachment $attachment, ?int $minutes = null): string
    {
        $minutes = $minutes ?: (int) settings('upload.temporary_url_minutes', 10);

        try {
            return $this->disk($attachment)->temporaryUrl($attachment->path, Carbon::now()->addMinutes($minutes));
        } catch (\Throwable) {
            $url = $this->url($attachment);
            if ($url === null) {
                abort(422, __('admin.attachment_temporary_url_unsupported'));
            }

            return $url;
        }
    }

    /**
     * 流式下载附件，保留原始文件名。
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        $this->ensureExists($attachment);

        return $this->disk($attachment)->download($attachment->path, $attachment->original_name);
    }

    /**
     * 重命名附件显示名（不改动物理路径）。
     */
    public function rename(Attachment $attachment, string $name): Attachment
    {
        $attachment->update(['name' => $name]);

        return $attachment->refresh();
    }

    /**
     * 同磁盘内移动附件物理路径。
     */
    public function move(Attachment $attachment, string $newPath): Attachment
    {
        $newPath = ltrim($newPath, '/');
        if (! str_starts_with($newPath, Attachment::PATH_PREFIX)) {
            abort(422, __('admin.attachment_invalid_target_path'));
        }

        if ($newPath === $attachment->path) {
            return $attachment;
        }

        $disk = $this->disk($attachment);
        $this->ensureExists($attachment);

        if ($disk->exists($newPath)) {
            abort(422, __('admin.attachment_target_exists'));
        }

        return DB::transaction(function () use ($attachment, $disk, $newPath) {
            $oldPath = $attachment->path;
            $attachment->update(['path' => $newPath]);

            if (! $disk->move($oldPath, $newPath)) {
                throw new \RuntimeException(__('admin.attachment_move_failed'));
            }

            return $attachment->refresh();
        });
    }

    /**
     * 删除附件：先删物理文件，再软删台账。
     *
     * 物理文件已丢失时视为删除成功，仅清理台账。
     */
    public function delete(Attachment $attachment): bool
    {
        return (bool) DB::transaction(function () use ($attachment) {
            $attachment->delete();

            $disk = $this->disk($attachment);
            if ($disk->exists($attachment->path)) {
                $disk->delete($attachment->path);
            }

            return true;
        });
    }

    /**
     * 批量删除附件，按磁盘分组减少远端请求次数。
     *
     * @param  array<int, int|string>  $ids
     * @return int 实际删除条数
     */
    public function deleteMany(array $ids): int
    {
        $attachments = Attachment::query()->whereIn('id', $ids)->get();
        if ($attachments->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($attachments) {
            Attachment::query()->whereIn('id', $attachments->modelKeys())->delete();

            foreach ($attachments->groupBy('disk') as $disk => $group) {
                Storage::disk((string) $disk)->delete($group->pluck('path')->all());
            }

            return $attachments->count();
        });
    }

    /**
     * 校验附件物理文件是否仍存在。
     */
    public function exists(Attachment $attachment): bool
    {
        return $this->disk($attachment)->exists($attachment->path);
    }

    /**
     * 获取附件所属磁盘实例。
     */
    protected function disk(Attachment $attachment): Filesystem
    {
        return Storage::disk($attachment->disk);
    }

    /**
     * 判断附件所在磁盘是否公开可访问。
     */
    protected function isPublic(Attachment $attachment): bool
    {
        return config("filesystems.disks.{$attachment->disk}.visibility") === 'public'
            || config("filesystems.disks.{$attachment->disk}.url") !== null;
    }

    /**
     * 断言物理文件存在，否则抛出 422。
     */
    protected function ensureExists(Attachment $attachment): void
    {
        if (! $this->exists($attachment)) {
            abort(422, __('admin.attachment_file_missing'));
        }
    }
}
