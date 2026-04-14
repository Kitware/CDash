<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\Build;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class CreateCommentTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    public function testCreateComment(): void
    {
        $project = $this->makePublicProject();

        /** @var Build $build */
        $build = $project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $user = $this->makeNormalUser();

        $text = Str::uuid()->toString();
        $response = $this->actingAs($user)->graphQL('
            mutation CreateComment($input: CreateCommentInput!) {
                createComment(input: $input) {
                    comment {
                        text
                        user {
                            id
                        }
                    }
                }
            }
        ', [
            'input' => [
                'buildId' => $build->id,
                'text' => $text,
            ],
        ]);

        $response->assertExactJson([
            'data' => [
                'createComment' => [
                    'comment' => [
                        'text' => $text,
                        'user' => [
                            'id' => (string) $user->id,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertDatabaseHas('comments', [
            'buildid' => $build->id,
            'userid' => $user->id,
            'text' => $text,
        ]);
    }

    public function testCreateCommentUnauthenticated(): void
    {
        $project = $this->makePublicProject();

        /** @var Build $build */
        $build = $project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            mutation CreateComment($input: CreateCommentInput!) {
                createComment(input: $input) {
                    comment {
                        id
                    }
                }
            }
        ', [
            'input' => [
                'buildId' => $build->id,
                'text' => Str::uuid()->toString(),
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        $this->assertDatabaseEmpty('comments');
    }

    public function testCreateCommentUnauthorizedForBuild(): void
    {
        $project = $this->makePrivateProject();

        /** @var Build $build */
        $build = $project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $user = $this->makeNormalUser();
        $this->actingAs($user)->graphQL('
            mutation CreateComment($input: CreateCommentInput!) {
                createComment(input: $input) {
                    comment {
                        id
                    }
                }
            }
        ', [
            'input' => [
                'buildId' => $build->id,
                'text' => Str::uuid()->toString(),
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        $this->assertDatabaseEmpty('comments');
    }
}
