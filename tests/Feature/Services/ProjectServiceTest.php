<?php

namespace Tests\Feature\Services;

use App\Models\Build;
use App\Models\Note;
use App\Models\Project;
use App\Models\Site;
use App\Models\UploadFile;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected ?Project $project = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->project?->delete();

        parent::tearDown();
    }

    public function testCreatesDefaultBuildGroups(): void
    {
        $project = ProjectService::create([
            'name' => Str::uuid()->toString(),
            'public' => Project::ACCESS_PUBLIC,
        ]);

        $project = $project->refresh();

        self::assertTrue($project->exists());
        self::assertSame(3, $project->buildgroups()->count());
        self::assertEquals(
            ['Nightly', 'Continuous', 'Experimental'],
            $project->buildgroups()->pluck('name')->toArray(),
        );

        foreach ($project->buildgroups as $buildgroup) {
            self::assertNotNull($buildgroup->starttime);
            self::assertNull($buildgroup->endtime);
        }

        $active_groups = ProjectService::getBuildGroups($project->id);
        self::assertCount(3, $active_groups);
    }

    public function testDeleteRemovesProjectAndOrphanedBuildData(): void
    {
        Storage::fake('local');

        $project = ProjectService::create([
            'name' => Str::uuid()->toString(),
            'public' => Project::ACCESS_PUBLIC,
        ]);
        $site = Site::factory()->create();

        $build = $project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $site->id,
        ]);

        $note = Note::factory()->create();
        $note->builds()->attach($build->id, ['time' => now()]);

        $uploadFile = UploadFile::create([
            'filename' => Str::uuid()->toString(),
            'filesize' => 123,
            'sha1sum' => sha1(Str::uuid()->toString()),
            'isurl' => false,
        ]);
        $uploadFile->builds()->attach($build->id);
        Storage::put("upload/{$uploadFile->sha1sum}", Str::uuid()->toString());

        ProjectService::delete($project);

        self::assertDatabaseMissing(Project::class, ['id' => $project->id]);
        self::assertDatabaseMissing(Build::class, ['id' => $build->id]);
        self::assertDatabaseMissing(Note::class, ['id' => $note->id]);
        self::assertDatabaseMissing(UploadFile::class, ['id' => $uploadFile->id]);
        Storage::assertMissing("upload/{$uploadFile->sha1sum}");
    }
}
