<?php

namespace App\Mail;

use App\Models\GlobalInvitation;
use App\Models\User;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitedToCdash extends Mailable implements ShouldQueue
{
    use Queueable;

    public User $invitedBy;

    public function __construct(
        public GlobalInvitation $userInvitation,
        public string $password,
    ) {
        $invitedBy = $this->userInvitation->invitedBy;
        if ($invitedBy === null) {
            throw new Exception('User invitation does not refer to inviting user properly.');
        }
        $this->invitedBy = $invitedBy;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[CDash] {$this->invitedBy->firstname} {$this->invitedBy->lastname} invited you to join CDash",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'email.invited-to-cdash',
        );
    }
}
