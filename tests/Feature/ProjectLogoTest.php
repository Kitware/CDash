<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class ProjectLogoTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    /**
     * @throws FileNotFoundException
     */
    private function setLogo(Project $project): string
    {
        $logo = UploadedFile::fake()->image('logo.png');
        ProjectService::setLogo($project, $logo);
        return (string) file_get_contents($logo->getPathname());
    }

    /**
     * @throws FileNotFoundException
     */
    public function testPublicProjectLogoVisibleToAnonymousUser(): void
    {
        $project = $this->makePublicProject();
        $contents = $this->setLogo($project);

        $response = $this->get((string) $project->logoUrl);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        self::assertSame($contents, $response->streamedContent());
    }

    /**
     * @throws FileNotFoundException
     */
    public function testPrivateProjectLogoNotVisibleToAnonymousUser(): void
    {
        $project = $this->makePrivateProject();
        $this->setLogo($project);

        $this->get((string) $project->logoUrl)->assertNotFound();
    }

    /**
     * @throws FileNotFoundException
     */
    public function testPrivateProjectLogoNotVisibleToNonMember(): void
    {
        $project = $this->makePrivateProject();
        $this->setLogo($project);

        $this->actingAs(User::factory()->create())
            ->get((string) $project->logoUrl)
            ->assertNotFound();
    }

    /**
     * @throws FileNotFoundException
     */
    public function testPrivateProjectLogoVisibleToProjectMember(): void
    {
        $project = $this->makePrivateProject();
        $this->setLogo($project);
        $user = User::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::USER]);

        $this->actingAs($user)
            ->get((string) $project->logoUrl)
            ->assertOk();
    }

    /**
     * @throws FileNotFoundException
     */
    public function testPrivateProjectLogoVisibleToGlobalAdmin(): void
    {
        $project = $this->makePrivateProject();
        $this->setLogo($project);

        $this->actingAs(User::factory()->adminUser()->create())
            ->get((string) $project->logoUrl)
            ->assertOk();
    }

    /**
     * @throws FileNotFoundException
     */
    public function testProtectedProjectLogoVisibleOnlyToLoggedInUsers(): void
    {
        $project = $this->makeProtectedProject();
        $this->setLogo($project);

        $this->get((string) $project->logoUrl)->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get((string) $project->logoUrl)
            ->assertOk();
    }

    public function testProjectWithoutLogo(): void
    {
        $project = $this->makePublicProject();

        self::assertNull($project->fresh()?->logoUrl);
        $this->get("/projects/{$project->id}/logo")->assertNotFound();
    }

    public function testNonExistentProject(): void
    {
        $this->get('/projects/123456789/logo')->assertNotFound();
    }

    /**
     * @throws FileNotFoundException
     */
    public function testMissingLogoFile(): void
    {
        $project = $this->makePublicProject();
        $this->setLogo($project);
        Storage::delete((string) $project->logo_path);

        $this->get((string) $project->logoUrl)->assertNotFound();
    }

    /**
     * @throws FileNotFoundException
     */
    public function testOnlyCurrentLogoUrlIsCachedIndefinitely(): void
    {
        $project = $this->makePublicProject();
        $this->setLogo($project);

        $this->get((string) $project->logoUrl)
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, private');

        $this->get("/projects/{$project->id}/logo")
            ->assertHeader('Cache-Control', 'no-cache, private');

        $this->get("/projects/{$project->id}/logo?v=" . sha1('old logo'))
            ->assertHeader('Cache-Control', 'no-cache, private');
    }
}
