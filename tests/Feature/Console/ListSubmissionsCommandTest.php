<?php

namespace Tests\Feature\Console;

use App\Enums\SubmissionStatus;
use App\Models\Build;
use App\Models\Project;
use App\Models\Site;
use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class ListSubmissionsCommandTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private Project $project;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->site = Site::factory()->create();
    }

    private function makeBuild(string $name, ?Site $site = null, ?Project $project = null): Build
    {
        return ($project ?? $this->project)->builds()->create([
            'name' => $name,
            'uuid' => Str::uuid()->toString(),
            'siteid' => ($site ?? $this->site)->id,
            'starttime' => '2026-11-20 12:00:00',
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function makeSubmission(string $received_at, ?Build $build = null, array $attributes = []): Submission
    {
        return Submission::factory()->create([
            'projectid' => $build->projectid ?? $this->project->id,
            'buildid' => $build?->id,
            'received_at' => Carbon::parse($received_at, 'UTC'),
        ] + $attributes);
    }

    /**
     * Run submission:list for the project.
     *
     * @param array<string, mixed> $options
     */
    private function listCommand(array $options = []): PendingCommand
    {
        $command = $this->artisan('submission:list', $options + ['--project' => $this->project->name]);
        self::assertInstanceOf(PendingCommand::class, $command);
        return $command;
    }

    /**
     * List the project's submissions, and return the output.
     *
     * @param array<string, mixed> $options
     */
    private function listSubmissions(array $options = []): string
    {
        self::assertSame(Command::SUCCESS, Artisan::call('submission:list', $options + ['--project' => $this->project->name]));
        return Artisan::output();
    }

    /**
     * The ids of the submissions in a listing, in order.
     *
     * @return array<string>
     */
    private static function listedIds(string $output): array
    {
        preg_match_all('/^\| ([0-9a-f-]{36}) /m', $output, $matches);
        return $matches[1];
    }

    public function testListsSubmissionsNewestFirst(): void
    {
        $build = $this->makeBuild('linux-release');
        $configure = $this->makeSubmission('2026-11-20 12:00:00', $build, [
            'type' => 'Configure',
            'status' => SubmissionStatus::DONE,
            'attempts' => 1,
        ]);
        $build_file = $this->makeSubmission('2026-11-20 12:05:00', $build, [
            'type' => 'Build',
            'status' => SubmissionStatus::FAILED,
            'attempts' => 1,
            'error' => "Unable to read the file\nMore detail on the second line",
        ]);
        $test = $this->makeSubmission('2026-11-20 12:10:00', $build, [
            'type' => 'Test',
            'status' => SubmissionStatus::FAILED,
            'attempts' => 1,
            'error' => 'Unable to parse the file: premature end of data in tag Testing on line 812',
        ]);
        $coverage = $this->makeSubmission('2026-11-20 12:15:00', null, ['type' => 'GcovTar']);
        $other_submission = Submission::factory()->create(['projectid' => $this->makePublicProject()->id]);

        $this->listCommand()
            ->expectsTable(['ID', 'Received (UTC)', 'Project', 'Build', 'File type', 'Status', 'Attempts', 'Error'], [
                [$coverage->id, '2026-11-20 12:15:00', $this->project->name, '—', 'GcovTar', 'queued', 0, ''],
                [$test->id, '2026-11-20 12:10:00', $this->project->name, "linux-release ({$build->id})", 'Test', 'failed', 1,
                    'Unable to parse the file: premature end of data in tag Testi…'],
                [$build_file->id, '2026-11-20 12:05:00', $this->project->name, "linux-release ({$build->id})", 'Build', 'failed', 1,
                    'Unable to read the file'],
                [$configure->id, '2026-11-20 12:00:00', $this->project->name, "linux-release ({$build->id})", 'Configure', 'done', 1, ''],
            ])
            ->doesntExpectOutputToContain('second line')
            ->doesntExpectOutputToContain($other_submission->id)
            ->doesntExpectOutputToContain('Showing')
            ->assertSuccessful();
    }

    public function testListsSubmissionsToEveryProject(): void
    {
        $site = Site::factory()->create();
        $build = $this->makeBuild('linux-release', $site);
        $mine = $this->makeSubmission('2026-11-20 12:00:00', $build);
        $other_project = $this->makePublicProject();
        $other_build = $this->makeBuild('linux-release', $site, $other_project);
        $theirs = $this->makeSubmission('2026-11-20 12:01:00', $other_build);

        $command = $this->artisan('submission:list', ['--site' => $site->name]);
        self::assertInstanceOf(PendingCommand::class, $command);
        $command
            ->expectsTable(['ID', 'Received (UTC)', 'Project', 'Build', 'File type', 'Status', 'Attempts', 'Error'], [
                [$theirs->id, '2026-11-20 12:01:00', $other_project->name, "linux-release ({$other_build->id})", 'Build', 'queued', 0, ''],
                [$mine->id, '2026-11-20 12:00:00', $this->project->name, "linux-release ({$build->id})", 'Build', 'queued', 0, ''],
            ])
            ->assertSuccessful();
    }

    public function testFiltersByBuildName(): void
    {
        $here = $this->makeSubmission('2026-11-20 12:00:00', $this->makeBuild('linux-release'));
        $elsewhere = $this->makeSubmission('2026-11-20 12:01:00', $this->makeBuild('linux-release', Site::factory()->create()));
        $this->makeSubmission('2026-11-20 12:02:00', $this->makeBuild('windows-debug'));
        $this->makeSubmission('2026-11-20 12:03:00');

        self::assertSame([$elsewhere->id, $here->id], self::listedIds($this->listSubmissions(['--build' => 'linux-release'])));
    }

    public function testFiltersByBuildId(): void
    {
        $build = $this->makeBuild('linux-release');
        $listed = $this->makeSubmission('2026-11-20 12:00:00', $build);
        $this->makeSubmission('2026-11-20 12:01:00', $this->makeBuild('linux-release'));
        $this->makeSubmission('2026-11-20 12:02:00');

        self::assertSame([$listed->id], self::listedIds($this->listSubmissions(['--build-id' => (string) $build->id])));
    }

    public function testFiltersBySite(): void
    {
        $listed = $this->makeSubmission('2026-11-20 12:00:00', $this->makeBuild('linux-release'));
        $this->makeSubmission('2026-11-20 12:01:00', $this->makeBuild('linux-release', Site::factory()->create()));
        $this->makeSubmission('2026-11-20 12:02:00');

        self::assertSame([$listed->id], self::listedIds($this->listSubmissions(['--site' => $this->site->name])));
    }

    public function testFiltersByTypes(): void
    {
        $this->makeSubmission('2026-11-20 12:00:00', null, ['type' => 'Configure']);
        $test = $this->makeSubmission('2026-11-20 12:01:00', null, ['type' => 'Test']);
        $coverage = $this->makeSubmission('2026-11-20 12:02:00', null, ['type' => 'GcovTar']);
        $this->makeSubmission('2026-11-20 12:03:00', null, ['type' => 'TestJUnit']);

        self::assertSame([$coverage->id, $test->id], self::listedIds($this->listSubmissions(['--type' => ['test', 'GCOVTAR']])));
    }

    public function testFiltersByStatuses(): void
    {
        $queued = $this->makeSubmission('2026-11-20 12:00:00', null, ['status' => SubmissionStatus::QUEUED]);
        $this->makeSubmission('2026-11-20 12:01:00', null, ['status' => SubmissionStatus::PROCESSING]);
        $this->makeSubmission('2026-11-20 12:02:00', null, ['status' => SubmissionStatus::DONE]);
        $failed = $this->makeSubmission('2026-11-20 12:03:00', null, ['status' => SubmissionStatus::FAILED]);

        self::assertSame([$failed->id, $queued->id], self::listedIds($this->listSubmissions(['--status' => ['failed', 'QUEUED']])));
    }

    public function testFiltersByDateReceived(): void
    {
        $this->makeSubmission('2026-11-19 23:59:59');
        $midnight = $this->makeSubmission('2026-11-20 00:00:00');
        $later = $this->makeSubmission('2026-11-20 08:00:00');

        self::assertSame([$later->id, $midnight->id], self::listedIds($this->listSubmissions(['--since' => '2026-11-20'])));
        self::assertSame([$later->id, $midnight->id], self::listedIds($this->listSubmissions([
            '--since' => '2026-11-20T03:00:00+03:00',
        ])));
    }

    public function testFiltersByRelativeTimeReceived(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00', 'UTC'));
        $this->makeSubmission('2026-11-20 08:59:59');
        $listed = $this->makeSubmission('2026-11-20 09:00:00');

        self::assertSame([$listed->id], self::listedIds($this->listSubmissions(['--since' => '3 hours ago'])));
    }

    public function testCombinesFilters(): void
    {
        $build = $this->makeBuild('linux-release');
        $listed = $this->makeSubmission('2026-11-20 12:00:00', $build, ['status' => SubmissionStatus::FAILED]);
        $this->makeSubmission('2026-11-20 12:01:00', $build, ['status' => SubmissionStatus::DONE]);
        $this->makeSubmission('2026-11-20 12:02:00', $this->makeBuild('linux-release', Site::factory()->create()), [
            'status' => SubmissionStatus::FAILED,
        ]);

        self::assertSame([$listed->id], self::listedIds($this->listSubmissions(['--site' => $this->site->name, '--status' => ['failed']])));
    }

    public function testListsUpToLimitAndSaysHowManyMatch(): void
    {
        $this->makeSubmission('2026-11-20 12:00:00');
        $middle = $this->makeSubmission('2026-11-20 12:01:00');
        $newest = $this->makeSubmission('2026-11-20 12:02:00');

        $output = $this->listSubmissions(['--limit' => '2']);
        self::assertSame([$newest->id, $middle->id], self::listedIds($output));
        self::assertStringContainsString('Showing 2 of 3 submissions. Use --limit to see more.', $output);
        self::assertStringNotContainsString('Showing', $this->listSubmissions(['--limit' => '3']));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function unmatchedOptions(): array
    {
        return [
            'type' => [['--type' => ['Test']]],
            'status' => [['--status' => ['failed']]],
            'build name' => [['--build' => 'windows-debug']],
            'build id' => [['--build-id' => '999999999']],
            'site' => [['--site' => 'Unknown site']],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('unmatchedOptions')]
    public function testSaysWhenNoSubmissionsMatch(array $options): void
    {
        $this->makeSubmission('2026-11-20 12:00:00', $this->makeBuild('linux-release'), ['status' => SubmissionStatus::DONE]);

        $this->listCommand($options)
            ->expectsOutput('No submissions match.')
            ->assertSuccessful();
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidOptions(): array
    {
        return [
            'unknown project' => [['--project' => 'does-not-exist'], 'Project does-not-exist does not exist'],
            'unknown type' => [
                ['--type' => ['Testing']],
                'Unknown type Testing. Valid types are: BazelJSON, Build, BuildPropertiesJSON, Configure, Coverage, '
                . 'CoverageJUnit, CoverageLog, Done, DynamicAnalysis, GcovTar, JavaJSONTar, JSCoverTar, Notes, '
                . 'OpenCoverTar, Project, SubProjectDirectories, Test, TestJUnit, Update, Upload',
            ],
            'unknown status' => [
                ['--status' => ['finished']],
                'Unknown status finished. Valid statuses are: queued, processing, done, failed',
            ],
            'unreadable time' => [['--since' => 'not a time'], "Can't read --since=not a time as a time"],
            'zero limit' => [['--limit' => '0'], '--limit must be a positive integer'],
            'non-numeric limit' => [['--limit' => 'all'], '--limit must be a positive integer'],
            'zero build id' => [['--build-id' => '0'], '--build-id must be a positive integer'],
            'non-numeric build id' => [['--build-id' => 'linux-release'], '--build-id must be a positive integer'],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidOptions')]
    public function testRejectsInvalidOption(array $options, string $message): void
    {
        $this->listCommand($options)
            ->expectsOutput($message)
            ->assertFailed();
    }
}
