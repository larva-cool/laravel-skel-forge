<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature\Api\V1\User;

use App\Http\Controllers\Api\V1\User\FeedbackController;
use App\Models\System\Feedback;
use App\Models\User;
use Database\Factories\FeedbackFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 用户端反馈接口功能测试
 */
#[CoversClass(FeedbackController::class)]
#[Group('api')]
#[Group('feedback')]
class FeedbackControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('游客提交反馈返回未授权')]
    public function guest_cannot_submit_feedback(): void
    {
        $response = $this->postJson('/api/v1/user/feedbacks', [
            'title' => '测试反馈',
            'content' => '这是一条测试反馈内容',
            'type' => 'feature',
            'contact_name' => '测试用户',
            'contact_info' => 'test@example.com',
        ]);

        $response->assertUnauthorized();

        $this->assertDatabaseMissing('feedbacks', [
            'title' => '测试反馈',
        ]);
    }

    #[Test]
    #[TestDox('已登录用户提交反馈时自动绑定 user_id')]
    public function authenticated_user_feedback_binds_user_id(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/feedbacks', [
            'title' => '登录用户的反馈',
            'content' => '内容',
            'type' => 'bug',
            'contact_name' => '测试用户',
            'contact_info' => 'test@example.com',
        ]);

        $response->assertCreated();

        $feedback = Feedback::query()->where('title', '登录用户的反馈')->first();
        $this->assertNotNull($feedback);
        $this->assertSame($user->id, $feedback->user_id);
        $this->assertSame('测试用户', $feedback->contact_name);
        $this->assertSame('test@example.com', $feedback->contact_info);
        $this->assertNotNull($feedback->ip_address);
        $this->assertNotNull($feedback->user_agent);
    }

    #[Test]
    #[TestDox('提交反馈时自动生成 FDB 前缀编号')]
    public function submitted_feedback_has_auto_generated_code(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/v1/user/feedbacks', [
            'title' => '编号测试',
            'content' => '内容',
            'type' => 'other',
        ]);

        $code = $response->json('code');
        $this->assertStringStartsWith('FDB', $code);
    }

    #[Test]
    #[TestDox('缺少必填字段返回验证错误')]
    public function submit_requires_title_content_type(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/v1/user/feedbacks', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content', 'type']);
    }

    #[Test]
    #[TestDox('无效类型返回验证错误')]
    public function invalid_type_returns_validation_error(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/v1/user/feedbacks', [
            'title' => '测试',
            'content' => '内容',
            'type' => 'invalid_type_not_exists',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    #[TestDox('未登录用户无法访问我的反馈列表')]
    public function guest_cannot_access_my_feedbacks(): void
    {
        $this->getJson('/api/v1/user/feedbacks/my')->assertUnauthorized();
    }

    #[Test]
    #[TestDox('登录用户可查看自己的反馈列表')]
    public function authenticated_user_can_list_own_feedbacks(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        FeedbackFactory::new()->count(2)->create(['user_id' => $user->id]);
        FeedbackFactory::new()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->getJson('/api/v1/user/feedbacks/my');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    #[TestDox('提交反馈时标题超长返回验证错误')]
    public function title_max_length_validation(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/v1/user/feedbacks', [
            'title' => str_repeat('a', 201),
            'content' => '内容',
            'type' => 'feature',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    #[Test]
    #[TestDox('可携带图片列表提交反馈')]
    public function submit_feedback_with_images(): void
    {
        $this->actingAsUser();

        $images = [
            'https://cdn.example.com/feedback/1.jpg',
            'https://cdn.example.com/feedback/2.jpg',
        ];

        $response = $this->postJson('/api/v1/user/feedbacks', [
            'title' => '带图片的反馈',
            'content' => '内容',
            'type' => 'bug',
            'images' => $images,
        ]);

        $response->assertCreated();

        $feedback = Feedback::query()->where('title', '带图片的反馈')->first();
        $this->assertNotNull($feedback);
        $this->assertSame($images, $feedback->images);
    }

    #[Test]
    #[TestDox('图片超过最大数量返回验证错误')]
    public function images_exceed_max_limit_returns_validation_error(): void
    {
        $this->actingAsUser();

        $images = array_map(
            fn (int $i) => "https://cdn.example.com/feedback/{$i}.jpg",
            range(1, 10)
        );

        $response = $this->postJson('/api/v1/user/feedbacks', [
            'title' => '图片太多',
            'content' => '内容',
            'type' => 'bug',
            'images' => $images,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images']);
    }

    #[Test]
    #[TestDox('图片项不是合法URL返回验证错误')]
    public function invalid_image_url_returns_validation_error(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/v1/user/feedbacks', [
            'title' => '图片URL无效',
            'content' => '内容',
            'type' => 'bug',
            'images' => ['not-a-valid-url'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    /**
     * 模拟一个已登录用户，返回用户实例
     */
    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
