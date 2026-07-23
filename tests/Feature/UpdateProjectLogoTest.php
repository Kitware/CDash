<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class UpdateProjectLogoTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

    public function testCannotUploadToNonExistentProject(): void
    {
        $user = User::factory()->adminUser()->create();
        $response = $this->actingAs($user)->postJson('/projects/123456789/logo', [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);
        $response->assertForbidden();
    }

    public function testCannotUseNonIntegerProjectId(): void
    {
        $user = User::factory()->adminUser()->create();
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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertForbidden();
        self::assertNull($project->fresh()?->logoUrl);
    }

    public function testCannotUploadAsProjectUser(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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
        $user = User::factory()->adminUser()->create();

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertRedirect(url("/projects/{$project->id}/settings"));
        self::assertNotNull($project->fresh()?->logoUrl);
    }

    public function testCannotUploadNonImage(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->create('document.pdf'),
        ]);

        $response->assertStatus(422);
        self::assertNull($project->fresh()?->logoUrl);
    }

    public function testCannotUploadSvg(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->create('logo.svg'),
        ]);

        $response->assertStatus(422);
        self::assertNull($project->fresh()?->logoUrl);
    }
}
