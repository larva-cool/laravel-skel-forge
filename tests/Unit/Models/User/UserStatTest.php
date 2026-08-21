<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\User;

use App\Models\User\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * UserStat 模型单元测试
 */
#[CoversClass(UserStat::class)]
#[Group('models')]
#[Group('user-stat')]
class UserStatTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('fillable 允许批量赋值指定字段')]
    public function fillable_allows_mass_assignment(): void
    {
        $stat = UserStat::create([
            'stat_date' => '2025-01-01',
            'total_user_count' => 100,
            'new_user_count' => 10,
            'active_user_count' => 50,
        ]);

        $this->assertSame('2025-01-01', $stat->stat_date->toDateString());
        $this->assertSame(100, $stat->total_user_count);
        $this->assertSame(10, $stat->new_user_count);
        $this->assertSame(50, $stat->active_user_count);
    }

    #[Test]
    #[TestDox('count 字段默认为 0')]
    public function count_fields_default_to_zero(): void
    {
        $stat = UserStat::create(['stat_date' => '2025-01-01']);

        $this->assertSame(0, $stat->total_user_count);
        $this->assertSame(0, $stat->new_user_count);
        $this->assertSame(0, $stat->active_user_count);
    }

    #[Test]
    #[TestDox('fillable 允许批量赋值积分与金币统计字段')]
    public function fillable_allows_point_and_coin_fields(): void
    {
        $stat = UserStat::create([
            'stat_date' => '2025-01-01',
            'total_point_count' => 1000,
            'incr_point_count' => 200,
            'decr_point_count' => 50,
            'total_coin_count' => 3000,
            'incr_coin_count' => 400,
            'decr_coin_count' => 100,
        ]);

        $this->assertSame(1000, $stat->total_point_count);
        $this->assertSame(200, $stat->incr_point_count);
        $this->assertSame(50, $stat->decr_point_count);
        $this->assertSame(3000, $stat->total_coin_count);
        $this->assertSame(400, $stat->incr_coin_count);
        $this->assertSame(100, $stat->decr_coin_count);
    }

    #[Test]
    #[TestDox('积分与金币统计字段默认为 0')]
    public function point_and_coin_fields_default_to_zero(): void
    {
        $stat = UserStat::create(['stat_date' => '2025-01-01']);

        $this->assertSame(0, $stat->total_point_count);
        $this->assertSame(0, $stat->incr_point_count);
        $this->assertSame(0, $stat->decr_point_count);
        $this->assertSame(0, $stat->total_coin_count);
        $this->assertSame(0, $stat->incr_coin_count);
        $this->assertSame(0, $stat->decr_coin_count);
    }

    #[Test]
    #[TestDox('stat_date 正确 cast 为 date')]
    public function stat_date_is_cast_to_date(): void
    {
        $stat = UserStat::create(['stat_date' => '2025-01-15']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $stat->stat_date);
        $this->assertSame('2025-01-15', $stat->stat_date->toDateString());
    }

    #[Test]
    #[TestDox('count 字段正确 cast 为 integer')]
    public function count_fields_are_cast_to_integer(): void
    {
        $stat = UserStat::create([
            'stat_date' => '2025-01-01',
            'total_user_count' => '100',
            'new_user_count' => '5',
            'active_user_count' => '20',
        ]);

        $this->assertIsInt($stat->total_user_count);
        $this->assertIsInt($stat->new_user_count);
        $this->assertIsInt($stat->active_user_count);
    }

    #[Test]
    #[TestDox('created_at 自动设置')]
    public function created_at_is_auto_set(): void
    {
        $stat = UserStat::create(['stat_date' => '2025-01-01']);

        $this->assertNotNull($stat->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $stat->created_at);
    }

    #[Test]
    #[TestDox('UPDATED_AT 为 null 不更新时间戳')]
    public function updated_at_is_null_constant(): void
    {
        $this->assertNull(UserStat::UPDATED_AT);
    }
}
