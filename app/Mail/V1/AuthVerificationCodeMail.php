<?php

declare(strict_types=1);

namespace App\Mail\V1;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuthVerificationCodeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $code,
        public string $action,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('auth.verification_code_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.verification-code',
            with: [
                'code' => $this->code,
                'actionLabel' => __('auth.actions.'.$this->action),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
