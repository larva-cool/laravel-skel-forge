<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories;

use App\Models\User\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 收货地址模型工厂
 *
 * @extends Factory<Address>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AddressFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Address>
     */
    protected $model = Address::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'country' => 'CN',
            'province' => fake()->randomElement(['浙江省', '江苏省', '广东省', '北京市']),
            'city' => fake()->randomElement(['杭州市', '南京市', '广州市', '北京市']),
            'district' => fake()->randomElement(['西湖区', '鼓楼区', '天河区', '朝阳区']),
            'address' => fake()->streetAddress(),
            'zipcode' => fake()->postcode(),
            'phone' => fake()->numerify('13#########'),
            'is_default' => false,
        ];
    }

    /**
     * 标记为默认地址
     */
    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
