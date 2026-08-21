<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\Traits;

use App\Models\Traits\DateTimeFormatter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * DateTimeFormatter trait 单元测试
 */
#[CoversClass(DateTimeFormatter::class)]
#[Group('traits')]
class DateTimeFormatterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('serializeDate 返回正确格式的日期字符串')]
    public function serialize_date_returns_correct_format(): void
    {
        $user = User::factory()->create();
        $date = Carbon::parse('2025-01-15 10:30:00');

        $reflection = new \ReflectionMethod($user, 'serializeDate');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($user, $date);

        $this->assertSame('2025-01-15 10:30:00', $result);
    }

    #[Test]
    #[TestDox('toArray 中日期使用 serializeDate 格式')]
    public function to_array_uses_serialize_date_format(): void
    {
        $user = User::factory()->create([
            'created_at' => '2025-06-15 12:00:00',
        ]);

        $array = $user->toArray();

        $this->assertSame('2025-06-15 12:00:00', $array['created_at']);
    }
}
