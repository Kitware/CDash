<?php

namespace Tests\Feature\Submission\Tests;

use App\Models\Project;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class BuildXMLTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;

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
     * Test parsing a valid Build.xml file that contains
     * the Source and Binary directories
     */
    public function testBuildDirectoriesHandling(): void
    {
        $this->submitFiles($this->project->name, [
            base_path(
                'tests/Feature/Submission/Tests/data/with_build_source_binary_directories.xml'
            ),
        ]);

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                sourceDirectory
                binaryDirectory
              }
            }
        ', [
            'id' => $this->project->builds()->firstOrFail()->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'sourceDirectory' => '/home/user/Work/cmake',
                    'binaryDirectory' => '/home/user/Work/cmake-build',
                ],
            ],
        ]);
    }
}
