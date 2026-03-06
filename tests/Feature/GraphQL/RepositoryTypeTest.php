<?php

namespace Tests\Feature\GraphQL;

use App\Models\Repository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class RepositoryTypeTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    /**
     * @return array{
     *     array{
     *         string, mixed, string, mixed,
     *     }
     * }
     */
    public static function fieldValues(): array
    {
        $repository = Repository::factory()->make();

        return [
            ['url', $repository->url, 'url', $repository->url],
            ['username', $repository->username, 'username', $repository->username],
            ['branch', $repository->branch, 'branch', $repository->branch],
        ];
    }

    /**
     * A basic test to ensure that each of the non-relationship fields works
     */
    #[DataProvider('fieldValues')]
    public function testBasicFieldAccess(string $modelField, mixed $modelValue, string $graphqlField, mixed $graphqlValue): void
    {
        $project = $this->makePublicProject();
        $repository = $project->repositories()->save(Repository::factory()->make());
        self::assertInstanceOf(Repository::class, $repository);
        $repository->setAttribute($modelField, $modelValue);
        $repository->save();

        $this->graphQL("
            query project(\$id: ID) {
                project(id: \$id) {
                    repositories {
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
            'id' => $project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'repositories' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $repository->id,
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
