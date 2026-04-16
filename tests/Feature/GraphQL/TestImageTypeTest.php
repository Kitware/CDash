<?php

namespace Tests\Feature\GraphQL;

use App\Models\Image;
use App\Models\Project;
use App\Models\TestOutput;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Random\RandomException;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class TestImageTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    private Project $project;
    private Image $image;
    private TestOutput $testOutput;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->image = Image::create([
            'img' => '',
            'extension' => '.png',
            'checksum' => '123',
        ]);

        $this->testOutput = TestOutput::create([
            'path' => Str::uuid()->toString(),
            'command' => Str::uuid()->toString(),
            'output' => Str::uuid()->toString(),
        ]);
    }

    /**
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        $test = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        $testImageNoImage = $test->testImages()->create([
            'role' => Str::uuid()->toString(),
            'imgid' => 0,
        ]);

        $testImageWithImage = $test->testImages()->create([
            'role' => Str::uuid()->toString(),
            'imgid' => $this->image->id,
        ]);

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                tests {
                                    edges {
                                        node {
                                            testImages {
                                                edges {
                                                    node {
                                                        id
                                                        role
                                                        url
                                                    }
                                                }
                                            }
                                        }
                                    }
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
                                    'tests' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'testImages' => [
                                                        'edges' => [
                                                            [
                                                                'node' => [
                                                                    'id' => (string) $testImageWithImage->id,
                                                                    'role' => $testImageWithImage->role,
                                                                    'url' => url('/image/' . $this->image->id),
                                                                ],
                                                            ],
                                                            [
                                                                'node' => [
                                                                    'id' => (string) $testImageNoImage->id,
                                                                    'role' => $testImageNoImage->role,
                                                                    'url' => null,
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
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
