<?php

namespace Tests\Feature\GraphQL;

use App\Enums\BuildCommandType;
use App\Models\Build;
use App\Models\BuildCommand;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class CompileBuildCommandTypeTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        // Deleting the project will delete all corresponding builds and build measurements
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildCommand $command */
        $command = $build->commands()->create([
            'type' => BuildCommandType::COMPILE_COMMAND,
            'starttime' => Carbon::now(),
            'duration' => 1,
            'command' => Str::uuid()->toString(),
            'binarydirectory' => Str::uuid()->toString(),
            'result' => Str::uuid()->toString(),
            'language' => Str::random(4),
            'target' => Str::uuid()->toString(),
            'source' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query($id: ID) {
                build(id: $id) {
                    compileCommands {
                        edges {
                            node {
                                id
                                startTime
                                duration
                                command
                                binaryDirectory
                                result
                                language
                                target
                                source
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertJson([
            'data' => [
                'build' => [
                    'compileCommands' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $command->id,
                                    'startTime' => $command->starttime->toIso8601String(),
                                    'duration' => 1,
                                    'command' => $command->command,
                                    'binaryDirectory' => $command->binarydirectory,
                                    'result' => $command->result,
                                    'language' => $command->language,
                                    'target' => $command->target,
                                    'source' => $command->source,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], true);
    }
}
