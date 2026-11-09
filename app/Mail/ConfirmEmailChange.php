<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class ConfirmEmailChange extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $minutes;

    public string $url;

    /**
     * The link includes a hash of the user's current address, so it stops working once the address changes.
     */
    public function __construct(
        User $user,
        public string $email,
    ) {
        $this->minutes = config()->integer('auth.verification.expire', 60);
        $this->url = URL::temporarySignedRoute('confirm-email-change', Carbon::now()->addMinutes($this->minutes), [
            'id' => $user->id,
            'hash' => sha1($user->email),
            'email' => $email,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[CDash] Confirm your new email address',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'email.confirm-email-change',
        );
    }
}
