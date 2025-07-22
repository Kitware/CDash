<?php

namespace Tests\Feature\GraphQL;

use App\Models\CoverageFile;
use App\Models\Label;
use App\Models\Project;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class CoverageTypeTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private Project $project;

    /** @var array<CoverageFile> */
    private array $coverageFiles = [];

    /** @var array<Label> */
    private array $labels = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        foreach ($this->coverageFiles as $file) {
            $file->delete();
        }

        foreach ($this->labels as $label) {
            $label->delete();
        }

        // Deleting the project will delete all corresponding builds and coverage results
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        $coverageFile = CoverageFile::create([
            'fullpath' => Str::uuid()->toString(),
            'file' => Str::uuid()->toString(),
            'crc32' => 0,
        ]);
        $this->coverageFiles[] = $coverageFile;

        $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->coverageResults()->create([
            'fileid' => $coverageFile->id,
            'loctested' => 4,
            'locuntested' => 5,
            'branchestested' => 6,
            'branchesuntested' => 7,
            'functionstested' => 8,
            'functionsuntested' => 9,
        ]);

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                coverageResults {
                                    edges {
                                        node {
                                            filePath
                                            linesOfCodeTested
                                            linesOfCodeUntested
                                            branchesTested
                                            branchesUntested
                                            functionsTested
                                            functionsUntested
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
                                    'coverageResults' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'filePath' => $coverageFile->fullpath,
                                                    'linesOfCodeTested' => 4,
                                                    'linesOfCodeUntested' => 5,
                                                    'branchesTested' => 6,
                                                    'branchesUntested' => 7,
                                                    'functionsTested' => 8,
                                                    'functionsUntested' => 9,
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

    public function testLabelRelationship(): void
    {
        $coverageFile = CoverageFile::create([
            'fullpath' => Str::uuid()->toString(),
            'file' => Str::uuid()->toString(),
            'crc32' => 0,
        ]);
        $this->coverageFiles[] = $coverageFile;

        $label = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->coverageResults()->create([
            'fileid' => $coverageFile->id,
        ])->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);
        $this->labels[] = $label;

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                coverageResults {
                                    edges {
                                        node {
                                            labels {
                                                edges {
                                                    node {
                                                        text
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
                                    'coverageResults' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'labels' => [
                                                        'edges' => [
                                                            [
                                                                'node' => [
                                                                    'text' => $label->text,
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
