<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class TestFileDownloadTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    public function testDownloadsAttachedFile(): void
    {
        $test = $this->makePublicProject()->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'passed',
        ]);
        $contents = Str::random(100);
        $test->testMeasurements()->create([
            'name' => 'output.txt',
            'type' => 'file',
            'value' => base64_encode($contents),
        ]);

        $_GET['fileid'] = '1'; // The legacy controller reads the query parameter from $_GET.
        $response = $this->get("/api/v1/testDetails.php?buildtestid={$test->id}&fileid=1");
        unset($_GET['fileid']);

        $response->assertOk();
        self::assertSame($contents, $response->streamedContent());
    }
}
