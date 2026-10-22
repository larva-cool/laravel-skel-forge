<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 知识库文档索引状态
 */
enum KnowledgeDocumentStatus: string implements \JsonSerializable
{
    use HasLabel;

    /** 待处理（已创建，等待向量化） */
    case PENDING = 'pending';

    /** 处理中（正在分块与向量化） */
    case PROCESSING = 'processing';

    /** 已完成（可参与检索） */
    case READY = 'ready';

    /** 失败（最近一次索引失败，可重建） */
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待处理',
            self::PROCESSING => '处理中',
            self::READY => '已完成',
            self::FAILED => '失败',
        };
    }

    public function labelType(): string
    {
        return match ($this) {
            self::PENDING => 'info',
            self::PROCESSING => 'warning',
            self::READY => 'success',
            self::FAILED => 'danger',
        };
    }

    public function isReady(): bool
    {
        return $this === self::READY;
    }
}
