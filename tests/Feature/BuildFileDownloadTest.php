<?php

namespace Tests\Feature;

use App\Models\UploadFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class BuildFileDownloadTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    public function testDownloadsUploadedFile(): void
    {
        Storage::fake();
        $contents = Str::random(100);
        $sha1sum = sha1($contents);
        Storage::put("upload/{$sha1sum}", $contents);

        $build = $this->makePublicProject()->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);
        $file = UploadFile::create([
            'filename' => 'output.txt',
            'filesize' => strlen($contents),
            'sha1sum' => $sha1sum,
            'isurl' => false,
        ]);
        $build->uploadedFiles()->attach($file);

        $response = $this->get("/builds/{$build->id}/files/{$file->id}");

        $response->assertOk();
        self::assertSame($contents, $response->streamedContent());
    }
}
