<?php

namespace Tests\Feature\GraphQL;

use App\Models\Build;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class CommentTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    private Project $project;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->user = $this->makeNormalUser();
    }

    /**
     * @return array{
     *     array{
     *         string, mixed, string, mixed,
     *     }
     * }
     */
    public static function fieldValues(): array
    {
        $text = Str::uuid()->toString();
        $timestamp = Carbon::now()->round('second');
        return [
            ['text', $text, 'text', $text],
            ['timestamp', $timestamp, 'timestamp', $timestamp->toIso8601String()],
        ];
    }

    /**
     * A basic test to ensure that each of the non-relationship fields works
     */
    #[DataProvider('fieldValues')]
    public function testBasicFieldAccess(string $modelField, mixed $modelValue, string $graphqlField, mixed $graphqlValue): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Comment $comment */
        $comment = $build->comments()->save(Comment::factory()->make([
            'userid' => $this->user->id,
            $modelField => $modelValue,
        ]));

        $this->graphQL("
            query build(\$id: ID) {
                build(id: \$id) {
                    comments {
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
                    'comments' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $comment->id,
                                    $graphqlField => $graphqlValue,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testUserRelationship(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Comment $comment */
        $comment = $build->comments()->save(Comment::factory()->make([
            'userid' => $this->user->id,
        ]));

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    comments {
                        edges {
                            node {
                                id
                                user {
                                    id
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
                    'comments' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $comment->id,
                                    'user' => [
                                        'id' => (string) $this->user->id,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testOrdersByTimestamp(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Comment $comment1 */
        $comment1 = $build->comments()->save(Comment::factory()->make([
            'userid' => $this->user->id,
            'timestamp' => Carbon::now()->subHour()->round('second'),
        ]));

        /** @var Comment $comment2 */
        $comment2 = $build->comments()->save(Comment::factory()->make([
            'userid' => $this->user->id,
            'timestamp' => Carbon::now()->subDay()->round('second'),
        ]));

        /** @var Comment $comment3 */
        $comment3 = $build->comments()->save(Comment::factory()->make([
            'userid' => $this->user->id,
            'timestamp' => Carbon::now()->round('second'),
        ]));

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    comments {
                        edges {
                            node {
                                id
                                timestamp
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
                    'comments' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $comment3->id,
                                    'timestamp' => $comment3->timestamp->toIso8601String(),
                                ],
                            ],
                            [
                                'node' => [
                                    'id' => (string) $comment1->id,
                                    'timestamp' => $comment1->timestamp->toIso8601String(),
                                ],
                            ],
                            [
                                'node' => [
                                    'id' => (string) $comment2->id,
                                    'timestamp' => $comment2->timestamp->toIso8601String(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
