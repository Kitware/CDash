<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PruneEmails;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class PruneEmailsTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    protected Project $project;
    protected User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->user = $this->makeNormalUser();
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->user->delete();

        parent::tearDown();
    }

    public function testDeletesOldEmails(): void
    {
        $email_to_keep = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->emails()->create([
            'userid' => $this->user->id,
            'category' => 0,
            'time' => Carbon::now()->subHours(47),
        ]);

        $email_to_delete = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->emails()->create([
            'userid' => $this->user->id,
            'category' => 0,
            'time' => Carbon::now()->subHours(49),
        ]);

        self::assertModelExists($email_to_delete);
        self::assertModelExists($email_to_keep);
        PruneEmails::dispatch();
        self::assertModelMissing($email_to_delete);
        self::assertModelExists($email_to_keep);
    }
}
