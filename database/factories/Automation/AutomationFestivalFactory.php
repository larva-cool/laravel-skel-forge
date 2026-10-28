<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Automation;

use App\Enums\FestivalDateType;
use App\Models\Automation\AutomationFestival;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 自动化运营节日工厂
 *
 * @extends Factory<AutomationFestival>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationFestivalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AutomationFestival>
     */
    protected $model = AutomationFestival::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => '节日-'.fake()->unique()->word(),
            'date_type' => FestivalDateType::SOLAR,
            'month' => fake()->numberBetween(1, 12),
            'day' => fake()->numberBetween(1, 28),
            'status' => true,
            'sort' => fake()->numberBetween(0, 100),
            'remark' => null,
        ];
    }

    /**
     * 农历节日
     */
    public function lunar(): static
    {
        return $this->state(fn () => ['date_type' => FestivalDateType::LUNAR]);
    }

    /**
     * 禁用状态
     */
    public function disabled(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}
