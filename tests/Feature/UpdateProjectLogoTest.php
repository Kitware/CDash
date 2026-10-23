<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
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

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

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
        $project->users()->attach($user, ['role' => ProjectRole::USER]);

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertForbidden();
        self::assertNull($project->fresh()?->logoUrl);
    }

    public function testCanUploadAsProjectAdmin(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::ADMINISTRATOR]);

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertRedirect(url("/projects/{$project->id}/settings"));
        $project->refresh();
        self::assertNotNull($project->logoUrl);
        Storage::assertExists((string) $project->logo_path);
    }

    public function testCanUploadAsGlobalAdmin(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertRedirect(url("/projects/{$project->id}/settings"));
        $project->refresh();
        self::assertNotNull($project->logoUrl);
        Storage::assertExists((string) $project->logo_path);
    }

    public function testStoresLogoInProjectDirectory(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();
        $logo = UploadedFile::fake()->image('logo.png');
        $contents = (string) file_get_contents($logo->getPathname());

        $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => $logo,
        ]);

        $expected_path = "logos/{$project->id}/" . sha1($contents) . '.png';
        self::assertSame($expected_path, $project->fresh()?->logo_path);
        self::assertSame($contents, Storage::get($expected_path));
    }

    public function testReplacingLogoDeletesPreviousLogo(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.png', 10, 10),
        ]);
        $old_path = (string) $project->fresh()?->logo_path;

        $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.jpg', 20, 20),
        ]);
        $new_path = (string) $project->fresh()?->logo_path;

        self::assertNotSame($old_path, $new_path);
        Storage::assertMissing($old_path);
        Storage::assertExists($new_path);
    }

    public function testUploadingSameLogoTwiceKeepsLogo(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();
        $logo = UploadedFile::fake()->image('logo.png');

        $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => $logo,
        ]);
        $this->actingAs($user)->postJson("/projects/{$project->id}/logo", [
            'logo' => $logo,
        ]);

        Storage::assertExists((string) $project->fresh()?->logo_path);
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
