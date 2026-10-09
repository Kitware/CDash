<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class ProjectNotificationsTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private Project $project;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->user = User::factory()->create();
        $this->project->users()->attach($this->user, [
            'role' => ProjectRole::USER,
            'emailtype' => 1,
            'emailcategory' => 2 + 16 + 64,
            'emailsuccess' => true,
            'emailmissingsites' => false,
        ]);
    }

    /**
     * @return array<string,int>
     */
    private function getPreferences(User $user): array
    {
        $row = DB::table('user2project')
            ->where('userid', $user->id)
            ->where('projectid', $this->project->id)
            ->first(['emailtype', 'emailcategory', 'emailsuccess', 'emailmissingsites']);
        self::assertNotNull($row);

        return [
            'emailtype' => (int) $row->emailtype,
            'emailcategory' => (int) $row->emailcategory,
            'emailsuccess' => (int) $row->emailsuccess,
            'emailmissingsites' => (int) $row->emailmissingsites,
        ];
    }

    /**
     * @param TestResponse<Response> $response
     *
     * @return array<string,mixed>
     */
    private static function getProps(TestResponse $response): array
    {
        /** @var array<string,mixed> $props */
        $props = $response->assertOk()->viewData('props');
        self::assertIsArray($props);
        return $props;
    }

    public function testRequiresLogin(): void
    {
        $this->get("/projects/{$this->project->id}/notifications")->assertRedirect('login');
        $this->post("/projects/{$this->project->id}/notifications", ['emailtype' => 0])->assertRedirect('login');
    }

    public function testShowsCurrentPreferences(): void
    {
        $this->project->emailbrokensubmission = false;
        $this->project->save();

        $props = self::getProps($this->actingAs($this->user)->get("/projects/{$this->project->id}/notifications"));

        self::assertSame($this->project->id, $props['project-id']);
        self::assertFalse($props['project-emails-enabled']);
        self::assertFalse($props['can-edit-project']);
        self::assertSame(1, $props['email-type']);
        self::assertTrue($props['email-success']);
        self::assertFalse($props['email-missing-sites']);
        self::assertSame(['update', 'error', 'dynamicanalysis'], $props['email-categories']);
        self::assertSame('', $props['message']);
    }

    public function testProjectAdminCanEditProject(): void
    {
        $admin = User::factory()->create();
        $this->project->users()->attach($admin, ['role' => ProjectRole::ADMINISTRATOR]);

        $props = self::getProps($this->actingAs($admin)->get("/projects/{$this->project->id}/notifications"));

        self::assertTrue($props['can-edit-project']);
        self::assertTrue($props['project-emails-enabled']);
    }

    public function testNonMembersCannotViewOrUpdatePreferences(): void
    {
        $non_member = User::factory()->create();

        $this->actingAs($non_member)
            ->get("/projects/{$this->project->id}/notifications")
            ->assertForbidden();
        $this->actingAs($non_member)
            ->post("/projects/{$this->project->id}/notifications", ['emailtype' => 3])
            ->assertForbidden();

        self::assertFalse($non_member->projects()->whereKey($this->project->id)->exists());
    }

    public function testCannotViewInaccessibleProject(): void
    {
        $private_project = $this->makePrivateProject();

        $this->actingAs($this->user)
            ->get("/projects/{$private_project->id}/notifications")
            ->assertNotFound();
        $this->actingAs($this->user)
            ->post("/projects/{$private_project->id}/notifications", ['emailtype' => 3])
            ->assertNotFound();
        $this->actingAs($this->user)
            ->get('/projects/123456789/notifications')
            ->assertNotFound();
    }

    public function testUpdatesPreferences(): void
    {
        $other_user = User::factory()->create();
        $this->project->users()->attach($other_user, ['role' => ProjectRole::USER]);
        $other_user_preferences = $this->getPreferences($other_user);

        $this->actingAs($this->user)
            ->post("/projects/{$this->project->id}/notifications", [
                'emailtype' => '3',
                'emailmissingsites' => '1',
                'emailcategories' => ['configure', 'warning', 'test'],
            ])
            ->assertRedirect("/projects/{$this->project->id}/notifications")
            ->assertSessionHas('message', 'Your notification preferences have been updated.');

        self::assertSame([
            'emailtype' => 3,
            'emailcategory' => 4 + 8 + 32,
            'emailsuccess' => 0,
            'emailmissingsites' => 1,
        ], $this->getPreferences($this->user));
        self::assertSame($other_user_preferences, $this->getPreferences($other_user));

        $props = self::getProps($this->actingAs($this->user)->get("/projects/{$this->project->id}/notifications"));
        self::assertSame(3, $props['email-type']);
        self::assertSame(['configure', 'warning', 'test'], $props['email-categories']);
        self::assertSame('Your notification preferences have been updated.', $props['message']);
    }

    public function testClearsAllCategories(): void
    {
        $this->actingAs($this->user)
            ->post("/projects/{$this->project->id}/notifications", ['emailtype' => '0'])
            ->assertRedirect("/projects/{$this->project->id}/notifications");

        self::assertSame([
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => 0,
            'emailmissingsites' => 0,
        ], $this->getPreferences($this->user));
    }

    public function testRejectsInvalidPreferences(): void
    {
        $original_preferences = $this->getPreferences($this->user);

        foreach ([
            [],
            ['emailtype' => '4'],
            ['emailtype' => 'abc'],
            ['emailtype' => '1', 'emailsuccess' => 'abc'],
            ['emailtype' => '1', 'emailcategories' => 'test'],
            ['emailtype' => '1', 'emailcategories' => ['coverage']],
        ] as $input) {
            $this->actingAs($this->user)
                ->post("/projects/{$this->project->id}/notifications", $input)
                ->assertSessionHasErrors();
        }

        self::assertSame($original_preferences, $this->getPreferences($this->user));
    }

    public function testLegacyUrlRedirects(): void
    {
        $this->actingAs($this->user)
            ->get("/subscribeProject.php?projectid={$this->project->id}")
            ->assertRedirect("/projects/{$this->project->id}/notifications");
        $this->actingAs($this->user)
            ->get('/subscribeProject.php')
            ->assertStatus(400);
    }
}
