<?php

namespace Tests\Feature\GraphQL;

use App\Enums\BuildCommandType;
use App\Models\Build;
use App\Models\BuildCommand;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class BuildCommandTypeTest extends TestCase
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
        $this->project->delete();

        parent::tearDown();
    }

    public function testBasicFieldAccess(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildCommand $command */
        $command = $build->commands()->create([
            'type' => BuildCommandType::CUSTOM,
            'starttime' => Carbon::now(),
            'duration' => 12345,
            'command' => Str::random(10),
            'result' => Str::random(10),
            'source' => Str::random(10),
            'language' => Str::random(10),
            'config' => Str::random(10),
            'workingdirectory' => Str::uuid()->toString(),
        ]);
        $command->refresh();

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    commands {
                        edges {
                            node {
                                id
                                type
                                startTime
                                duration
                                command
                                result
                                source
                                language
                                config
                                workingDirectory
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
                    'commands' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $command->id,
                                    'type' => 'CUSTOM',
                                    'startTime' => $command->starttime->toIso8601ZuluString('microsecond'),
                                    'duration' => $command->duration,
                                    'command' => $command->command,
                                    'result' => $command->result,
                                    'source' => $command->source,
                                    'language' => $command->language,
                                    'config' => $command->config,
                                    'workingDirectory' => $command->workingdirectory,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array<array<string>>
     */
    public static function commandTypes(): array
    {
        $return_arr = [];
        foreach (BuildCommandType::cases() as $type) {
            $return_arr[] = [$type->name];
        }

        return $return_arr;
    }

    #[DataProvider('commandTypes')]
    public function testFilterByType(string $type): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $commands = [];
        foreach (BuildCommandType::cases() as $bc_type) {
            $commands[] = [
                'type' => $bc_type,
                'starttime' => Carbon::now(),
                'duration' => 12345,
                'command' => Str::random(10),
                'result' => Str::random(10),
                'workingdirectory' => Str::uuid()->toString(),
            ];
        }
        $build->commands()->createMany($commands);

        $this->graphQL('
            query build($id: ID, $type: BuildCommandType) {
                build(id: $id) {
                    commands(
                        filters: {
                            eq: {
                                type: $type
                            }
                        }
                    ){
                        edges {
                            node {
                                type
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
            'type' => $type,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'commands' => [
                        'edges' => [
                            [
                                'node' => [
                                    'type' => $type,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testFilterByDuration(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $build->commands()->createMany([
            [
                'type' => BuildCommandType::CUSTOM,
                'starttime' => Carbon::now(),
                'duration' => 1234,
                'command' => Str::random(10),
                'result' => Str::random(10),
                'workingdirectory' => Str::uuid()->toString(),
            ],
            [
                'type' => BuildCommandType::CUSTOM,
                'starttime' => Carbon::now(),
                'duration' => 2345,
                'command' => Str::random(10),
                'result' => Str::random(10),
                'workingdirectory' => Str::uuid()->toString(),
            ],
            [
                'type' => BuildCommandType::CUSTOM,
                'starttime' => Carbon::now(),
                'duration' => 3456,
                'command' => Str::random(10),
                'result' => Str::random(10),
                'workingdirectory' => Str::uuid()->toString(),
            ],
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    commands(
                        filters: {
                            eq: {
                                duration: 2345
                            }
                        }
                    ){
                        edges {
                            node {
                                duration
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
                    'commands' => [
                        'edges' => [
                            [
                                'node' => [
                                    'duration' => 2345,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
