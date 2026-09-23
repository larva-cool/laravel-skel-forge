<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1\User;

use App\Http\Controllers\Api\V1\User\AddressController;
use App\Models\User;
use Database\Factories\AddressFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 收货地址接口功能测试
 */
#[CoversClass(AddressController::class)]
#[Group('api')]
#[Group('address')]
class AddressControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * 以当前用户身份认证
     */
    private function actingAsUser(): void
    {
        Sanctum::actingAs($this->user);
    }

    /**
     * 有效地址请求数据
     *
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'name' => '张三',
            'country' => 'CN',
            'province' => '浙江省',
            'city' => '杭州市',
            'district' => '西湖区',
            'address' => '文一西路 969 号',
            'zipcode' => '310000',
            'phone' => '13800138000',
            'is_default' => false,
        ];
    }

    #[Test]
    #[TestDox('登录用户可创建收货地址')]
    public function authenticated_user_can_store_address(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/user/address', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('name', '张三')
            ->assertJsonPath('full_address', '浙江省杭州市西湖区文一西路 969 号');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $this->user->id,
            'name' => '张三',
            'phone' => '13800138000',
        ]);
    }

    #[Test]
    #[TestDox('创建地址缺少必填字段返回 422')]
    public function store_requires_required_fields(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/user/address', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'province', 'city', 'district', 'address', 'phone']);
    }

    #[Test]
    #[TestDox('创建地址手机号格式非法返回 422')]
    public function store_with_invalid_phone_returns_validation_error(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/user/address', [
            'name' => '张三',
            'province' => '浙江省',
            'city' => '杭州市',
            'district' => '西湖区',
            'address' => '文一西路 969 号',
            'phone' => '12345',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    #[Test]
    #[TestDox('登录用户可获取自己的地址列表')]
    public function authenticated_user_can_list_own_addresses(): void
    {
        $this->actingAsUser();

        AddressFactory::new()->count(2)->create(['user_id' => $this->user->id]);
        AddressFactory::new()->create(['user_id' => User::factory()->create()->id]);

        $this->getJson('/api/v1/user/address')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    #[TestDox('登录用户可查看自己的地址详情')]
    public function authenticated_user_can_show_own_address(): void
    {
        $this->actingAsUser();

        $address = AddressFactory::new()->create(['user_id' => $this->user->id]);

        $this->getJson('/api/v1/user/address/'.$address->id)
            ->assertOk()
            ->assertJsonPath('id', $address->id)
            ->assertJsonPath('name', $address->name);
    }

    #[Test]
    #[TestDox('用户无法查看他人的地址')]
    public function user_cannot_show_others_address(): void
    {
        $this->actingAsUser();

        $address = AddressFactory::new()->create(['user_id' => User::factory()->create()->id]);

        $this->getJson('/api/v1/user/address/'.$address->id)->assertForbidden();
    }

    #[Test]
    #[TestDox('登录用户可更新自己的地址')]
    public function authenticated_user_can_update_own_address(): void
    {
        $this->actingAsUser();

        $address = AddressFactory::new()->create(['user_id' => $this->user->id]);

        $this->putJson('/api/v1/user/address/'.$address->id, [
            'name' => '李四',
            'address' => '新地址 1 号',
        ])
            ->assertOk()
            ->assertJsonPath('name', '李四')
            ->assertJsonPath('address', '新地址 1 号');

        $this->assertSame('李四', $address->fresh()->name);
    }

    #[Test]
    #[TestDox('用户无法更新他人的地址')]
    public function user_cannot_update_others_address(): void
    {
        $this->actingAsUser();

        $address = AddressFactory::new()->create(['user_id' => User::factory()->create()->id]);

        $this->putJson('/api/v1/user/address/'.$address->id, ['name' => '李四'])->assertForbidden();
    }

    #[Test]
    #[TestDox('登录用户可将地址设为默认并取消旧默认')]
    public function authenticated_user_can_set_default_address(): void
    {
        $this->actingAsUser();

        $oldDefault = AddressFactory::new()->create(['user_id' => $this->user->id, 'is_default' => true]);
        $newDefault = AddressFactory::new()->create(['user_id' => $this->user->id, 'is_default' => false]);

        $this->putJson('/api/v1/user/address/'.$newDefault->id.'/default')
            ->assertOk()
            ->assertJsonPath('is_default', true);

        $this->assertFalse((bool) $oldDefault->fresh()->is_default);
        $this->assertTrue((bool) $newDefault->fresh()->is_default);
    }

    #[Test]
    #[TestDox('用户无法将他人地址设为默认')]
    public function user_cannot_set_others_address_default(): void
    {
        $this->actingAsUser();

        $address = AddressFactory::new()->create(['user_id' => User::factory()->create()->id]);

        $this->putJson('/api/v1/user/address/'.$address->id.'/default')->assertForbidden();
    }

    #[Test]
    #[TestDox('登录用户可删除自己的地址（软删除）')]
    public function authenticated_user_can_delete_own_address(): void
    {
        $this->actingAsUser();

        $address = AddressFactory::new()->create(['user_id' => $this->user->id]);

        $this->deleteJson('/api/v1/user/address/'.$address->id)->assertNoContent();

        $this->assertSoftDeleted('addresses', ['id' => $address->id]);
    }

    #[Test]
    #[TestDox('用户无法删除他人的地址')]
    public function user_cannot_delete_others_address(): void
    {
        $this->actingAsUser();

        $address = AddressFactory::new()->create(['user_id' => User::factory()->create()->id]);

        $this->deleteJson('/api/v1/user/address/'.$address->id)->assertForbidden();
    }

    /**
     * 需要认证的地址接口清单
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestForbiddenRouteProvider(): array
    {
        return [
            '地址列表' => ['getJson', '/api/v1/user/address'],
            '创建地址' => ['postJson', '/api/v1/user/address'],
        ];
    }

    #[Test]
    #[TestDox('未认证访问地址接口返回 401')]
    #[DataProvider('guestForbiddenRouteProvider')]
    public function guest_cannot_access_address_endpoints(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertUnauthorized();
    }

    #[Test]
    #[TestDox('未认证无法访问地址详情')]
    public function guest_cannot_show_address(): void
    {
        $address = AddressFactory::new()->create(['user_id' => $this->user->id]);

        $this->getJson('/api/v1/user/address/'.$address->id)->assertUnauthorized();
    }
}
