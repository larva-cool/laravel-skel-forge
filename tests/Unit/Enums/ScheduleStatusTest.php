<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\ScheduleStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * ScheduleStatus 枚举单元测试
 */
#[CoversClass(ScheduleStatus::class)]
#[Group('enums')]
class ScheduleStatusTest extends TestCase
{
    #[Test]
    #[TestDox('枚举值与预期一致')]
    public function cases_have_correct_values(): void
    {
        $this->assertSame(0, ScheduleStatus::RUNNING->value);
        $this->assertSame(1, ScheduleStatus::SUCCESS->value);
        $this->assertSame(2, ScheduleStatus::FAILED->value);
        $this->assertSame(3, ScheduleStatus::SKIPPED->value);
    }

    #[Test]
    #[TestDox('label 返回正确标签')]
    public function label_returns_correct_string(): void
    {
        $this->assertSame('执行中', ScheduleStatus::RUNNING->label());
        $this->assertSame('成功', ScheduleStatus::SUCCESS->label());
        $this->assertSame('失败', ScheduleStatus::FAILED->label());
        $this->assertSame('跳过', ScheduleStatus::SKIPPED->label());
    }

    #[Test]
    #[TestDox('isRunning 正确判断')]
    public function is_running(): void
    {
        $this->assertTrue(ScheduleStatus::RUNNING->isRunning());
        $this->assertFalse(ScheduleStatus::SUCCESS->isRunning());
    }

    #[Test]
    #[TestDox('isSuccess 正确判断')]
    public function is_success(): void
    {
        $this->assertTrue(ScheduleStatus::SUCCESS->isSuccess());
        $this->assertFalse(ScheduleStatus::FAILED->isSuccess());
    }

    #[Test]
    #[TestDox('isFailed 正确判断')]
    public function is_failed(): void
    {
        $this->assertTrue(ScheduleStatus::FAILED->isFailed());
        $this->assertFalse(ScheduleStatus::SKIPPED->isFailed());
    }

    #[Test]
    #[TestDox('isSkipped 正确判断')]
    public function is_skipped(): void
    {
        $this->assertTrue(ScheduleStatus::SKIPPED->isSkipped());
        $this->assertFalse(ScheduleStatus::RUNNING->isSkipped());
    }

    #[Test]
    #[TestDox('jsonSerialize 返回 value 和 label')]
    public function json_serialize_returns_value_and_label(): void
    {
        $this->assertSame(['value' => 2, 'label' => '失败'], ScheduleStatus::FAILED->jsonSerialize());
    }

    #[Test]
    #[TestDox('values 返回所有枚举值')]
    public function values_returns_all_values(): void
    {
        $this->assertSame([0, 1, 2, 3], ScheduleStatus::values());
    }

    #[Test]
    #[TestDox('options 返回键值对')]
    public function options_returns_key_value_pairs(): void
    {
        $this->assertSame([
            0 => '执行中',
            1 => '成功',
            2 => '失败',
            3 => '跳过',
        ], ScheduleStatus::options());
    }
}
