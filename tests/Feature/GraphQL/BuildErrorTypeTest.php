<?php

namespace Tests\Feature\GraphQL;

use App\Models\Build;
use App\Models\BuildError;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Random\RandomException;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class BuildErrorTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    /**
     * @return array{
     *     array{
     *         string, mixed, string, mixed,
     *     }
     * }
     *
     * @throws RandomException
     */
    public static function fieldValues(): array
    {
        $random_string = Str::uuid()->toString();
        $random_int = random_int(0, 100000);

        return [
            ['workingdirectory', $random_string, 'workingDirectory', $random_string],
            ['workingdirectory', null, 'workingDirectory', null],
            ['sourcefile', $random_string, 'sourceFile', $random_string],
            ['type', 0, 'type', 'ERROR'],
            ['type', 1, 'type', 'WARNING'],
            ['stdoutput', $random_string, 'stdOutput', $random_string],
            ['stderror', $random_string, 'stdError', $random_string],
            ['exitcondition', $random_string, 'exitCondition', $random_string],
            ['exitcondition', null, 'exitCondition', null],
            ['language', $random_string, 'language', $random_string],
            ['language', null, 'language', null],
            ['targetname', $random_string, 'targetName', $random_string],
            ['targetname', null, 'targetName', null],
            ['outputfile', $random_string, 'outputFile', $random_string],
            ['outputfile', null, 'outputFile', null],
            ['outputtype', $random_string, 'outputType', $random_string],
            ['outputtype', null, 'outputType', null],
            ['logline', $random_int, 'logLine', $random_int],
            ['logline', null, 'logLine', null],
            ['sourceline', $random_int, 'sourceLine', $random_int],
            ['sourceline', null, 'sourceLine', null],
        ];
    }

    /**
     * A basic test to ensure that each of the non-relationship fields works
     */
    #[DataProvider('fieldValues')]
    public function testBuildErrorFields(string $modelField, mixed $modelValue, string $graphqlField, mixed $graphqlValue): void
    {
        $project = $this->makePublicProject();

        /** @var Build $build */
        $build = $project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError */
        $buildError = $build->buildErrors()->save(BuildError::factory()->make([
            $modelField => $modelValue,
        ]));

        $this->graphQL("
            query build(\$id: ID) {
                build(id: \$id) {
                    buildErrors {
                        edges {
                            node {
                                id
                                $graphqlField
                            }
                        }
                    }
                }
            }
        ", [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'buildErrors' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $buildError->id,
                                    $graphqlField => $graphqlValue,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
