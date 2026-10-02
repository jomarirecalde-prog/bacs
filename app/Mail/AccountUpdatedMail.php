<?php

namespace App\Mail;

use App\Support\EmailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'BACS | Account Access Updated',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-updated',
            with: array_merge(EmailBranding::layoutContext(), [
                'payload' => $this->payload,
            ]),
        );
    }
}
