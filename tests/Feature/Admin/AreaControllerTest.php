<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AreaController;
use App\Models\Admin\Admin;
use App\Models\System\Area;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台地区管理控制器功能测试
 */
#[CoversClass(AreaController::class)]
#[Group('admin')]
#[Group('area')]
class AreaControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    #[TestDox('未登录访问地区列表返回 401')]
    public function guest_cannot_list_areas(): void
    {
        $response = $this->getJson('/admin/areas');
        $response->assertUnauthorized();
    }

    #[Test]
    #[TestDox('获取地区树形结构返回 200')]
    public function admin_can_list_area_tree(): void
    {
        $parent = Area::create(['name' => '北京市', 'sort' => 1]);
        Area::create(['name' => '东城区', 'parent_id' => $parent->id, 'sort' => 1]);
        Area::create(['name' => '西城区', 'parent_id' => $parent->id, 'sort' => 2]);

        $response = $this->actingAsAdmin()->getJson('/admin/areas');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', '北京市')
            ->assertJsonPath('0.children.0.name', '东城区')
            ->assertJsonPath('0.children.1.name', '西城区');
    }

    #[Test]
    #[TestDox('空数据库时获取地区列表返回空数组')]
    public function empty_area_list_returns_empty_array(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/areas');
        $response->assertOk()
            ->assertJson([]);
    }

    #[Test]
    #[TestDox('创建地区返回 201')]
    public function admin_can_create_area(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/areas', [
            'name' => '上海市',
            'sort' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', '上海市')
            ->assertJsonPath('sort', 1);

        $this->assertDatabaseHas('areas', ['name' => '上海市']);
    }

    #[Test]
    #[TestDox('创建子地区')]
    public function admin_can_create_child_area(): void
    {
        $parent = Area::create(['name' => '广东省']);

        $response = $this->actingAsAdmin()->postJson('/admin/areas', [
            'parent_id' => $parent->id,
            'name' => '深圳市',
        ]);

        $response->assertCreated()
            ->assertJsonPath('parent_id', $parent->id)
            ->assertJsonPath('name', '深圳市');
    }

    #[Test]
    #[TestDox('创建地区时名称必填返回 422')]
    public function create_area_requires_name(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/areas', []);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    #[TestDox('创建地区时 parent_id 必须存在返回 422')]
    public function create_area_parent_id_must_exist(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/areas', [
            'name' => '测试地区',
            'parent_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    #[Test]
    #[TestDox('获取地区详情')]
    public function admin_can_view_area(): void
    {
        $area = Area::create(['name' => '天津市']);

        $response = $this->actingAsAdmin()->getJson("/admin/areas/{$area->id}");
        $response->assertOk()
            ->assertJsonPath('id', $area->id)
            ->assertJsonPath('name', '天津市');
    }

    #[Test]
    #[TestDox('获取不存在的地区返回 404')]
    public function view_nonexistent_area_returns_404(): void
    {
        $response = $this->actingAsAdmin()->getJson('/admin/areas/99999');
        $response->assertNotFound();
    }

    #[Test]
    #[TestDox('更新地区成功')]
    public function admin_can_update_area(): void
    {
        $area = Area::create(['name' => '原名称']);

        $response = $this->actingAsAdmin()->putJson("/admin/areas/{$area->id}", [
            'name' => '新名称',
            'sort' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('name', '新名称')
            ->assertJsonPath('sort', 5);

        $this->assertDatabaseHas('areas', [
            'id' => $area->id,
            'name' => '新名称',
            'sort' => 5,
        ]);
    }

    #[Test]
    #[TestDox('更新地区时不能将自身设为父级返回 422')]
    public function update_area_cannot_set_self_as_parent(): void
    {
        $area = Area::create(['name' => '自引用测试']);

        $response = $this->actingAsAdmin()->putJson("/admin/areas/{$area->id}", [
            'name' => '自引用测试',
            'parent_id' => $area->id,
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    #[TestDox('更新地区时不能将后代设为父级返回 422')]
    public function update_area_cannot_set_descendant_as_parent(): void
    {
        $parent = Area::create(['name' => '父级']);
        $child = Area::create(['name' => '子级', 'parent_id' => $parent->id]);

        $response = $this->actingAsAdmin()->putJson("/admin/areas/{$parent->id}", [
            'name' => '父级',
            'parent_id' => $child->id,
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    #[TestDox('删除无子地区的地区返回 204')]
    public function admin_can_delete_area_without_children(): void
    {
        $area = Area::create(['name' => '待删除']);

        $response = $this->actingAsAdmin()->deleteJson("/admin/areas/{$area->id}");
        $response->assertNoContent();

        $this->assertDatabaseMissing('areas', ['id' => $area->id]);
    }

    #[Test]
    #[TestDox('有子地区时不可删除返回 400')]
    public function cannot_delete_area_with_children(): void
    {
        $parent = Area::create(['name' => '有子地区']);
        Area::create(['name' => '子地区', 'parent_id' => $parent->id]);

        $response = $this->actingAsAdmin()->deleteJson("/admin/areas/{$parent->id}");
        $response->assertBadRequest();
    }

    #[Test]
    #[TestDox('地区排序按 sort 和 id 排列')]
    public function areas_are_ordered_by_sort_and_id(): void
    {
        Area::create(['name' => '丙', 'sort' => 3]);
        Area::create(['name' => '甲', 'sort' => 1]);
        Area::create(['name' => '乙', 'sort' => 2]);

        $response = $this->actingAsAdmin()->getJson('/admin/areas');
        $response->assertOk()
            ->assertJsonPath('0.name', '甲')
            ->assertJsonPath('1.name', '乙')
            ->assertJsonPath('2.name', '丙');
    }

    #[Test]
    #[TestDox('未登录不能创建地区')]
    public function guest_cannot_create_area(): void
    {
        $this->postJson('/admin/areas', ['name' => '测试'])->assertUnauthorized();
    }
}
