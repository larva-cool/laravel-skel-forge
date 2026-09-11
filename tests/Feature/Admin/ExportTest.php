<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Enums\WalletTradeType;
use App\Exports\Admin\AuditLogListExport;
use App\Exports\Admin\CommentListExport;
use App\Exports\Admin\FeedbackListExport;
use App\Exports\Admin\UserListExport;
use App\Exports\Admin\WalletTradeExport;
use App\Jobs\Export\PerformExportJob;
use App\Models\Admin\Admin;
use App\Models\Content\Comment;
use App\Models\Point\PointTrade;
use App\Models\System\AuditLog;
use App\Models\User;
use App\Support\WalletHelper;
use Database\Factories\FeedbackFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 后台数据导出功能测试
 */
#[Group('admin')]
#[Group('export')]
class ExportTest extends TestCase
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

    // ========== 通用：未登录 ==========

    #[Test]
    #[TestDox('未登录访问用户导出返回 401')]
    public function guest_cannot_export_users(): void
    {
        $this->getJson('/admin/users/export')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('未登录访问评论导出返回 401')]
    public function guest_cannot_export_comments(): void
    {
        $this->getJson('/admin/comments/export')->assertUnauthorized();
    }

    // ========== 用户列表导出 ==========

    #[Test]
    #[TestDox('小数据量用户导出走同步，返回 xlsx 文件流')]
    public function user_list_export_sync_returns_xlsx(): void
    {
        User::factory()->count(3)->create();

        $response = $this->actingAsAdmin()->get('/admin/users/export');

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'attachment; filename=users_',
            (string) $response->headers->get('content-disposition')
        );
    }

    #[Test]
    #[TestDox('用户列表导出按状态筛选并生成符合筛选条件的 xlsx')]
    public function user_list_export_filters_by_status(): void
    {
        User::factory()->count(2)->create(['status' => UserStatus::ACTIVE]);
        User::factory()->count(3)->create(['status' => UserStatus::FROZEN]);

        $response = $this->actingAsAdmin()->get('/admin/users/export?status=0');
        $response->assertOk();

        $this->assertXlsxRowCount($response->streamedContent(), 4); // 1 表头 + 3 冻结
    }

    // ========== 评论列表导出 ==========

    #[Test]
    #[TestDox('评论列表导出走同步，返回 xlsx 文件流')]
    public function comment_list_export_sync_returns_xlsx(): void
    {
        $author = User::factory()->create();
        $source = User::factory()->create();
        Comment::factory()->forSource($source)->count(2)->create(['user_id' => $author->id]);

        $response = $this->actingAsAdmin()->get('/admin/comments/export');
        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
    }

    // ========== 反馈列表导出 ==========

    #[Test]
    #[TestDox('反馈列表导出走同步，返回 xlsx 文件流')]
    public function feedback_list_export_sync_returns_xlsx(): void
    {
        FeedbackFactory::new()->count(2)->create();

        $response = $this->actingAsAdmin()->get('/admin/feedbacks/export');
        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
    }

    // ========== 审计日志导出 ==========

    #[Test]
    #[TestDox('审计日志导出走同步，返回 xlsx 文件流')]
    public function audit_log_export_sync_returns_xlsx(): void
    {
        AuditLog::factory()->count(2)->create();

        $response = $this->actingAsAdmin()->get('/admin/audit-logs/export');
        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
    }

    // ========== 用户积分/金币流水导出 ==========

    #[Test]
    #[TestDox('用户积分流水导出走同步，返回 xlsx 文件流')]
    public function point_trade_export_sync_returns_xlsx(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            PointTrade::create([
                'user_id' => $user->id,
                'points' => 10 + $i,
                'description' => "test {$i}",
                'type' => 'admin_recharge',
                'source_type' => $user->getMorphClass(),
                'source_id' => $user->id,
                'expired_at' => now()->addYear(),
            ]);
        }

        $response = $this->actingAsAdmin()->get("/admin/users/{$user->id}/point-trades/export");
        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            "user_{$user->id}_points_",
            (string) $response->headers->get('content-disposition')
        );
    }

    // ========== 钱包流水导出 ==========

    #[Test]
    #[TestDox('未登录访问钱包流水导出返回 401')]
    public function guest_cannot_export_wallet_trades(): void
    {
        $this->getJson('/admin/wallet/trades/export')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('钱包流水导出走同步，返回 xlsx 文件流')]
    public function wallet_trade_export_sync_returns_xlsx(): void
    {
        $user = User::factory()->create();
        WalletHelper::incr($user, 100, $user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::decr($user, 30, $user, WalletTradeType::PAYMENT, '消费');

        $response = $this->actingAsAdmin()->get('/admin/wallet/trades/export');

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'attachment; filename=wallet_trades_',
            (string) $response->headers->get('content-disposition')
        );
    }

    #[Test]
    #[TestDox('钱包流水导出按交易类型筛选')]
    public function wallet_trade_export_filters_by_type(): void
    {
        $user = User::factory()->create();
        WalletHelper::incr($user, 100, $user, WalletTradeType::RECHARGE, '充值');
        WalletHelper::decr($user, 10, $user, WalletTradeType::PAYMENT, '消费1');
        WalletHelper::decr($user, 20, $user, WalletTradeType::PAYMENT, '消费2');

        $response = $this->actingAsAdmin()->get('/admin/wallet/trades/export?type=payment');
        $response->assertOk();

        $this->assertXlsxRowCount($response->streamedContent(), 3); // 1 表头 + 2 消费
    }

    #[Test]
    #[TestDox('WalletTradeExport 将交易类型与状态枚举映射为中文标签')]
    public function wallet_trade_export_maps_enum_labels(): void
    {
        $user = User::factory()->create();
        $trade = WalletHelper::incr($user, 100, $user, WalletTradeType::ADMIN_RECHARGE, '后台充正');

        $export = new WalletTradeExport;
        $columns = (new \ReflectionMethod($export, 'columns'))->invoke($export);
        $typeIdx = array_search('type', array_keys($columns));
        $statusIdx = array_search('status', array_keys($columns));

        $mapped = (new \ReflectionMethod($export, 'map'))->invoke($export, $trade);

        $this->assertSame('后台充正', $mapped[$typeIdx]);
        $this->assertSame('已完成', $mapped[$statusIdx]);
    }

    // ========== 异步导出（大数据量） ==========

    #[Test]
    #[TestDox('用户列表超过同步阈值时返回 202 并派发异步任务')]
    public function user_list_export_async_when_exceeds_threshold(): void
    {
        Queue::fake();

        $this->seedUsersBeyondThreshold();

        $response = $this->actingAsAdmin()->get('/admin/users/export');
        $response->assertStatus(202);
        $response->assertJsonStructure(['message', 'file_name']);

        Queue::assertPushed(PerformExportJob::class, function (PerformExportJob $job) {
            return $job->exportClass === UserListExport::class
                && $job->adminId === $this->admin->id
                && $job->queue === 'exports';
        });
    }

    #[Test]
    #[TestDox('异步任务推入 exports 队列并保留完整导出参数')]
    public function async_job_carries_export_arguments(): void
    {
        Queue::fake();

        $this->seedUsersBeyondThreshold();

        $this->actingAsAdmin()->get('/admin/users/export?status=1&vip=0');

        Queue::assertPushed(PerformExportJob::class, function (PerformExportJob $job) {
            $args = $job->exportArgs[0];

            return ($args['status'] ?? null) === '1'
                && ($args['vip'] ?? null) === '0';
        });
    }

    // ========== Export 类契约 ==========

    #[Test]
    #[TestDox('CommentListExport 复用 BaseExport 的列定义')]
    public function comment_export_columns_defined(): void
    {
        $export = new CommentListExport;
        $columns = (new \ReflectionMethod($export, 'columns'))->invoke($export);

        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('content', $columns);
        $this->assertArrayHasKey('created_at', $columns);
    }

    #[Test]
    #[TestDox('FeedbackListExport 正确映射状态与优先级枚举')]
    public function feedback_export_status_mapping(): void
    {
        $feedback = FeedbackFactory::new()->create([
            'status' => \App\Enums\FeedbackStatus::PENDING,
            'priority' => \App\Enums\FeedbackPriority::URGENT,
        ]);

        $export = new FeedbackListExport;
        $columns = (new \ReflectionMethod($export, 'columns'))->invoke($export);
        $statusIdx = array_search('status', array_keys($columns));
        $priorityIdx = array_search('priority', array_keys($columns));

        $mapped = (new \ReflectionMethod($export, 'map'))->invoke($export, $feedback);

        $this->assertSame('待处理', $mapped[$statusIdx]);
        $this->assertSame('紧急', $mapped[$priorityIdx]);
    }

    #[Test]
    #[TestDox('AuditLogListExport 严格按筛选条件过滤')]
    public function audit_log_export_filters_by_status_code(): void
    {
        AuditLog::factory()->count(2)->create(['status_code' => 200]);
        AuditLog::factory()->count(3)->failed()->create();

        $export = new AuditLogListExport(['status_code' => 422]);
        $query = $export->query();
        $this->assertSame(3, (clone $query)->getQuery()->getCountForPagination());
    }

    #[Test]
    #[TestDox('AbstractExport 自动追加主键作为唯一排序，避免 chunk 漏行')]
    public function base_export_appends_primary_key_for_stable_ordering(): void
    {
        User::factory()->count(2)->create();
        $export = new UserListExport;
        $query = $export->query();
        $sql = $query->toSql();
        // SQL 中应包含 `id` 排序
        $this->assertStringContainsString('"id"', $sql);
    }

    // ========== 异步导出下载地址解析 ==========

    #[Test]
    #[TestDox('公开磁盘的导出通知返回永久下载地址')]
    public function completed_notification_uses_public_url_for_public_disk(): void
    {
        $notification = new \App\Notifications\Export\ExportCompletedNotification(
            fileName: 'users.xlsx',
            relativePath: 'exports/admin-1/users.xlsx',
            disk: 'public',
        );

        $data = $notification->toDatabase($this->admin)->data;

        $this->assertSame('export.completed', $data['type']);
        $this->assertSame('public', $data['disk']);
        $this->assertStringContainsString('exports/admin-1/users.xlsx', (string) $data['url']);
        $this->assertStringContainsString('点击下载', $data['body']);
    }

    #[Test]
    #[TestDox('私有磁盘的导出通知签发临时下载地址')]
    public function completed_notification_signs_temporary_url_for_private_disk(): void
    {
        \Illuminate\Support\Facades\Storage::fake('private_export');

        $notification = new \App\Notifications\Export\ExportCompletedNotification(
            fileName: 'users.xlsx',
            relativePath: 'exports/admin-1/users.xlsx',
            disk: 'private_export',
        );

        $data = $notification->toDatabase($this->admin)->data;

        // Storage::fake 支持 temporaryUrl，应签出带签名的地址
        $this->assertNotNull($data['url']);
        $this->assertSame('private_export', $data['disk']);
    }

    #[Test]
    #[TestDox('磁盘既不支持永久地址也不支持临时地址时提示改走其他途径')]
    public function completed_notification_falls_back_when_url_unsupported(): void
    {
        config(['filesystems.disks.no_url_disk' => [
            'driver' => 'local',
            'root' => storage_path('app/no-url'),
            'throw' => false,
        ]]);

        $notification = new \App\Notifications\Export\ExportCompletedNotification(
            fileName: 'users.xlsx',
            relativePath: 'exports/admin-1/users.xlsx',
            disk: 'no_url_disk',
        );

        $data = $notification->toDatabase($this->admin)->data;

        $this->assertNull($data['url']);
        $this->assertStringContainsString('请前往附件管理下载', $data['body']);
    }

    // ========== 工具方法 ==========

    /**
     * 批量插入超过同步阈值的用户（直接走 insert，避免 factory 的模型与事件开销）
     */
    private function seedUsersBeyondThreshold(): void
    {
        $now = now()->toDateTimeString();
        $rows = [];
        for ($i = 0; $i < UserListExport::SYNC_THRESHOLD + 5; $i++) {
            $rows[] = [
                'username' => "bulk_user_{$i}",
                'name' => "批量用户{$i}",
                'status' => UserStatus::ACTIVE->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            \Illuminate\Support\Facades\DB::table('users')->insert($chunk);
        }
    }

    /**
     * 简单解析 xlsx 顶层 sheet 的总行数
     */
    private function assertXlsxRowCount(string $binary, int $expected): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        file_put_contents($tmp, $binary);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true, 'xlsx 不是有效的 zip 格式');
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertIsString($xml, 'xlsx 缺少 sheet1.xml');
        $rowCount = preg_match_all('/<row\b/i', $xml);
        $this->assertSame($expected, $rowCount, "xlsx 行数应为 {$expected}，实际 {$rowCount}");
    }
}
