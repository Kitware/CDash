<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class UpdateProjectLogoTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    public function testCannotUploadToNonExistentProject(): void
    {
        $user = $this->makeAdminUser();
        $response = $this->actingAs($user)->postJson('/projects/123456789/logo', [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);
        $response->assertForbidden();
    }

    public function testCannotUseNonIntegerProjectId(): void
    {
        $user = $this->makeAdminUser();
        $response = $this->actingAs($user)->postJson('/projects/abc/logo', [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);
        $response->assertNotFound();
    }

    public function testCannotUploadAsAnonymousUser(): void
    {
        $project = $this->makePublicProject();

        $response = $this->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertForbidden();
        self::assertNull($project->fresh()?->logoUrl);
    }

    public function testCannotUploadAsNormalUser(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeNormalUser();

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertForbidden();
        self::assertNull($project->fresh()?->logoUrl);
    }

    public function testCannotUploadAsProjectUser(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeNormalUser();
        $project->users()->attach($user, ['role' => Project::PROJECT_USER]);

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertForbidden();
        self::assertNull($project->fresh()?->logoUrl);
    }

    public function testCanUploadAsProjectAdmin(): void
    {
        Storage::fake('public');
        $project = $this->makePublicProject();
        $user = $this->makeNormalUser();
        $project->users()->attach($user, ['role' => Project::PROJECT_ADMIN]);

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertRedirect(url("/projects/{$project->id}/settings"));
        self::assertNotNull($project->fresh()?->logoUrl);
    }

    public function testCanUploadAsGlobalAdmin(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeAdminUser();

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertRedirect(url("/projects/{$project->id}/settings"));
        self::assertNotNull($project->fresh()?->logoUrl);
    }

    public function testCannotUploadNonImage(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeAdminUser();

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->create('document.pdf'),
        ]);

        $response->assertStatus(422);
        self::assertNull($project->fresh()?->logoUrl);
    }

    public function testCannotUploadSvg(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeAdminUser();

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->create('logo.svg'),
        ]);

        $response->assertStatus(422);
        self::assertNull($project->fresh()?->logoUrl);
    }
}
