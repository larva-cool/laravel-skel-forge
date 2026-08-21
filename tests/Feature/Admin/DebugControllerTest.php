<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\DebugController;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台调试面板接口测试
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[CoversClass(DebugController::class)]
#[Group('admin')]
#[Group('debug')]
class DebugControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 超级管理员
     */
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['telescope.enabled' => true]);

        $this->admin = Admin::query()->where('username', 'admin')->first();
    }

    /**
     * 以超级管理员身份发起请求
     */
    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * 创建一条调试记录
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createEntry(array $attributes = []): EntryModel
    {
        return EntryModel::factory()->create(array_merge([
            'type' => EntryType::REQUEST,
            'content' => ['uri' => '/admin/ping', 'method' => 'GET'],
        ], $attributes));
    }

    /**
     * 需要登录的接口清单
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function endpointProvider(): array
    {
        return [
            '条目列表' => ['get', '/admin/debug/entries?type=request'],
            '监控标签' => ['get', '/admin/debug/tags'],
            '新增监控标签' => ['post', '/admin/debug/tags'],
            '移除监控标签' => ['delete', '/admin/debug/tags'],
            '切换记录开关' => ['post', '/admin/debug/toggle-recording'],
            '清空记录' => ['delete', '/admin/debug/entries'],
        ];
    }

    #[Test]
    #[TestDox('未登录访问调试面板接口返回 401')]
    #[DataProvider('endpointProvider')]
    public function guest_cannot_access_debug_endpoints(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    #[Test]
    #[TestDox('超级管理员可获取条目列表并返回游标信息')]
    public function super_admin_can_list_entries(): void
    {
        $entry = $this->createEntry(['sequence' => 100]);

        $this->actingAsAdmin()
            ->getJson('/admin/debug/entries?type='.EntryType::REQUEST)
            ->assertOk()
            ->assertJsonStructure([
                'type',
                'status',
                'entries' => [['id', 'sequence', 'batch_id', 'type', 'content', 'tags', 'created_at']],
                'next_before',
            ])
            ->assertJsonPath('type', EntryType::REQUEST)
            ->assertJsonPath('status', 'enabled')
            ->assertJsonPath('entries.0.id', $entry->uuid)
            ->assertJsonPath('next_before', 100);
    }

    #[Test]
    #[TestDox('条目列表支持 take 与 before 游标翻页')]
    public function entries_support_cursor_pagination(): void
    {
        $this->createEntry(['sequence' => 10]);
        $second = $this->createEntry(['sequence' => 9]);

        $this->actingAsAdmin()
            ->getJson('/admin/debug/entries?type='.EntryType::REQUEST.'&before=10&take=1')
            ->assertOk()
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.id', $second->uuid)
            ->assertJsonPath('next_before', 9);
    }

    #[Test]
    #[TestDox('Telescope 总开关关闭时状态为 disabled')]
    public function status_is_disabled_when_telescope_disabled(): void
    {
        config(['telescope.enabled' => false]);

        $this->actingAsAdmin()
            ->getJson('/admin/debug/entries?type='.EntryType::REQUEST)
            ->assertOk()
            ->assertJsonPath('status', 'disabled');
    }

    #[Test]
    #[TestDox('对应 Watcher 未启用时状态为 off')]
    public function status_is_off_when_watcher_disabled(): void
    {
        config(['telescope.watchers.'.\Laravel\Telescope\Watchers\RequestWatcher::class => ['enabled' => false]]);

        $this->actingAsAdmin()
            ->getJson('/admin/debug/entries?type='.EntryType::REQUEST)
            ->assertOk()
            ->assertJsonPath('status', 'off');
    }

    #[Test]
    #[TestDox('缺少或非法条目类型返回 422')]
    public function invalid_type_is_rejected(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/debug/entries')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->actingAsAdmin()
            ->getJson('/admin/debug/entries?type=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    #[Test]
    #[TestDox('可获取条目详情及同批次条目')]
    public function super_admin_can_show_entry(): void
    {
        $entry = $this->createEntry();
        $this->createEntry(['batch_id' => $entry->batch_id]);

        $this->actingAsAdmin()
            ->getJson('/admin/debug/entries/'.$entry->uuid)
            ->assertOk()
            ->assertJsonStructure(['entry' => ['id', 'type', 'content'], 'batch'])
            ->assertJsonPath('entry.id', $entry->uuid)
            ->assertJsonCount(2, 'batch');
    }

    #[Test]
    #[TestDox('条目不存在时返回 404')]
    public function missing_entry_returns_not_found(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/debug/entries/'.fake()->uuid())
            ->assertNotFound();
    }

    #[Test]
    #[TestDox('可将异常条目标记为已解决')]
    public function exception_entry_can_be_resolved(): void
    {
        $entry = $this->createEntry([
            'type' => EntryType::EXCEPTION,
            'content' => ['class' => 'RuntimeException', 'message' => 'boom'],
        ]);

        $this->actingAsAdmin()
            ->putJson('/admin/debug/entries/'.$entry->uuid.'/resolve')
            ->assertOk()
            ->assertJsonStructure(['message', 'entry'])
            ->assertJsonPath('entry.id', $entry->uuid);

        $this->assertArrayHasKey('resolved_at', $entry->fresh()->content);
    }

    #[Test]
    #[TestDox('非异常条目标记已解决返回 422')]
    public function non_exception_entry_cannot_be_resolved(): void
    {
        $entry = $this->createEntry();

        $this->actingAsAdmin()
            ->putJson('/admin/debug/entries/'.$entry->uuid.'/resolve')
            ->assertUnprocessable();
    }

    #[Test]
    #[TestDox('可新增、查询并移除监控标签')]
    public function monitored_tags_can_be_managed(): void
    {
        $this->actingAsAdmin()
            ->getJson('/admin/debug/tags')
            ->assertOk()
            ->assertJsonPath('tags', []);

        $this->actingAsAdmin()
            ->postJson('/admin/debug/tags', ['tag' => 'admin:1'])
            ->assertOk()
            ->assertJsonPath('tags', ['admin:1']);

        $this->actingAsAdmin()
            ->getJson('/admin/debug/tags')
            ->assertOk()
            ->assertJsonPath('tags', ['admin:1']);

        $this->actingAsAdmin()
            ->deleteJson('/admin/debug/tags', ['tag' => 'admin:1'])
            ->assertOk()
            ->assertJsonPath('tags', []);
    }

    #[Test]
    #[TestDox('监控标签缺少 tag 参数返回 422')]
    public function monitor_requires_tag(): void
    {
        $this->actingAsAdmin()
            ->postJson('/admin/debug/tags')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag');
    }

    #[Test]
    #[TestDox('可切换记录开关并影响采集状态')]
    public function recording_can_be_toggled(): void
    {
        $this->actingAsAdmin()
            ->postJson('/admin/debug/toggle-recording')
            ->assertOk()
            ->assertJsonPath('paused', true);

        $this->actingAsAdmin()
            ->getJson('/admin/debug/entries?type='.EntryType::REQUEST)
            ->assertOk()
            ->assertJsonPath('status', 'paused');

        $this->actingAsAdmin()
            ->postJson('/admin/debug/toggle-recording')
            ->assertOk()
            ->assertJsonPath('paused', false);
    }

    #[Test]
    #[TestDox('可清空全部调试记录')]
    public function entries_can_be_cleared(): void
    {
        $this->createEntry();

        $this->actingAsAdmin()
            ->deleteJson('/admin/debug/entries')
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertSame(0, EntryModel::query()->count());
    }
}
