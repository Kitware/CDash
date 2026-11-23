<?php

namespace Tests\Feature\Utils;

use App\Models\Build;
use App\Models\BuildConfigure;
use App\Models\BuildError;
use App\Models\BuildFailureArgument;
use App\Models\BuildUpdate;
use App\Models\BuildUpdateFile;
use App\Models\Configure;
use App\Models\Coverage;
use App\Models\CoverageFile;
use App\Models\DynamicAnalysis;
use App\Models\DynamicAnalysisSummary;
use App\Models\Image;
use App\Models\Label;
use App\Models\Note;
use App\Models\Project;
use App\Models\TestDiff;
use App\Models\UploadFile;
use App\Models\User;
use App\Utils\DatabaseCleanupUtils;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class DatabaseCleanupUtilsTest extends TestCase
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
     * Test that removing a build deletes the build's data.  Most of these rows are deleted by the
     * ON DELETE CASCADE foreign keys on their tables, so this checks that those foreign keys exist.
     */
    public function testRemoveBuildDeletesDataOfBuild(): void
    {
        Storage::fake();

        $build = $this->createBuild();
        $label = Label::factory()->create();
        $group = $this->project->buildgroups()->firstOrFail();
        $build->buildGroups()->attach($group->id);
        $build->labels()->attach($label->id);

        $configure = Configure::factory()->create();
        DB::table('configureerror')->insert([
            'configureid' => $configure->id,
            'type' => 1,
            'text' => 'configure warning',
        ]);
        BuildConfigure::create([
            'buildid' => $build->id,
            'configureid' => $configure->id,
        ]);
        DB::table('configureerrordiff')->insert([
            'buildid' => $build->id,
            'type' => 1,
            'difference' => 1,
        ]);

        $update = BuildUpdate::factory()->create();
        $update->updateFiles()->save(BuildUpdateFile::factory()->make());
        $build->updateStep()->associate($update)->save();

        $note = Note::factory()->create();
        $build->notes()->attach($note->id, ['time' => Carbon::now()]);

        $build_error = BuildError::factory()->create(['buildid' => $build->id]);
        $build_error->arguments()->attach(BuildFailureArgument::create(['argument' => '-O2'])->id);
        $build_error->labels()->attach($label->id);
        DB::table('builderrordiff')->insert([
            'buildid' => $build->id,
            'type' => 0,
            'difference_positive' => 1,
            'difference_negative' => 0,
        ]);

        $coverage_file = CoverageFile::create([
            'fullpath' => '/path/to/file.cpp',
            'file' => 'int main() { return 0; }',
            'crc32' => crc32(Str::uuid()->toString()),
        ]);
        $coverage = Coverage::create([
            'buildid' => $build->id,
            'fileid' => $coverage_file->id,
        ]);
        $coverage->labels()->attach($label->id);
        DB::table('coveragefilelog')->insert([
            'buildid' => $build->id,
            'fileid' => $coverage_file->id,
            'log' => '1:1;',
        ]);
        DB::table('coveragesummary')->insert([
            'buildid' => $build->id,
            'loctested' => 1,
            'locuntested' => 0,
        ]);

        $dynamic_analysis = DynamicAnalysis::factory()->create(['buildid' => $build->id]);
        $dynamic_analysis->defects()->create([
            'type' => 'Memory Leak',
            'value' => 1,
        ]);
        $dynamic_analysis->labels()->attach($label->id);
        DynamicAnalysisSummary::create([
            'buildid' => $build->id,
            'checker' => 'Valgrind',
            'numdefects' => 1,
        ]);

        $test = $build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'passed',
        ]);
        $test->labels()->attach($label->id);
        $image = Image::create([
            'img' => 'image contents',
            'extension' => 'image/png',
            'checksum' => crc32(Str::uuid()->toString()),
        ]);
        $test->testImages()->create([
            'imgid' => $image->id,
            'role' => 'TestImage',
        ]);
        DB::table('buildtesttime')->insert([
            'buildid' => $build->id,
            'time' => 1.5,
        ]);
        TestDiff::create([
            'buildid' => $build->id,
            'type' => 0,
            'difference_positive' => 1,
            'difference_negative' => 0,
        ]);

        $upload = UploadFile::create([
            'filename' => 'file.txt',
            'filesize' => 0,
            'sha1sum' => sha1(Str::uuid()->toString()),
            'isurl' => false,
        ]);
        $build->uploadedFiles()->attach($upload->id);

        $build->emails()->create([
            'userid' => User::factory()->create()->id,
            'category' => 0,
        ]);
        DB::table('summaryemail')->insert([
            'buildid' => $build->id,
            'date' => Carbon::now(),
            'groupid' => $group->id,
        ]);

        $rows = [
            ['build', ['id' => $build->id]],
            ['build2group', ['buildid' => $build->id]],
            ['label2build', ['buildid' => $build->id]],
            ['build2configure', ['buildid' => $build->id]],
            ['configure', ['id' => $configure->id]],
            ['configureerror', ['configureid' => $configure->id]],
            ['configureerrordiff', ['buildid' => $build->id]],
            ['buildupdate', ['id' => $update->id]],
            ['updatefile', ['updateid' => $update->id]],
            ['build2note', ['buildid' => $build->id]],
            ['note', ['id' => $note->id]],
            ['builderrors', ['buildid' => $build->id]],
            ['buildfailure2argument', ['buildfailureid' => $build_error->id]],
            ['label2buildfailure', ['buildfailureid' => $build_error->id]],
            ['builderrordiff', ['buildid' => $build->id]],
            ['coverage', ['buildid' => $build->id]],
            ['coveragefile', ['id' => $coverage_file->id]],
            ['label2coverage', ['coverageid' => $coverage->id]],
            ['coveragefilelog', ['buildid' => $build->id]],
            ['coveragesummary', ['buildid' => $build->id]],
            ['dynamicanalysis', ['buildid' => $build->id]],
            ['dynamicanalysisdefect', ['dynamicanalysisid' => $dynamic_analysis->id]],
            ['label2dynamicanalysis', ['dynamicanalysisid' => $dynamic_analysis->id]],
            ['dynamicanalysissummary', ['buildid' => $build->id]],
            ['build2test', ['buildid' => $build->id]],
            ['label2test', ['testid' => $test->id]],
            ['test2image', ['testid' => $test->id]],
            ['image', ['id' => $image->id]],
            ['buildtesttime', ['buildid' => $build->id]],
            ['testdiff', ['buildid' => $build->id]],
            ['build2uploadfile', ['buildid' => $build->id]],
            ['uploadfile', ['id' => $upload->id]],
            ['buildemail', ['buildid' => $build->id]],
            ['summaryemail', ['buildid' => $build->id]],
        ];
        foreach ($rows as [$table, $columns]) {
            $this->assertDatabaseHas($table, $columns);
        }

        DatabaseCleanupUtils::removeBuild($build->id);

        foreach ($rows as [$table, $columns]) {
            $this->assertDatabaseMissing($table, $columns);
        }
    }

    /**
     * Test that records which several builds share are kept until the last of those builds is removed.
     */
    public function testRemoveBuildKeepsSharedRecordsUntilLastBuildIsRemoved(): void
    {
        Storage::fake();

        $configure = Configure::factory()->create();
        $update = BuildUpdate::factory()->create();
        $note = Note::factory()->create();
        $coverage_file = CoverageFile::create([
            'fullpath' => '/path/to/shared.cpp',
            'file' => 'int main() { return 0; }',
            'crc32' => crc32(Str::uuid()->toString()),
        ]);
        $image = Image::create([
            'img' => 'image contents',
            'extension' => 'image/png',
            'checksum' => crc32(Str::uuid()->toString()),
        ]);
        $upload_contents = Str::uuid()->toString();
        $upload = UploadFile::create([
            'filename' => 'shared.txt',
            'filesize' => strlen($upload_contents),
            'sha1sum' => sha1($upload_contents),
            'isurl' => false,
        ]);
        Storage::put("upload/{$upload->sha1sum}", $upload_contents);
        $shared_records = [$configure, $update, $note, $coverage_file, $image, $upload];

        $build = $this->createBuild();
        $other_build = $this->createBuild();
        foreach ([$build, $other_build] as $sharing_build) {
            BuildConfigure::create([
                'buildid' => $sharing_build->id,
                'configureid' => $configure->id,
            ]);
            $sharing_build->updateStep()->associate($update)->save();
            $sharing_build->notes()->attach($note->id, ['time' => Carbon::now()]);
            Coverage::create([
                'buildid' => $sharing_build->id,
                'fileid' => $coverage_file->id,
            ]);
            $sharing_build->tests()->create([
                'testname' => Str::uuid()->toString(),
                'status' => 'passed',
            ])->testImages()->create([
                'imgid' => $image->id,
                'role' => 'TestImage',
            ]);
            $sharing_build->uploadedFiles()->attach($upload->id);
        }

        DatabaseCleanupUtils::removeBuild($build->id);

        $this->assertModelMissing($build);
        foreach ($shared_records as $record) {
            $this->assertModelExists($record);
        }
        Storage::assertExists("upload/{$upload->sha1sum}");

        DatabaseCleanupUtils::removeBuild($other_build->id);

        $this->assertModelMissing($other_build);
        foreach ($shared_records as $record) {
            $this->assertModelMissing($record);
        }
        Storage::assertMissing("upload/{$upload->sha1sum}");
    }

    private function createBuild(): Build
    {
        return $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);
    }
}
