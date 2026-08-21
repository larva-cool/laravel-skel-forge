<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 附件类型枚举
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
enum AttachmentType: string implements \JsonSerializable
{
    use HasLabel;

    case IMAGE = 'image'; // 图片
    case VIDEO = 'video'; // 视频
    case AUDIO = 'audio'; // 音频
    case DOCUMENT = 'document'; // 文档
    case OTHER = 'other'; // 其他

    /**
     * 获取附件类型标签
     */
    public function label(): string
    {
        return match ($this) {
            self::IMAGE => '图片',
            self::VIDEO => '视频',
            self::AUDIO => '音频',
            self::DOCUMENT => '文档',
            self::OTHER => '其他',
        };
    }

    /**
     * 根据 MIME 类型推断附件类型
     */
    public static function fromMimeType(string $mimeType): self
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => self::IMAGE,
            str_starts_with($mimeType, 'video/') => self::VIDEO,
            str_starts_with($mimeType, 'audio/') => self::AUDIO,
            in_array($mimeType, self::documentMimeTypes(), true) => self::DOCUMENT,
            default => self::OTHER,
        };
    }

    /**
     * 文档类 MIME 类型集合
     *
     * @return array<int, string>
     */
    private static function documentMimeTypes(): array
    {
        return [
            'text/plain',
            'text/csv',
            'text/html',
            'application/pdf',
            'application/rtf',
            'application/msword',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
    }
}
