<?php

namespace Tests\Feature\GraphQL;

use App\Models\Build;
use App\Models\CoverageFile;
use App\Models\Label;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class CoverageTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;

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
                                coverage {
                                    edges {
                                        node {
                                            linesOfCodeTested
                                            linesOfCodeUntested
                                            branchesTested
                                            branchesUntested
                                            functionsTested
                                            functionsUntested
                                            filePath
                                        }
                                    }
                                }
                                coverageResults {
                                    edges {
                                        node {
                                            linesOfCodeTested
                                            linesOfCodeUntested
                                            branchesTested
                                            branchesUntested
                                            functionsTested
                                            functionsUntested
                                            filePath
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
                                                    'linesOfCodeTested' => 4,
                                                    'linesOfCodeUntested' => 5,
                                                    'branchesTested' => 6,
                                                    'branchesUntested' => 7,
                                                    'functionsTested' => 8,
                                                    'functionsUntested' => 9,
                                                    'filePath' => $coverageFile->fullpath,
                                                ],
                                            ],
                                        ],
                                    ],
                                    'coverage' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'linesOfCodeTested' => 4,
                                                    'linesOfCodeUntested' => 5,
                                                    'branchesTested' => 6,
                                                    'branchesUntested' => 7,
                                                    'functionsTested' => 8,
                                                    'functionsUntested' => 9,
                                                    'filePath' => $coverageFile->fullpath,
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
                                coverage {
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
                                    'coverage' => [
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

    public function testCoverageLines(): void
    {
        $coverageFile = CoverageFile::create([
            'fullpath' => Str::uuid()->toString(),
            'file' => Str::uuid()->toString(),
            'crc32' => 0,
        ]);
        $this->coverageFiles[] = $coverageFile;

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $build->coverageResults()->create([
            'fileid' => $coverageFile->id,
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    coverage {
                        edges {
                            node {
                                coveredLines {
                                    lineNumber
                                    timesHit
                                    totalBranches
                                    branchesHit
                                }
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'coverage' => [
                        'edges' => [
                            [
                                'node' => [
                                    'coveredLines' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // TODO: Refactor this once the coverage log is properly normalized in the database.
        DB::insert('
            INSERT INTO coveragefilelog (buildid, fileid, log) VALUES(?, ?, ?)
        ', [
            $build->id,
            $coverageFile->id,
            '1:1;2:0;b3:1/2;b6:0/2;',
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    coverage {
                        edges {
                            node {
                                coveredLines {
                                    lineNumber
                                    timesHit
                                    totalBranches
                                    branchesHit
                                }
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'coverage' => [
                        'edges' => [
                            [
                                'node' => [
                                    'coveredLines' => [
                                        [
                                            'lineNumber' => 1,
                                            'timesHit' => 1,
                                            'totalBranches' => null,
                                            'branchesHit' => null,
                                        ],
                                        [
                                            'lineNumber' => 2,
                                            'timesHit' => 0,
                                            'totalBranches' => null,
                                            'branchesHit' => null,
                                        ],
                                        [
                                            'lineNumber' => 3,
                                            'timesHit' => null,
                                            'totalBranches' => 2,
                                            'branchesHit' => 1,
                                        ],
                                        [
                                            'lineNumber' => 6,
                                            'timesHit' => null,
                                            'totalBranches' => 2,
                                            'branchesHit' => 0,
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

    public function testCodeNotShownWhenShowCoverageCodeIsFalse(): void
    {
        $this->project->showcoveragecode = false;
        $this->project->save();

        $coverageFile = CoverageFile::create([
            'fullpath' => Str::uuid()->toString(),
            'file' => Str::uuid()->toString(),
            'crc32' => 0,
        ]);
        $this->coverageFiles[] = $coverageFile;

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $build->coverageResults()->create([
            'fileid' => $coverageFile->id,
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    coverage {
                        edges {
                            node {
                                file
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'coverage' => [
                        'edges' => [
                            [
                                'node' => [
                                    'file' => null,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCodeShownWhenShowCoverageCodeIsTrue(): void
    {
        $coverageFile = CoverageFile::create([
            'fullpath' => Str::uuid()->toString(),
            'file' => Str::uuid()->toString(),
            'crc32' => 0,
        ]);
        $this->coverageFiles[] = $coverageFile;

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $build->coverageResults()->create([
            'fileid' => $coverageFile->id,
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    coverage {
                        edges {
                            node {
                                file
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'coverage' => [
                        'edges' => [
                            [
                                'node' => [
                                    'file' => $coverageFile->file,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
