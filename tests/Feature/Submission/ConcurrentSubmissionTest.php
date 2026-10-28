<?php

namespace Tests\Feature\Submission;

use App\Exceptions\BadSubmissionException;
use App\Jobs\ProcessSubmission;
use App\Models\Build;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class ConcurrentSubmissionTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private const LOCK_HOLDER_CONNECTION = 'concurrent_submission_test_lock_holder';

    private Project $project;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        $this->project = $this->makePublicProject();
        $this->site = Site::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    private function createBuild(): Build
    {
        return $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);
    }

    /**
     * @throws BadSubmissionException
     */
    private function processDoneFile(Build $build): void
    {
        $filename = Str::uuid()->toString() . '.xml';
        Storage::put("inbox/{$filename}", '<?xml version="1.0" encoding="UTF-8"?><Done><buildId>'
            . $build->id . '</buildId><time>' . time() . '</time></Done>');

        (new ProcessSubmission($filename, $this->project->id, $build->id, ''))->handle();
    }

    /**
     * @throws BadSubmissionException
     */
    public function testWaitsForOtherSubmissionsToTheSameBuild(): void
    {
        $locked_build = $this->createBuild();
        $other_build = $this->createBuild();

        // Hold the lock for $locked_build from another connection, as another worker would.
        config(['database.connections.' . self::LOCK_HOLDER_CONNECTION => config('database.connections.' . DB::getDefaultConnection())]);
        $lock_holder = DB::connection(self::LOCK_HOLDER_CONNECTION);
        $lock_holder->select('SELECT pg_advisory_lock(?)', [$locked_build->id]);

        // Give up waiting for the lock instead of blocking forever.
        DB::statement("SET LOCAL lock_timeout = '500ms'");

        try {
            $this->processDoneFile($other_build);
            $this->assertTrue($other_build->fresh()?->done);

            $this->assertThrows(fn () => $this->processDoneFile($locked_build), QueryException::class, 'lock timeout');
            $this->assertFalse($locked_build->fresh()?->done);
        } finally {
            $lock_holder->select('SELECT pg_advisory_unlock(?)', [$locked_build->id]);
            DB::purge(self::LOCK_HOLDER_CONNECTION);
        }

        $this->processDoneFile($locked_build);
        $this->assertTrue($locked_build->fresh()?->done);
    }
}
