<?php

namespace Tests\Feature\Submission\Notes;

use App\Models\Project;
use CDash\Model\Build;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class NotesXMLTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

    private Project $project;
    private MockInterface $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->log = Log::spy();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * Test that a Notes.xml file submitted for a build which doesn't exist yet creates
     * the build, using the time of the note as the start time of the build.
     */
    public function testBuildStartTimeFromNote(): void
    {
        $this->project->update(['nightlytime' => '18:00:00 America/Denver']);

        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Notes/data/start_time.xml'
        ));

        $this->log->shouldNotHaveReceived('error');
        $this->log->shouldNotHaveReceived('warning');

        $this->assertSame(1, $this->project->builds()->count());
        $build = $this->project->builds()->firstOrFail();
        $this->assertSame('2021-09-16 19:19:46', $build->starttime->toDateTimeString());

        $legacy_build = new Build();
        $legacy_build->Id = $build->id;
        $this->assertSame('2021-09-16', $legacy_build->GetDate());

        $this->assertSame(1, $build->notes()->count());
        $note = $build->notes()->firstOrFail();
        $this->assertSame('my very own note', $note->name);
        $this->assertSame("this is\nmy note\n", $note->text);
    }

    public function testNoteWithoutName(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Notes/data/no_name.xml'
        ));

        $this->assertSame(1, $this->project->builds()->count());
        $build = $this->project->builds()->firstOrFail();
        $this->log->shouldHaveReceived('error')->with("Note missing name for build #{$build->id}")->once();
        $this->assertSame(0, $build->notes()->count());
    }

    public function testNoteWithoutText(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Notes/data/no_text.xml'
        ));

        $this->assertSame(1, $this->project->builds()->count());
        $build = $this->project->builds()->firstOrFail();
        $this->log->shouldHaveReceived('debug')->with("No note text for 'my very own note' on build #{$build->id}")->once();
        $this->assertSame(0, $build->notes()->count());
    }

    /**
     * Test that a note without a time is rejected, since there is no way to
     * determine the start time of the build it would create.
     */
    public function testNoteWithoutTime(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Notes/data/no_time.xml'
        ));

        $this->log->shouldHaveReceived('error')->with("Cannot create build 'note_errors' for note 'my very own note' because time was not set")->once();
        $this->log->shouldHaveReceived('error')->with("Trying to add note 'my very own note' to a nonexistent build")->once();
        $this->assertSame(0, $this->project->builds()->count());
    }
}
