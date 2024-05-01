<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class BuildTypeTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::findOrFail((int) $this->makePublicProject()->Id);
    }

    protected function tearDown(): void
    {
        // Deleting the project will delete all corresponding builds
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        $uuid = Str::uuid()->toString();
        $this->project->builds()->create([
            'stamp' => 'abcdefg',
            'name' => 'build1',
            'type' => 'Continuous',
            'generator' => 'ctest-2.9.20091218',
            'starttime' => '2011-07-22 15:11:41',
            'endtime' => '2011-07-22 15:29:30',
            'submittime' => '2024-03-21 20:30:51',
            'command' => 'foo bar',
            'configureerrors' => 1,
            'configurewarnings' => 2,
            'configureduration' => 10,
            'builderrors' => 3,
            'buildwarnings' => 4,
            'buildduration' => 20,
            'testnotrun' => 5,
            'testfailed' => 6,
            'testpassed' => 7,
            'testtimestatusfailed' => 8,
            'testduration' => 30,
            'uuid' => $uuid,
        ]);

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                stamp
                                name
                                buildType
                                generator
                                startTime
                                endTime
                                submissionTime
                                command
                                configureErrorsCount
                                configureWarningsCount
                                configureDuration
                                buildErrorsCount
                                buildWarningsCount
                                buildDuration
                                notRunTestsCount
                                failedTestsCount
                                passedTestsCount
                                timeStatusFailedTestsCount
                                testDuration
                                uuid
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $this->project->id,
        ])->assertJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'stamp' => 'abcdefg',
                                    'name' => 'build1',
                                    'buildType' => 'Continuous',
                                    'generator' => 'ctest-2.9.20091218',
                                    'startTime' => '2011-07-22 15:11:41',
                                    'endTime' => '2011-07-22 15:29:30',
                                    'submissionTime' => '2024-03-21 20:30:51',
                                    'command' => 'foo bar',
                                    'configureErrorsCount' => 1,
                                    'configureWarningsCount' => 2,
                                    'configureDuration' => 10,
                                    'buildErrorsCount' => 3,
                                    'buildWarningsCount' => 4,
                                    'buildDuration' => 20,
                                    'notRunTestsCount' => 5,
                                    'failedTestsCount' => 6,
                                    'passedTestsCount' => 7,
                                    'timeStatusFailedTestsCount' => 8,
                                    'testDuration' => 30,
                                    'uuid' => $uuid,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], true);
    }
}
