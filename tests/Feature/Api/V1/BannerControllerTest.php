<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\LinkType;
use App\Http\Controllers\Api\V1\BannerController;
use App\Models\Operation\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 前台公开 Banner / 轮播图接口功能测试
 */
#[CoversClass(BannerController::class)]
#[Group('api')]
#[Group('banner')]
class BannerControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('未登录用户可访问指定广告位 Banner 列表')]
    public function guest_can_list_active_banners(): void
    {
        Banner::factory()->create(['position' => 'home_top', 'title' => '首页顶部']);

        $response = $this->getJson('/api/v1/banners?position=home_top');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', '首页顶部');
    }

    #[Test]
    #[TestDox('响应中只返回公开字段（不含 position_label 等后台字段）')]
    public function response_only_contains_public_fields(): void
    {
        Banner::factory()->create([
            'position' => 'home_top',
            'title' => '公开 Banner',
            'image' => 'https://example.com/img.jpg',
            'link_type' => LinkType::URL->value,
            'link_value' => 'https://example.com',
        ]);

        $response = $this->getJson('/api/v1/banners?position=home_top');
        $data = $response->json('0');

        // 公开字段
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('image', $data);
        $this->assertArrayHasKey('link_type', $data);
        $this->assertArrayHasKey('link_value', $data);
        $this->assertArrayHasKey('sort', $data);

        // 后台字段不应出现
        $this->assertArrayNotHasKey('position', $data);
        $this->assertArrayNotHasKey('position_label', $data);
        $this->assertArrayNotHasKey('link_type_label', $data);
        $this->assertArrayNotHasKey('status', $data);
        $this->assertArrayNotHasKey('start_at', $data);
        $this->assertArrayNotHasKey('end_at', $data);
        $this->assertArrayNotHasKey('created_at', $data);
        $this->assertArrayNotHasKey('updated_at', $data);
    }

    #[Test]
    #[TestDox('缺少 position 参数返回 422')]
    public function position_is_required(): void
    {
        $this->getJson('/api/v1/banners')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['position']);
    }

    #[Test]
    #[TestDox('position 超过 64 字符返回 422')]
    public function position_max_length_is_validated(): void
    {
        $this->getJson('/api/v1/banners?position='.str_repeat('a', 65))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['position']);
    }

    #[Test]
    #[TestDox('不同广告位的 Banner 互不影响')]
    public function only_banners_of_requested_position_are_returned(): void
    {
        Banner::factory()->create(['position' => 'home_top', 'title' => '顶部轮播']);
        Banner::factory()->create(['position' => 'launch', 'title' => '启动页广告']);
        Banner::factory()->create(['position' => 'user_center', 'title' => '用户中心']);

        $response = $this->getJson('/api/v1/banners?position=launch');

        $response->assertOk()->assertJsonCount(1);
        $this->assertSame('启动页广告', $response->json('0.title'));
    }

    #[Test]
    #[TestDox('未开始（start_at 在未来）的 Banner 不返回')]
    public function banners_not_yet_started_are_excluded(): void
    {
        Banner::factory()->create([
            'position' => 'home_top',
            'title' => '未开始的',
            'start_at' => now()->addDays(7),
            'end_at' => now()->addDays(30),
        ]);

        $this->getJson('/api/v1/banners?position=home_top')
            ->assertOk()
            ->assertJsonCount(0);
    }

    #[Test]
    #[TestDox('已结束（end_at 已过）的 Banner 不返回')]
    public function banners_already_ended_are_excluded(): void
    {
        Banner::factory()->create([
            'position' => 'home_top',
            'title' => '已过期的',
            'start_at' => now()->subDays(30),
            'end_at' => now()->subDays(1),
        ]);

        $this->getJson('/api/v1/banners?position=home_top')
            ->assertOk()
            ->assertJsonCount(0);
    }

    #[Test]
    #[TestDox('禁用（status=false）的 Banner 不返回')]
    public function disabled_banners_are_excluded(): void
    {
        Banner::factory()->disabled()->create([
            'position' => 'home_top',
            'title' => '被禁用的',
        ]);

        $this->getJson('/api/v1/banners?position=home_top')
            ->assertOk()
            ->assertJsonCount(0);
    }

    #[Test]
    #[TestDox('生效时间内的 Banner 被返回')]
    public function banners_in_valid_time_range_are_returned(): void
    {
        Banner::factory()->create([
            'position' => 'home_top',
            'title' => '生效中',
            'start_at' => now()->subDays(7),
            'end_at' => now()->addDays(7),
        ]);

        $this->getJson('/api/v1/banners?position=home_top')
            ->assertOk()
            ->assertJsonCount(1);
    }

    #[Test]
    #[TestDox('永久生效（无 start_at 和 end_at）的 Banner 被返回')]
    public function banners_without_time_limits_are_always_returned(): void
    {
        Banner::factory()->create([
            'position' => 'home_top',
            'title' => '永久生效',
            'start_at' => null,
            'end_at' => null,
        ]);

        $this->getJson('/api/v1/banners?position=home_top')
            ->assertOk()
            ->assertJsonCount(1);
    }

    #[Test]
    #[TestDox('按 sort 升序返回（sort 相同时按 id 倒序）')]
    public function results_are_ordered_by_sort_then_id_desc(): void
    {
        $b1 = Banner::factory()->create(['position' => 'home_top', 'sort' => 0]);
        $b2 = Banner::factory()->create(['position' => 'home_top', 'sort' => 1]);
        $b3 = Banner::factory()->create(['position' => 'home_top', 'sort' => 2]);

        $response = $this->getJson('/api/v1/banners?position=home_top');

        $ids = array_column($response->json(), 'id');
        $this->assertSame([$b1->id, $b2->id, $b3->id], $ids);
    }

    #[Test]
    #[TestDox('同 sort 时 id 大的排在前')]
    public function same_sort_orders_by_id_desc(): void
    {
        $first = Banner::factory()->create(['position' => 'home_top', 'sort' => 5]);
        $second = Banner::factory()->create(['position' => 'home_top', 'sort' => 5]);

        $response = $this->getJson('/api/v1/banners?position=home_top');

        $ids = array_column($response->json(), 'id');
        $this->assertSame([$second->id, $first->id], $ids);
    }

    #[Test]
    #[TestDox('当指定广告位无任何 Banner 时返回空数组')]
    public function returns_empty_array_when_no_banners(): void
    {
        $this->getJson('/api/v1/banners?position=empty_position')
            ->assertOk()
            ->assertJsonCount(0);
    }

    #[Test]
    #[TestDox('多种生效条件同时满足的混合场景过滤正确')]
    public function complex_filtering_scenario(): void
    {
        // 1. 有效：启用 + 永久
        Banner::factory()->create([
            'position' => 'home_top', 'title' => '有效-永久', 'sort' => 0,
            'start_at' => null, 'end_at' => null, 'status' => true,
        ]);
        // 2. 有效：启用 + 在时间区间内
        Banner::factory()->create([
            'position' => 'home_top', 'title' => '有效-区间内', 'sort' => 1,
            'start_at' => now()->subDay(), 'end_at' => now()->addDay(), 'status' => true,
        ]);
        // 3. 无效：禁用
        Banner::factory()->disabled()->create([
            'position' => 'home_top', 'title' => '无效-禁用', 'sort' => 2,
        ]);
        // 4. 无效：未开始
        Banner::factory()->create([
            'position' => 'home_top', 'title' => '无效-未开始', 'sort' => 3,
            'start_at' => now()->addDay(), 'end_at' => now()->addDays(7),
        ]);
        // 5. 无效：已结束
        Banner::factory()->create([
            'position' => 'home_top', 'title' => '无效-已结束', 'sort' => 4,
            'start_at' => now()->subDays(7), 'end_at' => now()->subDay(),
        ]);
        // 6. 无效：其他广告位
        Banner::factory()->create([
            'position' => 'launch', 'title' => '无效-其他位置', 'sort' => 5,
        ]);

        $response = $this->getJson('/api/v1/banners?position=home_top');

        $response->assertOk()->assertJsonCount(2);
        $titles = array_column($response->json(), 'title');
        $this->assertSame(['有效-永久', '有效-区间内'], $titles);
    }
}
