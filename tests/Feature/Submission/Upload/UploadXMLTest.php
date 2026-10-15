<?php

namespace Tests\Feature\Submission\Upload;

use App\Models\Project;
use CDash\Model\Build;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class UploadXMLTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

    private Project $project;
    private MockInterface $log;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->log = Log::spy();

        $this->project = $this->makePublicProject();
        $this->project->update(['uploadquota' => 2 ** 30]);
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * Test that an Upload.xml file submitted for a build which doesn't exist yet creates
     * the build, using the time of the upload as the start time of the build.
     */
    public function testBuildStartTimeFromUpload(): void
    {
        $this->project->update(['nightlytime' => '18:00:00 America/Denver']);

        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Upload/data/start_time.xml'
        ));

        $this->log->shouldNotHaveReceived('error');
        $this->log->shouldNotHaveReceived('warning');

        $this->assertSame(1, $this->project->builds()->count());
        $build = $this->project->builds()->firstOrFail();
        $this->assertSame('2024-02-27 18:50:50', $build->starttime->toDateTimeString());

        $legacy_build = new Build();
        $legacy_build->Id = $build->id;
        $this->assertSame('2024-02-27', $legacy_build->GetDate());

        $this->assertSame(1, $build->uploadedFiles()->count());
        $file = $build->uploadedFiles()->firstOrFail();
        $this->assertSame('tmp.txt', $file->filename);
        Storage::assertExists("upload/{$file->sha1sum}");
    }
}
