<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Open;

use App\Enums\IdentityStatus;
use App\Enums\UserStatus;
use App\Models\Open\OpenAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * 开放平台账户模型工厂
 *
 * @extends Factory<OpenAccount>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class OpenAccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<OpenAccount>
     */
    protected $model = OpenAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('13#########'),
            'name' => fake()->company(),
            'avatar' => null,
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->numerify('13#########'),
            'contact_email' => fake()->safeEmail(),
            'rate_limit' => 60,
            'available_points' => 0,
            'available_coins' => 0,
            'balance' => 0,
            'frozen_balance' => 0,
            'status' => UserStatus::ACTIVE,
            'password' => Hash::make('12345678'),
            'login_count' => 0,
            'identity_verified' => false,
            'identity_status' => IdentityStatus::UNSUBMITTED,
        ];
    }

    /**
     * 已冻结状态
     */
    public function frozen(): static
    {
        return $this->state(fn () => ['status' => UserStatus::FROZEN]);
    }

    /**
     * 正常状态
     */
    public function active(): static
    {
        return $this->state(fn () => ['status' => UserStatus::ACTIVE]);
    }
}
