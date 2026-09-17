<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * 附件路径 ↔ URL 转换 Cast
 *
 * 数据库只存相对路径（如 `identity/id-card.jpg`），模型访问时自动转为
 * 可访问 URL；写入时若传入完整 URL 则自动提取为相对路径。
 *
 * 使用方式：
 *   protected function casts(): array
 *   {
 *       return [
 *           'avatar' => AsAttachmentUrl::class,
 *           'cover'  => AsAttachmentUrl::class,
 *       ];
 *   }
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AsAttachmentUrl implements CastsAttributes
{
    /**
     * 从数据库读取路径 → 转为完整 URL
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // 已是完整 URL 直接返回
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        try {
            return Storage::url($value);
        } catch (\RuntimeException) {
            return $value;
        }
    }

    /**
     * 写入数据库 → 提取相对路径
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;

        // 若是完整 URL，尝试提取相对路径
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            try {
                $baseUrl = Storage::url('');
                if ($baseUrl !== '' && str_starts_with($value, $baseUrl)) {
                    $path = substr($value, strlen($baseUrl));

                    return ltrim($path, '/');
                }
            } catch (\RuntimeException) {
                // 无法解析时保留原值
            }
        }

        return $value;
    }
}
