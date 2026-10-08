<?php

namespace Tests\Feature\Console;

use App\Jobs\ProcessSubmission;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\QueueFake;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class QueueSubmissionsCommandTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private const MD5 = 'd41d8cd98f00b204e9800998ecf8427e';

    private Project $project;

    private QueueFake $queue;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->queue = Queue::fake();

        $this->project = $this->makePublicProject();
    }

    public function testQueuesXmlFileWithMd5(): void
    {
        $filename = "{$this->project->name}_-__-_" . Str::uuid()->toString() . '_-_' . self::MD5 . '.xml';
        Storage::put("inbox/$filename", '');

        Artisan::call('submission:queue');

        Queue::assertCount(1);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->filename === $filename
            && $job->projectid === $this->project->id
            && $job->buildid === null
            && $job->expected_md5 === self::MD5);
    }

    public function testQueuesBuildMetadataBeforeDataFile(): void
    {
        $uuid = Str::uuid()->toString();
        $data_filename = "{$this->project->name}_-__-_BazelJSON_-_{$uuid}_-_" . self::MD5 . '_-_.json';
        $metadata_filename = "{$this->project->name}_-__-_build-metadata_-_{$uuid}_-__-_.json";
        Storage::put("inbox/$data_filename", '');
        Storage::put("inbox/$metadata_filename", '');

        Artisan::call('submission:queue');

        /** @var Collection<int,ProcessSubmission> $jobs */
        $jobs = $this->queue->pushed(ProcessSubmission::class);
        $this->assertSame([
            [$metadata_filename, $this->project->id, null, ''],
            [$data_filename, $this->project->id, null, self::MD5],
        ], $jobs->map(fn (ProcessSubmission $job): array => [
            $job->filename,
            $job->projectid,
            $job->buildid,
            $job->expected_md5,
        ])->all());
    }

    public function testMovesMalformedFilenameToFailed(): void
    {
        Storage::put('inbox/malformed.xml', '');

        $this->expectOutputString("Could not parse malformed.xml\n");
        Artisan::call('submission:queue');

        Queue::assertNothingPushed();
        $this->assertSame(['failed/malformed.xml'], Storage::allFiles());
    }
}
