<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

/**
 * 邮件验证码
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class MailVerifyCode extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var string 邮件验证码
     */
    protected string $verifyCode;

    /**
     * Create a new message instance.
     */
    public function __construct(string $verifyCode)
    {
        $this->verifyCode = $verifyCode;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Lang::get('Email verification code :appName', ['appName' => config('app.name')]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.verify_code',
            with: [
                'verifyCode' => $this->verifyCode,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
