<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 管理员域通知业务类型枚举
 *
 * value 即写入 notifications.data.type 字段的字符串，需保持向后兼容
 * （现有通知类与前端列表筛选已依赖这些字符串）。
 *
 * 新增 type 时务必：
 *  1. 用领域前缀（如 export.* / segment.*），避免与未来用户域冲突
 *  2. 在 defaultLevel() 中指定语义级别
 *  3. 在对应通知类中提供中英文案（下一阶段会迁移至 lang/）
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
enum AdminNotificationType: string implements \JsonSerializable
{
    use HasLabel;

    case EXPORT_COMPLETED = 'export.completed';
    case EXPORT_FAILED = 'export.failed';
    case SEGMENT_TASK_COMPLETED = 'segment.task.completed';
    case SEGMENT_TASK_FAILED = 'segment.task.failed';

    public function label(): string
    {
        return match ($this) {
            self::EXPORT_COMPLETED => '导出完成',
            self::EXPORT_FAILED => '导出失败',
            self::SEGMENT_TASK_COMPLETED => '分群任务完成',
            self::SEGMENT_TASK_FAILED => '分群任务失败',
        };
    }

    /**
     * 该类型默认的严重级别（用于通知中心图标/颜色）。
     * 子类未来可覆盖，本枚举仅给出建议默认值。
     */
    public function defaultLevel(): NotificationLevel
    {
        return match ($this) {
            self::EXPORT_COMPLETED, self::SEGMENT_TASK_COMPLETED => NotificationLevel::SUCCESS,
            self::EXPORT_FAILED, self::SEGMENT_TASK_FAILED => NotificationLevel::ERROR,
        };
    }
}
