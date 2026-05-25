<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Jobs\ComputeCoverageDifference;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;
use Tests\Traits\CreatesUsers;

class CreateCoverageDiffMutationTest extends TestCase
{
    use CreatesProjects;
    use CreatesSites;
    use CreatesUsers;
    use DatabaseTransactions;

    private Project $project;
    private User $user;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = $this->makePublicProject();
        $this->user = $this->makeNormalUser();
        $this->site = $this->makeSite();
    }

    private function coverageDiffMutation(): string
    {
        return '
            mutation createCoverageDiff($input: CreateCoverageDiffInput!) {
                createCoverageDiff(input: $input) {
                    message
                }
            }
        ';
    }

    public function testCreateCoverageDiffMutation(): void
    {
        Queue::fake();

        $this->project->users()->attach($this->user->id);

        $baseBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $compareBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $this->actingAs($this->user)
            ->graphQL($this->coverageDiffMutation(), [
                'input' => [
                    'baseBuildId' => $baseBuild->id,
                    'compareBuildId' => $compareBuild->id,
                ],
            ])
            ->assertGraphQLErrorFree();

        Queue::assertPushed(ComputeCoverageDifference::class, fn ($job) => $job->baseBuild->id === $baseBuild->id && $job->compareBuild->id === $compareBuild->id);
    }

    public function testCreateCoverageDiffFailsDifferentProjects(): void
    {
        Queue::fake();

        $this->project->users()->attach($this->user->id);

        $project2 = $this->makePublicProject();

        $baseBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $compareBuild = $project2->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $this->actingAs($this->user)
            ->graphQL($this->coverageDiffMutation(), [
                'input' => [
                    'baseBuildId' => $baseBuild->id,
                    'compareBuildId' => $compareBuild->id,
                ],
            ])
            ->assertGraphQLErrorMessage('Builds must belong to the same project.');

        Queue::assertNotPushed(ComputeCoverageDifference::class);
    }

    public function testCreateCoverageDiffFailsUnauthorized(): void
    {
        Queue::fake();

        $privateProject = $this->makePrivateProject();
        $baseBuild = $privateProject->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);
        $compareBuild = $privateProject->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $this->actingAs($this->user)
            ->graphQL($this->coverageDiffMutation(), [
                'input' => [
                    'baseBuildId' => $baseBuild->id,
                    'compareBuildId' => $compareBuild->id,
                ],
            ])
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        Queue::assertNotPushed(ComputeCoverageDifference::class);
    }

    public function testCreateCoverageDiffFailsBuildNotFound(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->graphQL($this->coverageDiffMutation(), [
                'input' => [
                    'baseBuildId' => 99999,
                    'compareBuildId' => 100000,
                ],
            ])
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        Queue::assertNotPushed(ComputeCoverageDifference::class);
    }

    public function testCreateCoverageDiffAdminBuildNotFound(): void
    {
        Queue::fake();

        // Admins bypass the null-project policy check, so they proceed to the
        // same-project validation which fails when the builds do not exist.
        $this->actingAs($this->makeAdminUser())
            ->graphQL($this->coverageDiffMutation(), [
                'input' => [
                    'baseBuildId' => 999,
                    'compareBuildId' => 1000,
                ],
            ])
            ->assertGraphQLErrorMessage('Builds must belong to the same project.');

        Queue::assertNotPushed(ComputeCoverageDifference::class);
    }

    public function testCreateCoverageDiffFailsSameBuild(): void
    {
        Queue::fake();

        $this->project->users()->attach($this->user->id);

        $baseBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $this->actingAs($this->user)
            ->graphQL($this->coverageDiffMutation(), [
                'input' => [
                    'baseBuildId' => $baseBuild->id,
                    'compareBuildId' => $baseBuild->id,
                ],
            ])
            ->assertGraphQLValidationKeys(['input.compareBuildId']);

        Queue::assertNotPushed(ComputeCoverageDifference::class);
    }

    public function testCreateCoverageDiffAdminSuccess(): void
    {
        Queue::fake();

        $baseBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $compareBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $this->actingAs($this->makeAdminUser())
            ->graphQL($this->coverageDiffMutation(), [
                'input' => [
                    'baseBuildId' => $baseBuild->id,
                    'compareBuildId' => $compareBuild->id,
                ],
            ])
            ->assertGraphQLErrorFree();

        Queue::assertPushed(ComputeCoverageDifference::class);
    }

    public function testCreateCoverageDiffNonMemberPublicProjectFails(): void
    {
        Queue::fake();

        $baseBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $compareBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $this->actingAs($this->user)
            ->graphQL($this->coverageDiffMutation(), [
                'input' => [
                    'baseBuildId' => $baseBuild->id,
                    'compareBuildId' => $compareBuild->id,
                ],
            ])
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        Queue::assertNotPushed(ComputeCoverageDifference::class);
    }
}
