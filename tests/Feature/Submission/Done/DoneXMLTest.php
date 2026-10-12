<?php

namespace Tests\Feature\Submission\Done;

use App\Models\PendingSubmissions;
use App\Models\Project;
use App\Models\Site;
use CDash\Model\Build;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class DoneXMLTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

    private const BUILD_NAME = 'done-handler-test-build';
    private const BUILD_STAMP = '20181010-1410-Experimental';
    private const BUILD_TIMESTAMP = 1539195000;

    private Project $project;
    private MockInterface $log;
    private Site $site;
    private Build $build;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->log = Log::spy();

        $this->project = $this->makePublicProject();
        $this->site = Site::factory()->create();

        $this->build = new Build();
        $this->build->Name = self::BUILD_NAME;
        $this->build->ProjectId = $this->project->id;
        $this->build->SiteId = $this->site->id;
        $this->build->SetStamp(self::BUILD_STAMP);
        $this->build->StartTime = '2018-10-10 18:10:00';
        $this->assertTrue($this->build->AddBuild());
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * Submit a Done.xml file for the build, returning the build id from the response.
     */
    private function submitDone(): ?int
    {
        $file_to_submit = tempnam(sys_get_temp_dir(), 'Done');
        $this->assertNotFalse($file_to_submit);
        try {
            file_put_contents(
                $file_to_submit,
                '<?xml version="1.0" encoding="UTF-8"?><Done><buildId>' . $this->build->Id
                    . '</buildId><time>' . self::BUILD_TIMESTAMP . '</time></Done>',
            );
            return $this->makeSubmission($this->project->name, $file_to_submit, parameters: [
                'build' => self::BUILD_NAME,
                'site' => $this->site->name,
                'stamp' => self::BUILD_STAMP,
            ]);
        } finally {
            unlink($file_to_submit);
        }
    }

    public function testMarksBuildAsDone(): void
    {
        $this->assertSame((int) $this->build->Id, $this->submitDone());

        $this->assertTrue($this->project->builds()->findOrFail((int) $this->build->Id)->done);
        $this->log->shouldNotHaveReceived('error');
        $this->log->shouldNotHaveReceived('warning');
    }

    /**
     * Test that a Done.xml file is requeued while other submissions for the build are
     * still pending, and that it gives up after the maximum number of retries.
     */
    public function testRequeuesWhileSubmissionsArePending(): void
    {
        PendingSubmissions::upsert([
            'buildid' => $this->build->Id,
            'numfiles' => 2,
            'recheck' => true,
        ], 'buildid');

        $this->submitDone();

        $this->assertFalse($this->project->builds()->findOrFail((int) $this->build->Id)->done);
        $parsed_files = Storage::files('parsed');
        $this->assertCount(1, $parsed_files);
        $contents = Storage::get($parsed_files[0]);
        $this->assertIsString($contents);
        $this->assertStringContainsString('Done retries="5"', $contents);
        $this->log->shouldNotHaveReceived('error');
        $this->log->shouldNotHaveReceived('warning');
    }
}
