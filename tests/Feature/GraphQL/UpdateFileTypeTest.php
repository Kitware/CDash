<?php

namespace Tests\Feature\GraphQL;

use App\Models\Build;
use App\Models\BuildUpdate;
use App\Models\BuildUpdateFile;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class UpdateFileTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    private Project $project;

    /** @var BuildUpdate[] */
    private array $updates = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        foreach ($this->updates as $update) {
            $update->delete();
        }

        parent::tearDown();
    }

    /**
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildUpdate $update */
        $update = BuildUpdate::create([
            'command' => Str::uuid()->toString(),
            'type' => 'GIT',
            'status' => Str::uuid()->toString(),
            'revision' => Str::uuid()->toString(),
            'priorrevision' => Str::uuid()->toString(),
            'path' => Str::uuid()->toString(),
        ]);
        $build->updateStep()->associate($update)->save();

        /** @var BuildUpdateFile $updateFile */
        $updateFile = $update->updateFiles()->create([
            'filename' => Str::uuid()->toString(),
            'author' => Str::uuid()->toString(),
            'email' => Str::uuid()->toString(),
            'committer' => Str::uuid()->toString(),
            'committeremail' => Str::uuid()->toString(),
            'log' => Str::uuid()->toString(),
            'revision' => Str::uuid()->toString(),
            'priorrevision' => Str::uuid()->toString(),
            'status' => 'UPDATED',
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    updateStep {
                        updateFiles {
                            edges {
                                node {
                                    fileName
                                    authorName
                                    authorEmail
                                    committerName
                                    committerEmail
                                    log
                                    revision
                                    priorRevision
                                    status
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
                    'updateStep' => [
                        'updateFiles' => [
                            'edges' => [
                                [
                                    'node' => [
                                        'fileName' => $updateFile->filename,
                                        'authorName' => $updateFile->author,
                                        'authorEmail' => $updateFile->email,
                                        'committerName' => $updateFile->committer,
                                        'committerEmail' => $updateFile->committeremail,
                                        'log' => $updateFile->log,
                                        'revision' => $updateFile->revision,
                                        'priorRevision' => $updateFile->priorrevision,
                                        'status' => $updateFile->status,
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
