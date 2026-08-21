<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\System;

use App\Models\System\PhoneCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 手机验证码模型工厂
 *
 * @extends Factory<PhoneCode>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class PhoneCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scene' => 'default',
            'phone' => fake()->numerify('138#########'),
            'code' => fake()->numerify('######'),
            'ip' => fake()->ipv4(),
            'state' => 0,
            'verify_count' => 0,
            'result' => null,
        ];
    }

    /**
     * 标记为已使用
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => PhoneCode::USED_STATE,
            'usage_at' => now(),
        ]);
    }
}
