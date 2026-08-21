<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\MailVerifyCode;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * MailVerifyCode 单元测试
 */
#[CoversClass(MailVerifyCode::class)]
#[Group('mail')]
class MailVerifyCodeTest extends TestCase
{
    #[Test]
    #[TestDox('构造函数正确赋值 verifyCode')]
    public function constructor_sets_verify_code(): void
    {
        $mailable = new MailVerifyCode('123456');

        $reflection = new \ReflectionProperty($mailable, 'verifyCode');
        $reflection->setAccessible(true);

        $this->assertSame('123456', $reflection->getValue($mailable));
    }

    #[Test]
    #[TestDox('envelope 返回带应用名称的主题')]
    public function envelope_returns_subject_with_app_name(): void
    {
        $mailable = new MailVerifyCode('123456');
        $envelope = $mailable->envelope();

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertStringContainsString(config('app.name'), $envelope->subject);
    }

    #[Test]
    #[TestDox('content 使用 markdown 模板并传递 verifyCode')]
    public function content_uses_markdown_template(): void
    {
        $mailable = new MailVerifyCode('654321');
        $content = $mailable->content();

        $this->assertInstanceOf(Content::class, $content);
        $this->assertSame('emails.verify_code', $content->markdown);
        $this->assertArrayHasKey('verifyCode', $content->with);
        $this->assertSame('654321', $content->with['verifyCode']);
    }

    #[Test]
    #[TestDox('attachments 返回空数组')]
    public function attachments_returns_empty_array(): void
    {
        $mailable = new MailVerifyCode('123456');

        $this->assertSame([], $mailable->attachments());
    }
}
