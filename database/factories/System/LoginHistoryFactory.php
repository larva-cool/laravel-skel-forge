<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\System;

use App\Models\System\LoginHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 登录历史模型工厂
 *
 * @extends Factory<LoginHistory>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class LoginHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'user_type' => (new User)->getMorphClass(),
            'ip' => fake()->ipv4(),
            'port' => fake()->numberBetween(1024, 65535),
            'platform' => fake()->randomElement(['Windows', 'macOS', 'Linux', 'iOS', 'Android']),
            'device' => fake()->randomElement(['PC', 'iPhone', 'iPad', 'Android Phone', 'Tablet']),
            'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'user_agent' => fake()->userAgent(),
            'address' => fake()->city(),
            'login_at' => now(),
        ];
    }
}
