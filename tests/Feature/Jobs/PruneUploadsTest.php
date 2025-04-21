<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PruneUploads;
use App\Models\Build;
use App\Models\Project;
use App\Models\UploadFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

/**
 * Note: these tests are not as robust as they could be, and should be extended in the future...
 */
class PruneUploadsTest extends TestCase
{
    use CreatesProjects;

    private Project $project;

    /** @var array<UploadFile> */
    private array $filesToDelete = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        foreach ($this->filesToDelete as $file) {
            try {
                Storage::delete($file->file());
            } catch (FileNotFoundException) {
            }
            $file->delete();
        }
        $this->filesToDelete = [];

        parent::tearDown();
    }

    public function testProjectNoUploadedFiles(): void
    {
        self::expectNotToPerformAssertions();
        PruneUploads::dispatch();
    }

    public function testUploadedFilesBelowLimit(): void
    {
        $this->project->uploadquota = 2147483648;
        $this->project->save();

        $hash = sha1_file(__FILE__);

        // We need an example of an "uploaded file", so just use the current file...
        File::copy(__FILE__, storage_path("app/upload/$hash"));

        self::assertFileExists(storage_path("app/upload/$hash"));

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->filesToDelete[] = $build->uploadedFiles()->create([
            'filename' => Str::uuid()->toString(),
            'filesize' => 123,
            'sha1sum' => $hash,
            'isurl' => false,
        ]);

        self::assertDatabaseHas(UploadFile::class, [
            'sha1sum' => $hash,
        ]);

        PruneUploads::dispatch();

        self::assertDatabaseHas(UploadFile::class, [
            'sha1sum' => $hash,
        ]);

        self::assertFileExists(storage_path("app/upload/$hash"));
    }

    public function testUploadedFilesAboveLimit(): void
    {
        $this->project->uploadquota = 1073741824;
        $this->project->save();

        $hash = sha1_file(__FILE__);

        // We need an example of an "uploaded file", so just use the current file...
        File::copy(__FILE__, storage_path("app/upload/$hash"));

        self::assertFileExists(storage_path("app/upload/$hash"));

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->filesToDelete[] = $build->uploadedFiles()->create([
            'filename' => Str::uuid()->toString(),
            'filesize' => 2000000000,
            'sha1sum' => $hash,
            'isurl' => false,
        ]);

        self::assertDatabaseHas(UploadFile::class, [
            'sha1sum' => $hash,
        ]);

        PruneUploads::dispatch();

        self::assertDatabaseMissing(UploadFile::class, [
            'sha1sum' => $hash,
        ]);

        self::assertFileDoesNotExist(storage_path("app/upload/$hash"));
    }
}
