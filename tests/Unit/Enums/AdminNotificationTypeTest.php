<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\AdminNotificationType;
use App\Enums\NotificationLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * AdminNotificationType 枚举单元测试
 */
#[CoversClass(AdminNotificationType::class)]
#[Group('enums')]
class AdminNotificationTypeTest extends TestCase
{
    #[Test]
    #[TestDox('value 与现有通知 payload 字符串完全一致（向后兼容）')]
    public function values_are_backward_compatible(): void
    {
        $this->assertSame('export.completed', AdminNotificationType::EXPORT_COMPLETED->value);
        $this->assertSame('export.failed', AdminNotificationType::EXPORT_FAILED->value);
        $this->assertSame('segment.task.completed', AdminNotificationType::SEGMENT_TASK_COMPLETED->value);
        $this->assertSame('segment.task.failed', AdminNotificationType::SEGMENT_TASK_FAILED->value);
    }

    #[Test]
    #[TestDox('label 返回正确中文标签')]
    public function label_returns_chinese(): void
    {
        $this->assertSame('导出完成', AdminNotificationType::EXPORT_COMPLETED->label());
        $this->assertSame('导出失败', AdminNotificationType::EXPORT_FAILED->label());
        $this->assertSame('分群任务完成', AdminNotificationType::SEGMENT_TASK_COMPLETED->label());
        $this->assertSame('分群任务失败', AdminNotificationType::SEGMENT_TASK_FAILED->label());
    }

    #[Test]
    #[TestDox('完成型 case 默认级别为 SUCCESS')]
    public function completed_default_level_is_success(): void
    {
        $this->assertSame(NotificationLevel::SUCCESS, AdminNotificationType::EXPORT_COMPLETED->defaultLevel());
        $this->assertSame(NotificationLevel::SUCCESS, AdminNotificationType::SEGMENT_TASK_COMPLETED->defaultLevel());
    }

    #[Test]
    #[TestDox('失败型 case 默认级别为 ERROR')]
    public function failed_default_level_is_error(): void
    {
        $this->assertSame(NotificationLevel::ERROR, AdminNotificationType::EXPORT_FAILED->defaultLevel());
        $this->assertSame(NotificationLevel::ERROR, AdminNotificationType::SEGMENT_TASK_FAILED->defaultLevel());
    }

    #[Test]
    #[TestDox('tryFrom 支持已注册字符串，未知字符串返回 null')]
    public function try_from_handles_unknown_value(): void
    {
        $this->assertSame(
            AdminNotificationType::EXPORT_FAILED,
            AdminNotificationType::tryFrom('export.failed'),
        );
        $this->assertNull(AdminNotificationType::tryFrom('not.a.real.type'));
    }

    #[Test]
    #[TestDox('options 返回给前端下拉/筛选使用的键值对')]
    public function options_for_filter_dropdown(): void
    {
        $this->assertSame([
            'export.completed' => '导出完成',
            'export.failed' => '导出失败',
            'segment.task.completed' => '分群任务完成',
            'segment.task.failed' => '分群任务失败',
        ], AdminNotificationType::options());
    }
}
