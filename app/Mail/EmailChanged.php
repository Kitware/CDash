<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmailChanged extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $oldEmail,
        public string $newEmail,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[CDash] Your email address was changed',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'email.email-changed',
        );
    }
}
