<?php

namespace Tests\Feature\Submission\Update;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class UpdateXMLTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * Test that the ChangeId attribute of the Site element is stored for an existing
     * build which was created by a submission without a ChangeId.
     */
    public function testChangeIdFromSiteAttribute(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Update/data/change_id_build.xml'
        ));
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Update/data/change_id_test.xml'
        ));

        $this->assertSame(1, $this->project->builds()->count());
        $build = $this->project->builds()->firstOrFail();
        $this->assertSame('555', $build->changeid);
        $this->assertSame('ctest-3.14.0-rc1', $build->generator);
    }

    /**
     * Test that the ChangeId element of an Update.xml file is stored for the build.
     */
    public function testChangeIdFromUpdate(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Update/data/change_id_update.xml'
        ));

        $this->assertSame(1, $this->project->builds()->count());
        $build = $this->project->builds()->firstOrFail();
        $this->assertSame('555', $build->changeid);
        $this->assertSame('ctest-3.14.0-rc1', $build->generator);
    }

    /**
     * Test parsing three Update.xml files for the same build, where each file sets the Append
     * attribute.  The updates should be combined into a single update for the build.
     */
    public function testAppend(): void
    {
        foreach (range(1, 3) as $part) {
            $this->makeSubmission($this->project->name, base_path(
                "tests/Feature/Submission/Update/data/append_part_$part.xml"
            ));
        }

        $this->assertSame(1, $this->project->builds()->count());
        $update = $this->project->builds()->firstOrFail()->updateStep;
        $this->assertNotNull($update);
        $this->assertSame(3, $update->nfiles);
        $this->assertSame(3, $update->updateFiles()->count());
        $this->assertSame(1, $update->warnings);
        // The commands from all files are concatenated.
        $this->assertSame('Command 1Command 2Command 3', $update->command);
        // The start time comes from the first file and the end time from the last file.
        $this->assertSame('2015-08-24 04:04:14', $update->starttime->toDateTimeString());
        $this->assertSame('2015-08-24 04:20:30', $update->endtime->toDateTimeString());
        // The return status is overwritten by each file.
        $this->assertSame('', $update->status);
        // The type, revisions, and path are only read from the first file.
        $this->assertSame('GIT', $update->type);
        $this->assertSame('3', $update->revision);
        $this->assertSame('2', $update->priorrevision);
        $this->assertSame('mypath', $update->path);
    }

    /**
     * Test that revisions which look like numbers are stored as strings, keeping their leading zeros.
     */
    public function testNumericRevision(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Update/data/numeric_revision.xml'
        ));

        $this->graphQL('
            query project($id: ID) {
              project(id: $id) {
                builds {
                  edges {
                    node {
                      updateStep {
                        revision
                        priorRevision
                      }
                    }
                  }
                }
              }
            }
        ', [
            'id' => $this->project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'updateStep' => [
                                        'revision' => '08416132611118b6817515907055112111663314',
                                        'priorRevision' => '07416132611118b6817515907055112111663314',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
