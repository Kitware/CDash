<?php

namespace Tests\Feature\Mail;

use App\Enums\GlobalRole;
use App\Mail\InvitedToCdash;
use App\Models\GlobalInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvitedToCdashTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->adminUser()->create([
            'firstname' => 'Pat & Sam',
            'lastname' => "O'Brien <Jr.>",
        ]);
    }

    protected function tearDown(): void
    {
        $this->user->delete();

        parent::tearDown();
    }

    private function createInvitation(string $password): GlobalInvitation
    {
        /** @var GlobalInvitation $invitation */
        $invitation = GlobalInvitation::create([
            'email' => fake()->email(),
            'invited_by_id' => $this->user->id,
            'role' => GlobalRole::USER,
            'invitation_timestamp' => Carbon::now(),
            'password' => Hash::make($password),
        ]);

        return $invitation;
    }

    public function testMailableShowsInviterName(): void
    {
        $mailable = new InvitedToCdash($this->createInvitation('password'), 'password');

        $mailable->assertSeeInText("You have been invited by Pat & Sam O'Brien <Jr.> to join CDash.");
    }

    public function testMailableShowsPassword(): void
    {
        $password = 'p&ss<w>rd';

        $mailable = new InvitedToCdash($this->createInvitation($password), $password);

        $mailable->assertSeeInText('Your temporary password is: ' . $password);
    }
}
