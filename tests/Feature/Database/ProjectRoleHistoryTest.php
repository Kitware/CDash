<?php

namespace Tests\Feature\Database;

use App\Enums\ProjectRole;
use App\Models\ProjectRoleHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class ProjectRoleHistoryTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    public function testTriggerLogsInsert(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();

        $project->users()->attach($user->id, [
            'role' => ProjectRole::USER,
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => 0,
            'emailmissingsites' => 0,
        ]);

        $history = ProjectRoleHistory::firstOrFail();
        $this->assertTrue($history->user->is($user));
        $this->assertTrue($history->project->is($project));
        $this->assertEquals(ProjectRole::USER, $history->role);
    }

    public function testTriggerLogsUpdate(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();

        $project->users()->attach($user->id, [
            'role' => ProjectRole::USER,
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => 0,
            'emailmissingsites' => 0,
        ]);

        $project->users()->updateExistingPivot($user->id, ['role' => ProjectRole::ADMINISTRATOR]);

        $this->assertEquals(2, ProjectRoleHistory::count());
        $history = ProjectRoleHistory::latest('id')->firstOrFail();
        $this->assertTrue($history->user->is($user));
        $this->assertTrue($history->project->is($project));
        $this->assertEquals(ProjectRole::ADMINISTRATOR, $history->role);
    }

    public function testTriggerLogsDelete(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();

        $project->users()->attach($user->id, [
            'role' => ProjectRole::USER,
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => 0,
            'emailmissingsites' => 0,
        ]);

        $project->users()->detach($user->id);

        $this->assertEquals(2, ProjectRoleHistory::count());
        $history = ProjectRoleHistory::latest('id')->firstOrFail();
        $this->assertTrue($history->user->is($user));
        $this->assertTrue($history->project->is($project));
        $this->assertNull($history->role);
    }

    public function testTriggerDoesNotLogUpdateWithoutRoleChange(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();

        $project->users()->attach($user->id, [
            'role' => ProjectRole::USER,
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => 0,
            'emailmissingsites' => 0,
        ]);

        $project->users()->updateExistingPivot($user->id, ['emailtype' => 1]);

        $this->assertEquals(1, ProjectRoleHistory::count());
    }

    public function testTriggerDoesNotCrashOnProjectDelete(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();

        $project->users()->attach($user->id, [
            'role' => ProjectRole::USER,
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => 0,
            'emailmissingsites' => 0,
        ]);

        $this->assertEquals(1, ProjectRoleHistory::count());

        $project->delete();

        // ProjectRoleHistory should be empty because of ON DELETE CASCADE.
        $this->assertEquals(0, ProjectRoleHistory::count());
    }

    public function testTriggerDoesNotCrashOnUserDelete(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();

        $project->users()->attach($user->id, [
            'role' => ProjectRole::USER,
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => 0,
            'emailmissingsites' => 0,
        ]);

        $this->assertEquals(1, ProjectRoleHistory::count());

        $user->delete();

        // ProjectRoleHistory should be empty because of ON DELETE CASCADE.
        $this->assertEquals(0, ProjectRoleHistory::count());
    }
}
