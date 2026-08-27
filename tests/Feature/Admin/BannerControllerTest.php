<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\LinkType;
use App\Http\Controllers\Admin\BannerController;
use App\Models\Admin\Admin;
use App\Models\Operation\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台 Banner / 轮播图管理控制器功能测试
 */
#[CoversClass(BannerController::class)]
#[Group('admin')]
#[Group('banner')]
class BannerControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // seed_initial_admin_data 迁移已预置超级管理员
        $this->admin = Admin::query()->where('username', 'admin')->firstOrFail();
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 默认有效 payload
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'position' => 'home_top',
            'title' => '首页顶部轮播',
            'image' => 'https://example.com/banners/home-top.jpg',
            'link_type' => LinkType::URL->value,
            'link_value' => 'https://example.com/promo',
            'sort' => 10,
            'status' => true,
        ], $overrides);
    }

    #[Test]
    #[TestDox('未登录访问 Banner 列表返回 401')]
    public function guest_cannot_list_banners(): void
    {
        $this->getJson('/admin/banners')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('管理员可获取 Banner 列表分页数据')]
    public function admin_can_list_banners(): void
    {
        Banner::factory()->count(3)->create(['position' => 'home_top']);

        $response = $this->actingAsAdmin()->getJson('/admin/banners');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按广告位筛选 Banner')]
    public function admin_can_filter_by_position(): void
    {
        Banner::factory()->create(['position' => 'home_top']);
        Banner::factory()->create(['position' => 'home_banner']);
        Banner::factory()->create(['position' => 'home_banner']);

        $response = $this->actingAsAdmin()->getJson('/admin/banners?position=home_banner');

        $response->assertOk()->assertJsonPath('meta.total', 2);
    }

    #[Test]
    #[TestDox('按状态筛选 Banner')]
    public function admin_can_filter_by_status(): void
    {
        Banner::factory()->count(2)->create(['status' => true]);
        Banner::factory()->count(3)->disabled()->create();

        $response = $this->actingAsAdmin()->getJson('/admin/banners?status=0');

        $response->assertOk()->assertJsonPath('meta.total', 3);
    }

    #[Test]
    #[TestDox('按关键词搜索 Banner 标题')]
    public function admin_can_search_by_keyword(): void
    {
        Banner::factory()->create(['title' => '双十一大促']);
        Banner::factory()->create(['title' => '普通 Banner']);

        $response = $this->actingAsAdmin()->getJson('/admin/banners?keyword='.rawurlencode('双十一'));

        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[Test]
    #[TestDox('列表返回数据包含 position_label 字段')]
    public function list_response_includes_position_label(): void
    {
        Banner::factory()->create(['position' => 'home_top']);

        $response = $this->actingAsAdmin()->getJson('/admin/banners');
        $data = $response->json('data.0');

        $this->assertArrayHasKey('position_label', $data);
        $this->assertArrayHasKey('link_type_label', $data);
    }

    #[Test]
    #[TestDox('管理员可创建 Banner')]
    public function admin_can_create_banner(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/banners', $this->validPayload([
            'title' => '新 Banner',
        ]));

        $response->assertCreated()
            ->assertJsonPath('title', '新 Banner')
            ->assertJsonPath('position', 'home_top')
            ->assertJsonPath('link_type', LinkType::URL->value)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('banners', [
            'title' => '新 Banner',
            'position' => 'home_top',
        ]);
    }

    #[Test]
    #[TestDox('创建 Banner 时 link_type 为 none 时 link_value 允许为空')]
    public function admin_can_create_banner_with_no_link(): void
    {
        $response = $this->actingAsAdmin()->postJson('/admin/banners', $this->validPayload([
            'link_type' => LinkType::NONE->value,
            'link_value' => null,
        ]));

        $response->assertCreated()
            ->assertJsonPath('link_type', LinkType::NONE->value)
            ->assertJsonPath('link_value', null);
    }

    #[Test]
    #[TestDox('创建 Banner 时缺少 position 字段返回 422')]
    public function create_requires_position(): void
    {
        $payload = $this->validPayload();
        unset($payload['position']);

        $this->actingAsAdmin()->postJson('/admin/banners', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['position']);
    }

    #[Test]
    #[TestDox('创建 Banner 时缺少 image 字段返回 422')]
    public function create_requires_image(): void
    {
        $payload = $this->validPayload();
        unset($payload['image']);

        $this->actingAsAdmin()->postJson('/admin/banners', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    #[Test]
    #[TestDox('创建 Banner 时 link_type 非法返回 422')]
    public function create_validates_link_type_enum(): void
    {
        $this->actingAsAdmin()->postJson('/admin/banners', $this->validPayload([
            'link_type' => 'invalid_type',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['link_type']);
    }

    #[Test]
    #[TestDox('创建 Banner 时 end_at 早于 start_at 返回 422')]
    public function create_validates_end_after_start(): void
    {
        $this->actingAsAdmin()->postJson('/admin/banners', $this->validPayload([
            'start_at' => '2026-12-31 00:00:00',
            'end_at' => '2026-01-01 00:00:00',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['end_at']);
    }

    #[Test]
    #[TestDox('管理员可查看 Banner 详情')]
    public function admin_can_view_banner_detail(): void
    {
        $banner = Banner::factory()->create(['title' => '详情测试']);

        $response = $this->actingAsAdmin()->getJson("/admin/banners/{$banner->id}");

        $response->assertOk()
            ->assertJsonPath('id', $banner->id)
            ->assertJsonPath('title', '详情测试');
    }

    #[Test]
    #[TestDox('查看不存在 Banner 返回 404')]
    public function view_missing_banner_returns_404(): void
    {
        $this->actingAsAdmin()->getJson('/admin/banners/999999')
            ->assertNotFound();
    }

    #[Test]
    #[TestDox('管理员可更新 Banner')]
    public function admin_can_update_banner(): void
    {
        $banner = Banner::factory()->create(['title' => '原标题']);

        $response = $this->actingAsAdmin()->putJson("/admin/banners/{$banner->id}", $this->validPayload([
            'title' => '更新后的标题',
            'sort' => 99,
            'status' => false,
        ]));

        $response->assertOk()
            ->assertJsonPath('title', '更新后的标题')
            ->assertJsonPath('sort', 99)
            ->assertJsonPath('status', false);

        $banner->refresh();
        $this->assertSame('更新后的标题', $banner->title);
        $this->assertFalse($banner->status);
    }

    #[Test]
    #[TestDox('更新时校验失败返回 422 且不改数据库')]
    public function update_with_invalid_data_does_not_modify_record(): void
    {
        $banner = Banner::factory()->create(['title' => '原标题']);

        $this->actingAsAdmin()->putJson("/admin/banners/{$banner->id}", [
            'image' => '', // 必填，不能为空
        ])->assertStatus(422);

        $banner->refresh();
        $this->assertSame('原标题', $banner->title);
    }

    #[Test]
    #[TestDox('管理员可删除 Banner')]
    public function admin_can_delete_banner(): void
    {
        $banner = Banner::factory()->create();

        $this->actingAsAdmin()->deleteJson("/admin/banners/{$banner->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
    }

    #[Test]
    #[TestDox('切换 Banner 状态：启用 → 禁用')]
    public function admin_can_toggle_banner_status_from_active_to_disabled(): void
    {
        $banner = Banner::factory()->create(['status' => true]);

        $response = $this->actingAsAdmin()->putJson("/admin/banners/{$banner->id}/toggle-status");

        $response->assertOk()->assertJsonPath('status', false);
        $this->assertFalse($banner->fresh()->status);
    }

    #[Test]
    #[TestDox('切换 Banner 状态：禁用 → 启用')]
    public function admin_can_toggle_banner_status_from_disabled_to_active(): void
    {
        $banner = Banner::factory()->disabled()->create();

        $response = $this->actingAsAdmin()->putJson("/admin/banners/{$banner->id}/toggle-status");

        $response->assertOk()->assertJsonPath('status', true);
        $this->assertTrue($banner->fresh()->status);
    }

    #[Test]
    #[TestDox('拖拽排序：按 ids 数组顺序写入 sort 字段')]
    public function admin_can_sort_banners_by_id_order(): void
    {
        $a = Banner::factory()->create(['position' => 'home_top', 'sort' => 0]);
        $b = Banner::factory()->create(['position' => 'home_top', 'sort' => 1]);
        $c = Banner::factory()->create(['position' => 'home_top', 'sort' => 2]);

        // 期望最终顺序为 c → a → b（即 ids[0] 写 sort=0，ids[1] 写 sort=1，ids[2] 写 sort=2）
        $this->actingAsAdmin()->postJson('/admin/banners/sort', [
            'ids' => [$c->id, $a->id, $b->id],
        ])->assertNoContent();

        $this->assertSame(1, $a->fresh()->sort);
        $this->assertSame(2, $b->fresh()->sort);
        $this->assertSame(0, $c->fresh()->sort);
    }

    #[Test]
    #[TestDox('拖拽排序：仅同广告位之间的顺序生效')]
    public function sort_only_updates_provided_ids(): void
    {
        $homeTop = Banner::factory()->create(['position' => 'home_top', 'sort' => 5]);
        $other = Banner::factory()->create(['position' => 'home_banner', 'sort' => 9]);

        $this->actingAsAdmin()->postJson('/admin/banners/sort', [
            'ids' => [$homeTop->id],
        ])->assertNoContent();

        $this->assertSame(0, $homeTop->fresh()->sort);
        // 不在 ids 数组内的 Banner 保持 sort 不变
        $this->assertSame(9, $other->fresh()->sort);
    }

    #[Test]
    #[TestDox('拖拽排序：ids 为空数组返回 422')]
    public function sort_requires_non_empty_ids(): void
    {
        $this->actingAsAdmin()->postJson('/admin/banners/sort', [
            'ids' => [],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);
    }

    #[Test]
    #[TestDox('拖拽排序：传入不存在的 id 返回 422')]
    public function sort_validates_id_existence(): void
    {
        $banner = Banner::factory()->create();

        $this->actingAsAdmin()->postJson('/admin/banners/sort', [
            'ids' => [$banner->id, 99999999],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['ids.1']);
    }

    #[Test]
    #[TestDox('列表按 sort 升序、id 倒序返回')]
    public function list_orders_by_sort_asc_then_id_desc(): void
    {
        $first = Banner::factory()->create(['position' => 'home_top', 'sort' => 0]);
        $second = Banner::factory()->create(['position' => 'home_top', 'sort' => 1]);
        $third = Banner::factory()->create(['position' => 'home_top', 'sort' => 2]);

        $response = $this->actingAsAdmin()->getJson('/admin/banners?position=home_top');

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$first->id, $second->id, $third->id], $ids);
    }
}
