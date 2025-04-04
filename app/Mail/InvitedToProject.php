<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\User;
use App\Models\UserInvitation;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitedToProject extends Mailable implements ShouldQueue
{
    use Queueable;

    public User $invitedBy;
    public Project $project;

    public function __construct(
        public UserInvitation $userInvitation,
    ) {
        $invitedBy = $this->userInvitation->invitedBy;
        if ($invitedBy === null) {
            throw new Exception('User invitation does not refer to inviting user properly.');
        }
        $this->invitedBy = $invitedBy;

        $project = $this->userInvitation->project;
        if ($project === null) {
            throw new Exception('User invitation does not refer to project properly.');
        }
        $this->project = $project;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[CDash] {$this->invitedBy->firstname} {$this->invitedBy->lastname} invited you to {$this->project->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.invited-to-project',
        );
    }
}
