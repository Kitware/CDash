<?php

namespace Tests\Feature\Console;

use App\Models\BuildConfigure;
use App\Models\BuildUpdate;
use App\Models\Configure;
use App\Models\Coverage;
use App\Models\CoverageFile;
use App\Models\Image;
use App\Models\Note;
use App\Models\Project;
use App\Models\UploadFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class DatabaseCleanCommandTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * Test that db:clean deletes the records which no build uses, and keeps the ones which builds use.
     */
    public function testDeletesOnlyUnusedRecords(): void
    {
        $used_records = $this->createRecords();
        $unused_records = $this->createRecords();
        $unused_output_id = DB::table('testoutput')->insertGetId([
            'path' => '/path/to/test',
            'command' => 'unused command',
            'output' => 'unused output',
        ]);

        [$configure, $update, $note, $coverage_file, $image, $upload] = $used_records;
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);
        BuildConfigure::create([
            'buildid' => $build->id,
            'configureid' => $configure->id,
        ]);
        $build->updateStep()->associate($update)->save();
        $build->notes()->attach($note->id, ['time' => Carbon::now()]);
        Coverage::create([
            'buildid' => $build->id,
            'fileid' => $coverage_file->id,
        ]);
        // Creating the test also creates its output.
        $test = $build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'passed',
        ]);
        $test->testImages()->create([
            'imgid' => $image->id,
            'role' => 'TestImage',
        ]);
        $build->uploadedFiles()->attach($upload->id);

        Artisan::call('db:clean');

        foreach ($used_records as $record) {
            $this->assertModelExists($record);
        }
        // The tests view joins each test to its output, so the test is only found if its output was kept.
        $this->assertModelExists($test);
        foreach ($unused_records as $record) {
            $this->assertModelMissing($record);
        }
        $this->assertDatabaseMissing('testoutput', ['id' => $unused_output_id]);
    }

    /**
     * @return array{Configure, BuildUpdate, Note, CoverageFile, Image, UploadFile}
     */
    private function createRecords(): array
    {
        return [
            Configure::factory()->create(),
            BuildUpdate::factory()->create(),
            Note::factory()->create(),
            CoverageFile::create([
                'fullpath' => '/path/to/file.cpp',
                'file' => 'int main() { return 0; }',
                'crc32' => crc32(Str::uuid()->toString()),
            ]),
            Image::create([
                'img' => 'image contents',
                'extension' => 'image/png',
                'checksum' => crc32(Str::uuid()->toString()),
            ]),
            UploadFile::create([
                'filename' => 'file.txt',
                'filesize' => 0,
                'sha1sum' => sha1(Str::uuid()->toString()),
                'isurl' => false,
            ]),
        ];
    }
}
