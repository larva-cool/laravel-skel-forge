<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Enums\SocialProvider;
use App\Enums\WalletTradeStatus;
use App\Enums\WalletTradeType;
use App\Models\Content\Collection;
use App\Models\Content\Comment;
use App\Models\Content\Follow;
use App\Models\Content\Like;
use App\Models\Content\Report;
use App\Models\Content\Visit;
use App\Models\Invite\InviteCode;
use App\Models\Invite\InviteRecord;
use App\Models\System\Feedback;
use App\Models\System\LoginHistory;
use App\Models\System\Social;
use App\Models\User;
use App\Models\User\Address;
use App\Models\User\UserExtra;
use App\Models\User\UserProfile;
use App\Models\Wallet\WalletTrade;
use App\Models\Wallet\WithdrawAccount;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 互动行为（点赞/收藏/关注）的测试目标模型
 *
 * @property int $id
 * @property int $like_count
 * @property int $collection_count
 * @property int $follow_count
 */
class ObserverInteractableStub extends Model
{
    protected $table = 'observer_interactable_stubs';

    protected $guarded = [];
}

/**
 * UserObserver 单元测试
 */
#[CoversClass(UserObserver::class)]
#[Group('observers')]
class UserObserverTest extends TestCase
{
    use RefreshDatabase;

    private UserObserver $observer;

    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap(['observer_interactable_stub' => ObserverInteractableStub::class]);

        Schema::create('observer_interactable_stubs', function ($table): void {
            $table->id();
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('collection_count')->default(0);
            $table->unsignedInteger('follow_count')->default(0);
            $table->timestamps();
        });

        $this->observer = new UserObserver;
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('observer_interactable_stubs');

