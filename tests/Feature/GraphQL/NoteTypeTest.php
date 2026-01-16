<?php

namespace Tests\Feature\GraphQL;

use App\Models\Note;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Random\RandomException;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class NoteTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    private Project $public_project;
    private Project $private_project;

    /**
     * Only submitted to the public project
     */
    private Note $note1;

    /**
     * Only submitted to the private project
     */
    private Note $note2;

    /**
     * Submitted to both the public and private projects
     */
    private Note $note3;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->public_project = $this->makePublicProject();
        $this->private_project = $this->makePrivateProject();

        $this->note1 = Note::create([
            'name' => Str::uuid()->toString(),
            'text' => Str::uuid()->toString(),
        ]);

        $this->note2 = Note::create([
            'name' => Str::uuid()->toString(),
            'text' => Str::uuid()->toString(),
        ]);

        $this->note3 = Note::create([
            'name' => Str::uuid()->toString(),
            'text' => Str::uuid()->toString(),
        ]);

        $this->public_project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ])->notes()->attach([
            $this->note1->id,
            $this->note3->id,
        ]);

        $this->private_project->builds()->create([
            'name' => 'build2',
            'uuid' => Str::uuid()->toString(),
        ])->notes()->attach([
            $this->note2->id,
            $this->note3->id,
        ]);
    }

    protected function tearDown(): void
    {
        $this->public_project->delete();
        $this->private_project->delete();

        $this->note1->delete();
        $this->note2->delete();
        $this->note3->delete();

        parent::tearDown();
    }

    /**
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                name
                                notes {
                                    edges {
                                        node {
                                            id
                                            name
                                            text
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $this->public_project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'build1',
                                    'notes' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => (string) $this->note3->id,
                                                    'name' => $this->note3->name,
                                                    'text' => $this->note3->text,
                                                ],
                                            ],
                                            [
                                                'node' => [
                                                    'id' => (string) $this->note1->id,
                                                    'name' => $this->note1->name,
                                                    'text' => $this->note1->text,
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
}
