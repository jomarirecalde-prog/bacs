<?php

namespace App\Mail;

use App\Support\EmailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function envelope(): Envelope
    {
        $reference = (string) ($this->payload['reference_number'] ?? '');
        $subject = (string) ($this->payload['email_subject'] ?? 'BACS | Request Update - '.$reference);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.transaction-update',
            with: array_merge(EmailBranding::layoutContext(), [
                'payload' => $this->payload,
            ]),
        );
    }
}
