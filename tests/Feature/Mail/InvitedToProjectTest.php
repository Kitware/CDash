<?php

namespace Tests\Feature\Mail;

use App\Enums\ProjectRole;
use App\Mail\InvitedToProject;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class InvitedToProjectTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    protected Project $project;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->user = User::factory()->create([
            'firstname' => 'Pat & Sam',
            'lastname' => "O'Brien <Jr.>",
        ]);
    }

    protected function tearDown(): void
    {
        $this->project->delete();
        $this->user->delete();

        parent::tearDown();
    }

    public function testMailableShowsInviterName(): void
    {
        /** @var ProjectInvitation $invitation */
        $invitation = $this->project->invitations()->create([
            'email' => fake()->email(),
            'invited_by_id' => $this->user->id,
            'role' => ProjectRole::USER,
            'invitation_timestamp' => Carbon::now(),
        ]);

        $mailable = new InvitedToProject($invitation);

        $mailable->assertSeeInText("You have been invited by Pat & Sam O'Brien <Jr.> to join {$this->project->name}.");
    }
}
