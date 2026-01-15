<?php

namespace Tests\Feature\GraphQL;

use App\Models\Build;
use App\Models\BuildUpdate;
use App\Models\Project;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class UpdateTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;

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

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    updateStep {
                        id
                        command
                        type
                        status
                        revision
                        priorRevision
                        path
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'updateStep' => [
                        'id' => (string) $update->id,
                        'command' => $update->command,
                        'type' => $update->type,
                        'status' => $update->status,
                        'revision' => $update->revision,
                        'priorRevision' => $update->priorrevision,
                        'path' => $update->path,
                    ],
                ],
            ],
        ]);
    }
}
