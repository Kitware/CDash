<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PruneSubmissions;
use App\Models\Project;
use App\Models\Submission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class PruneSubmissionsTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    public function testExpiredSubmissionDeleted(): void
    {
        $submission = Submission::factory()->done()->create([
            'projectid' => $this->project->id,
            'received_at' => Carbon::now()->subHours(1001),
            'finished_at' => Carbon::now()->subHours(1000),
        ]);

        PruneSubmissions::dispatch();

        $this->assertModelMissing($submission);
    }

    public function testRecentSubmissionNotDeleted(): void
    {
        $submission = Submission::factory()->done()->create([
            'projectid' => $this->project->id,
            'received_at' => Carbon::now()->subHours(1),
        ]);

        PruneSubmissions::dispatch();

        $this->assertModelExists($submission);
    }

    public function testUnfinishedSubmissionDeletedAfterLifetime(): void
    {
        Config::set('cdash.backup_timeframe', 48);
        $expired = Submission::factory()->create([
            'projectid' => $this->project->id,
            'received_at' => Carbon::now()->subHours(49),
        ]);
        $recent = Submission::factory()->create([
            'projectid' => $this->project->id,
            'received_at' => Carbon::now()->subHours(30),
        ]);

        PruneSubmissions::dispatch();

        $this->assertModelMissing($expired);
        $this->assertModelExists($recent);
    }

    public function testUnfinishedSubmissionKeptForADayWithoutBackups(): void
    {
        Config::set('cdash.backup_timeframe', 0);
        $finished = Submission::factory()->done()->create([
            'projectid' => $this->project->id,
            'received_at' => Carbon::now()->subHours(2),
            'finished_at' => Carbon::now()->subHours(1),
        ]);
        $unfinished = Submission::factory()->create([
            'projectid' => $this->project->id,
            'received_at' => Carbon::now()->subHours(2),
        ]);
        $expired = Submission::factory()->create([
            'projectid' => $this->project->id,
            'received_at' => Carbon::now()->subHours(25),
        ]);

        PruneSubmissions::dispatch();

        $this->assertModelMissing($finished);
        $this->assertModelExists($unfinished);
        $this->assertModelMissing($expired);
    }
}
