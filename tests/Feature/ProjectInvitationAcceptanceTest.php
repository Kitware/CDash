<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ProjectInvitationAcceptanceTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;

    protected Project $project;

    /**
     * @var array<User>
     */
    protected array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->users['admin'] = $this->makeAdminUser();
        $this->users['normal'] = $this->makeNormalUser();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->project->delete();

        foreach ($this->users as $user) {
            $user->delete();
        }
    }

    private function createInvitation(): ProjectInvitation
    {
        /** @var ProjectInvitation $invitation */
        $invitation = $this->project->invitations()->create([
            'email' => fake()->unique()->email(),
            'invited_by_id' => $this->users['admin']->id,
            'role' => ProjectRole::USER,
            'invitation_timestamp' => Carbon::now(),
        ]);

        return $invitation;
    }

    public function testCannotAcceptInvitationWhenLoggedOut(): void
    {
        $invitation = $this->createInvitation();
        self::assertContains($invitation->id, $this->project->invitations()->pluck('id'));

        $this->get('/projects/' . $invitation->project_id . '/invitations/' . $invitation->id)
            ->assertRedirect('/login');

        self::assertContains($invitation->id, $this->project->invitations()->pluck('id'));
    }

    public function testCannotAcceptNonexistentInvitation(): void
    {
        $invitation = $this->createInvitation();
        self::assertContains($invitation->id, $this->project->invitations()->pluck('id'));

        $this->actingAs($this->users['normal'])
            ->get('/projects/' . $invitation->project_id . '/invitations/' . 1234567)
            ->assertUnauthorized();

        self::assertContains($invitation->id, $this->project->invitations()->pluck('id'));
    }

    public function testCannotAcceptInvitationForDifferentUser(): void
    {
        $invitation = $this->createInvitation();
        self::assertContains($invitation->id, $this->project->invitations()->pluck('id'));

        $this->actingAs($this->users['normal'])
            ->get('/projects/' . $invitation->project_id . '/invitations/' . $invitation->id)
            ->assertUnauthorized();

        self::assertContains($invitation->id, $this->project->invitations()->pluck('id'));
    }

    public function testCannotAcceptInvitationWhenAlreadyAMember(): void
    {
        $invitation = $this->createInvitation();
        $invitation->email = $this->users['normal']->email;
        $invitation->save();
        self::assertContains($invitation->id, $this->project->invitations()->pluck('id'));

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_USER,
            ]);

        $this->actingAs($this->users['normal'])
            ->get('/projects/' . $invitation->project_id . '/invitations/' . $invitation->id)
            ->assertUnauthorized();

        self::assertNotContains($invitation->id, $this->project->invitations()->pluck('id'));
    }

    public function testCannotAcceptSelfInvite(): void
    {
        $invitation = $this->createInvitation();
        $invitation->email = $this->users['admin']->email;
        $invitation->save();
        self::assertContains($invitation->id, $this->project->invitations()->pluck('id'));

        $this->actingAs($this->users['admin'])
            ->get('/projects/' . $invitation->project_id . '/invitations/' . $invitation->id)
            ->assertUnauthorized();

        self::assertContains($invitation->id, $this->project->invitations()->pluck('id'));
    }

    public function testAcceptUserInvite(): void
    {
        $invitation = $this->createInvitation();
        $invitation->email = $this->users['normal']->email;
        $invitation->save();
        self::assertContains($invitation->id, $this->project->invitations()->pluck('id'));

        $this->actingAs($this->users['normal'])
            ->get('/projects/' . $invitation->project_id . '/invitations/' . $invitation->id)
            ->assertRedirect("/index.php?project={$this->project->name}");

        self::assertNotContains($invitation->id, $this->project->invitations()->pluck('id'));
        self::assertContains($this->users['normal']->id, $this->project->basicUsers()->pluck('id'));
    }

    public function testAcceptAdminInvite(): void
    {
        $invitation = $this->createInvitation();
        $invitation->role = ProjectRole::ADMINISTRATOR;
        $invitation->email = $this->users['normal']->email;
        $invitation->save();
        self::assertContains($invitation->id, $this->project->invitations()->pluck('id'));

        $this->actingAs($this->users['normal'])
            ->get('/projects/' . $invitation->project_id . '/invitations/' . $invitation->id)
            ->assertRedirect("/index.php?project={$this->project->name}");

        self::assertNotContains($invitation->id, $this->project->invitations()->pluck('id'));
        self::assertContains($this->users['normal']->id, $this->project->administrators()->pluck('id'));
    }
}