        parent::tearDown();
    }

    #[Test]
    #[TestDox('created 事件创建 UserExtra、UserProfile 和邀请码记录')]
    public function created_creates_user_extra_profile_and_invite_code(): void
    {
        $user = User::factory()->create();

        // UserObserver 已通过 Laravel 自动注册，验证 extra、profile 和邀请码已创建
        $this->assertDatabaseHas('user_extras', ['user_id' => $user->id]);
        $this->assertInstanceOf(UserExtra::class, $user->extra);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id]);
        $this->assertInstanceOf(UserProfile::class, $user->profile);
        $this->assertDatabaseHas('invite_codes', [
            'owner_id' => $user->id,
            'owner_type' => $user->getMorphClass(),
        ]);
        $this->assertInstanceOf(InviteCode::class, $user->inviteCode);
    }

    #[Test]
    #[TestDox('created 手动调用也创建 UserExtra、UserProfile 和邀请码')]
    public function created_manual_call_creates_extra_profile_and_invite_code(): void
    {
        $user = User::factory()->create();
        // 删除 extra、profile 和邀请码后手动调用
        $user->extra?->delete();
        $user->profile?->delete();
        $user->inviteCode?->delete();

        $this->observer->created($user);

        $this->assertDatabaseHas('user_extras', ['user_id' => $user->id]);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseHas('invite_codes', [
            'owner_id' => $user->id,
            'owner_type' => $user->getMorphClass(),
        ]);
    }

    #[Test]
    #[TestDox('forceDeleted 删除 UserExtra、UserProfile、邀请码、登录历史和社交账号记录')]
    public function force_deleted_deletes_user_extra_profile_invite_code_login_histories_and_socials(): void
    {
        $user = User::factory()->create();
        $extra = $user->extra;
        $profile = $user->profile;

        // 创建一些登录历史记录
        LoginHistory::factory()->count(3)->create([
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
        ]);

        // 创建一些社交账号记录
        Social::create([
            'user_id' => $user->id,
            'user_type' => $user->getMorphClass(),
            'provider' => SocialProvider::WECHAT_MP,
            'openid' => 'openid123',
            'unionid' => 'unionid123',
            'access_token' => 'token123',
            'refresh_token' => 'refresh123',
            'expiry_at' => now()->addDays(30),
        ]);

        $this->assertNotNull($extra);
        $this->assertNotNull($profile);
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id]);
        $this->assertDatabaseHas('socials', ['user_id' => $user->id]);

        $this->observer->forceDeleted($user);

        $this->assertDatabaseMissing('user_extras', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('invite_codes', [
            'owner_id' => $user->id,
            'owner_type' => $user->getMorphClass(),
        ]);
        $this->assertDatabaseMissing('login_histories', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('socials', ['user_id' => $user->id]);
    }

    #[Test]
    #[TestDox('forceDeleted 清理邀请关系、互动行为、举报、个人数据等全部关联信息')]
    public function force_deleted_cleans_up_all_user_related_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // 邀请关系（作为邀请人与被邀请人）
        InviteRecord::factory()->forInviter($user)->forInvitee($otherUser)->create();
        InviteRecord::factory()->forInviter($otherUser)->forInvitee($user)->create();

        // 提现收款账户（含已软删的）
        WithdrawAccount::factory()->forUser($user)->create();
        $trashedAccount = WithdrawAccount::factory()->forUser($user)->create();
        $trashedAccount->delete();

        // 评论及他人回复
        $comment = Comment::factory()->forSource($otherUser)->create(['user_id' => $user->id]);
        $reply = Comment::factory()->forSource($comment)->create(['user_id' => $otherUser->id]);

        // 互动行为
        $otherComment = Comment::factory()->forSource($otherUser)->create(['user_id' => $otherUser->id]);
        $interactable = ObserverInteractableStub::create([]);
        Like::factory()->forSource($interactable)->create(['user_id' => $user->id]);
        Collection::factory()->forSource($interactable)->create(['user_id' => $user->id]);
        Follow::factory()->forSource($interactable)->create(['user_id' => $user->id]);
        Visit::factory()->forSource($otherUser)->create(['user_id' => $user->id]);

        // 举报（作为举报人与被举报对象）
        Report::create([
            'user_id' => $user->id,
            'reportable_id' => $otherUser->id,
            'reportable_type' => $otherUser->getMorphClass(),
            'category' => 'other',
            'reason' => '测试举报',
        ]);
        Report::create([
            'user_id' => $otherUser->id,
            'reportable_id' => $user->id,
            'reportable_type' => $user->getMorphClass(),
            'category' => 'other',
            'reason' => '测试举报',
        ]);

        // 收货地址（含已软删的）
        Address::factory()->create(['user_id' => $user->id]);
        $trashedAddress = Address::factory()->create(['user_id' => $user->id]);
        $trashedAddress->delete();

        // 反馈
        Feedback::factory()->create(['user_id' => $user->id]);

        // 站内通知
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => 'test']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 会话
        DB::table('sessions')->insert([
            'id' => (string) Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('test-payload'),
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->observer->forceDeleted($user);

        $this->assertDatabaseMissing('invite_records', ['inviter_id' => $user->id]);
        $this->assertDatabaseMissing('invite_records', ['invitee_id' => $user->id]);
        $this->assertDatabaseMissing('withdraw_accounts', ['owner_id' => $user->id]);
        $this->assertSame(0, Comment::forUser($user)->count());
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        $this->assertDatabaseMissing('comments', ['id' => $reply->id]); // 回复被递归删除
        $this->assertDatabaseHas('comments', ['id' => $otherComment->id]); // 他人独立评论不受影响
        $this->assertSame(0, Like::forUser($user)->count());
        $this->assertSame(0, Collection::forUser($user)->count());
        $this->assertSame(0, Follow::forUser($user)->count());
        $this->assertSame(0, Visit::forUser($user)->count());
        $this->assertDatabaseMissing('reports', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('reports', [
            'reportable_id' => $user->id,
            'reportable_type' => $user->getMorphClass(),
        ]);
        $this->assertDatabaseMissing('addresses', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('feedbacks', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => $user->getMorphClass(),
        ]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    #[Test]
    #[TestDox('forceDeleted 保留财务流水作为审计凭证')]
    public function force_deleted_keeps_financial_trades(): void
    {
        $user = User::factory()->create();

        WalletTrade::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->id,
            'amount' => 100,
            'before_balance' => 0,
            'after_balance' => 100,
            'before_frozen' => 0,
            'after_frozen' => 0,
            'type' => WalletTradeType::RECHARGE,
            'status' => WalletTradeStatus::SUCCESS,
            'description' => '充值',
        ]);

        $this->observer->forceDeleted($user);

        $this->assertDatabaseHas('wallet_trades', ['owner_id' => $user->id]);
    }
}
