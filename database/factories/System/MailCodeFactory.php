<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\System;

use App\Models\System\MailCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 邮件验证码模型工厂
 *
 * @extends Factory<MailCode>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class MailCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->safeEmail(),
            'code' => fake()->numerify('######'),
            'ip' => fake()->ipv4(),
            'state' => 0,
            'verify_count' => 0,
        ];
    }

    /**
     * 标记为已使用
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => MailCode::USED_STATE,
            'usage_at' => now(),
        ]);
    }
}
