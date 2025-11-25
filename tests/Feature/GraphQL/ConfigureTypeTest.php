<?php

namespace Tests\Feature\GraphQL;

use App\Models\Build;
use App\Models\BuildConfigure;
use App\Models\Configure;
use App\Models\Project;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ConfigureTypeTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private Project $project;

    /** @var array<Configure> */
    private array $configures = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        foreach ($this->configures as $configure) {
            $configure->delete();
        }

        parent::tearDown();
    }

    /**
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        $configure = Configure::factory()->create();

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        BuildConfigure::create([
            'buildid' => $build->id,
            'configureid' => $configure->id,
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    configure {
                        id
                        command
                        log
                        returnValue
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'configure' => [
                        'id' => (string) $configure->id,
                        'command' => $configure->command,
                        'log' => $configure->log,
                        'returnValue' => $configure->status,
                    ],
                ],
            ],
        ]);
    }
}
