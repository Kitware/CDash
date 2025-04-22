<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

use App\Models\TestMeasurement;
use App\Utils\DatabaseCleanupUtils;
use App\Utils\NoteCreator;
use App\Utils\TestCreator;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildError;
use CDash\Model\BuildFailure;
use CDash\Model\BuildGroup;
use CDash\Model\BuildUpdate;
use CDash\Model\BuildUpdateFile;
use CDash\Model\Coverage;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageFileLog;
use CDash\Model\CoverageSummary;
use CDash\Model\DynamicAnalysis;
use CDash\Model\DynamicAnalysisDefect;
use CDash\Model\DynamicAnalysisSummary;
use CDash\Model\Image;
use CDash\Model\Label;
use CDash\Model\UploadFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class RemoveBuildsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testRemoveBuilds(): void
    {
        $this->login();
        $this->get($this->url . '/removeBuilds.php?projectid=5');
        $this->clickSubmitByName('Submit');
        if (!str_contains($this->getBrowser()->getContentAsText(), 'Removed')) {
            $this->fail("'Removed' not found when expected");
        }
    }

    public function testBuildRemovalWorksAsExpected(): void
    {
        $time = gmdate(FMT_DATETIME);

        // Find an existing site.
        $row = DB::select('SELECT id FROM site LIMIT 1')[0];
        $siteid = $row->id;

        // Label
        $label = new Label();
        $label->SetText('remove me');

        // Build
        $build = new Build();
        $build->Name = 'RemovalWorksAsExpected';
        $build->SetStamp('20160822-1810-Experimental');
        $build->ProjectId = 1;
        $build->InsertErrors = true;
        $build->SiteId = $siteid;
        $build->StartTime = $time;
        $build->EndTime = $time;
        $build->SubmitTime = $time;
        $build->AddLabel($label);
        $buildgroup = new BuildGroup();
        $build->GroupId = $buildgroup->GetGroupIdFromRule($build);
        $build->OSName = 'Windows';

        // BuildError
        $error = new BuildError();
        $error->Text = 'error: asdf';
        $error->Type = 1;
        $error->LogLine = 1;
        $build->AddError($error);

        // BuildFailure
        $failure = new BuildFailure();
        $failure->StdError = 'failure: asdf';
        $failure->AddArgument('arg1');
        $failure->AddLabel($label);
        $failure->Type = 1;
        $build->AddError($failure);

        $build->Save();

        // Create another build to test shared resources.
        $existing_build = new Build();
        $existing_build->Id = $build->Id;
        $existing_build->FillFromId($build->Id);
        $existing_build->SetStamp('20160822-1811-Experimental');
        $existing_build->SubmitTime = $time;
        $existing_build->InsertErrors = true;
        $existing_build->AddError($failure);
        $existing_build->Id = null;
        $existing_build->Save();

        // BuildConfigure
        $configure = new BuildConfigure();
        $configure->BuildId = $build->Id;
        $configure->StartTime = $time;
        $configure->EndTime = $time;
        $configure->Command = 'cmake';
        $configure->Log = "precontext\nWARNING: bar\npostcontext";
        $configure->Status = 5;
        $configure->AddLabel($label);
        $configure->Insert();
        $configure->ComputeWarnings();
        $configure->ComputeErrors();

        $configure->BuildId = $existing_build->Id;

        $time2 = gmdate(FMT_DATETIME, time() + 1);
        $configure->StartTime = $time2;
        $configure->EndTime = $time2;
        $configure->Insert();

        // BuildNote
        $note = new NoteCreator();
        $note->name = 'my note';
        $note->text = 'note text';
        $note->time = $time;
        $note->buildid = $build->Id;
        $note->create();

        $shared_note = new NoteCreator();
        $shared_note->name = 'my shared note';
        $shared_note->text = 'shared note text';
        $shared_note->time = $time;
        $shared_note->buildid = $build->Id;
        $shared_note->create();

        $shared_note->buildid = $existing_build->Id;
        $shared_note->create();

        // buildtesttime
        $build->SaveTotalTestsTime();

        // BuildUpdate
        $updatefile = new BuildUpdateFile();
        $updatefile->Author = 'My Self';
        $updatefile->Committer = 'My Self';
        $updatefile->Email = 'my@self.com';
        $updatefile->CommitterEmail = 'my@self.com';
        $updatefile->Revision = 2;
        $updatefile->PriorRevision = 1;
        $updatefile->Filename = 'foo.cpp';
        $updatefile->Status = 'MODIFIED';

        $update = new BuildUpdate();
        $update->AddFile($updatefile);
        $update->BuildId = $build->Id;
        $update->StartTime = $time;
        $update->EndTime = $time;
        $update->Command = 'git fetch';
        $update->Insert();

        DB::insert("INSERT INTO build2update (buildid, updateid)
            VALUES ($existing_build->Id, $update->UpdateId)");

        // Coverage
        $file1 = new CoverageFile();
        $file1->FullPath = '/path/to/unshared.php';
        $file1->File .= 'this unshared line gets covered<br>';
        $file1->File .= 'this unshared line does not<br>';

        $coverage1 = new Coverage();
        $coverage1->Covered = 1;
        $coverage1->CoverageFile = $file1;
        $coverage1->LocTested = 1;
        $coverage1->LocUntested = 1;
        $coverage1->AddLabel($label);

        $file2 = new CoverageFile();
        $file2->FullPath = '/path/to/shared.php';
        $file2->File .= 'this shared line gets covered<br>';
        $file2->File .= 'this shared line does not<br>';

        $coverage2 = new Coverage();
        $coverage2->Covered = 1;
        $coverage2->CoverageFile = $file2;
        $coverage2->LocTested = 1;
        $coverage2->LocUntested = 1;
        $coverage2->AddLabel($label);

        $summary = new CoverageSummary();
        $summary->BuildId = $build->Id;
        $summary->AddCoverage($coverage1);
        $summary->AddCoverage($coverage2);
        $summary->Insert(true);

        $file1->TrimLastNewline();
        $file1->Update($build->Id);
        $log1 = new CoverageFileLog();
        $log1->AddLine(1, 1);
        $log1->BuildId = $build->Id;
        $log1->FileId = $file1->Id;
        $log1->Insert(true);

        $file2->TrimLastNewline();
        $file2->Update($build->Id);
        $log2 = new CoverageFileLog();
        $log2->AddLine(1, 1);
        $log2->BuildId = $build->Id;
        $log2->FileId = $file2->Id;
        $log2->Insert(true);

        // Also add coverage to existing build to test that shared files
        // do not get deleted.
        $existing_cov = new Coverage();
        $existing_cov->Covered = 1;
        $existing_cov->CoverageFile = $file2;
        $existing_cov->LocTested = 1;
        $existing_cov->LocUntested = 1;
        $existing_cov->AddLabel($label);

        $existing_summary = new CoverageSummary();
        $existing_summary->BuildId = $existing_build->Id;
        $existing_summary->AddCoverage($existing_cov);
        $existing_summary->Insert(true);

        $file2->Update($existing_build->Id);

        $existing_log = new CoverageFileLog();
        $existing_log->AddLine(1, 1);
        $existing_log->BuildId = $existing_build->Id;
        $existing_log->FileId = $file2->Id;
        $existing_log->Insert(true);

        // DynamicAnalysis
        $DA_defect = new DynamicAnalysisDefect();
        $DA_defect->Type = 'Potential Memory Leak';
        $DA_defect->Value = 5;

        $DA = new DynamicAnalysis();
        $DA->BuildId = $build->Id;
        $DA->Checker = 'Valgrind';
        $DA->FullCommandLine = 'php DA_removebuilds.php';
        $DA->Log = 'build removed successfully';
        $DA->Name = 'removal test';
        $DA->Path = '/path/to/removal/DA';
        $DA->Status = 'failed';
        $DA->AddDefect($DA_defect);
        $DA->AddLabel($label);
        $DA->Insert();

        $DA_summary = new DynamicAnalysisSummary();
        $DA_summary->BuildId = $build->Id;
        $DA_summary->Checker = 'Valgrind';
        $DA_summary->AddDefects($DA_defect->Value);
        $DA_summary->Insert();

        // Test
        $test_creator = new TestCreator();
        $test_creator->projectid = 1;
        $test_creator->alreadyCompressed = false;
        $test_creator->testDetails = 'Completed';
        $test_creator->setTestName('removal test');
        $test_creator->testPath = '/path/to/removal/test';
        $test_creator->testCommand = 'php test_removebuilds.php';
        $test_creator->testOutput = 'build removed successfully';
        $test_creator->testStatus = 'passed';

        $measurement = new TestMeasurement();
        $measurement->name = 'Exit Value';
        $measurement->type = 'text/string';
        $measurement->value = '5';
        $test_creator->measurements->push($measurement);

        $image = new Image();
        $image->Extension = 'image/png';
        $image->Data = 'iVBORw0KGgoAAAANSUhEUgAAABwAAAASCAMAAAB/2U7WAAAABl'
            . 'BMVEUAAAD///+l2Z/dAAAASUlEQVR4XqWQUQoAIAxC2/0vXZDr'
            . 'EX4IJTRkb7lobNUStXsB0jIXIAMSsQnWlsV+wULF4Avk9fLq2r'
            . '8a5HSE35Q3eO2XP1A1wQkZSgETvDtKdQAAAABJRU5ErkJggg==';
        $image->Name = 'remove_me.png';
        $test_creator->images->push($image);

        $test_creator->labels->push($label);

        $test_creator->create($build);

        $test_creator2 = new TestCreator();
        $test_creator2->projectid = 1;
        $test_creator2->alreadyCompressed = false;
        $test_creator2->testDetails = 'Completed';
        $test_creator2->setTestName('shared test');
        $test_creator2->testPath = '/path/to/shared/test';
        $test_creator2->testCommand = 'php test_sharedbuilds.php';
        $test_creator2->testOutput = 'build shared successfully';
        $test_creator2->testStatus = 'passed';

        $measurement2 = new TestMeasurement();
        $measurement2->name = 'Exit Value';
        $measurement2->type = 'text/string';
        $measurement2->value = '0';
        $test_creator2->measurements->push($measurement2);

        $image2 = new Image();
        $image2->Extension = 'image/gif';
        $image2->Name = 'smile.gif';
        $image2->Data = base64_encode(file_get_contents(dirname(__FILE__) . '/data/smile.gif'));
        $test_creator2->images->push($image2);

        $test_creator2->labels->push($label);

        $test_creator2->create($build);

        $test_creator2->testOutput = 'build shared successfully';
        $test_creator2->create($existing_build);

        // UploadFile
        $filename = dirname(__FILE__) . '/data/smile.gif';
        $upload1 = new UploadFile();
        $upload1->Filename = $filename;
        $upload1->IsUrl = false;
        $upload1->BuildId = $build->Id;
        $upload1->Sha1Sum = sha1_file($filename);
        $upload1->Filesize = filesize($filename);
        $upload1->Insert();

        $filename = dirname(__FILE__) . '/data/smile2.gif';
        $upload2 = new UploadFile();
        $upload2->Filename = $filename;
        $upload2->IsUrl = false;
        $upload2->BuildId = $build->Id;
        $upload2->Sha1Sum = sha1_file($filename);
        $upload2->Filesize = filesize($filename);
        $upload2->Insert();
        $upload2->BuildId = $existing_build->Id;
        $upload2->Insert();

        // Various tables that are too hard to spoof with models so we resort
        // to direct insertion.
        DB::table('buildemail')->insert([
            'userid' => 1,
            'buildid' => $build->Id,
            'category' => 0,
        ]);
        DB::table('builderrordiff')->insert([
            'buildid' => $build->Id,
            'type' => 0,
            'difference_positive' => 1,
            'difference_negative' => 1,
        ]);
        DB::table('configureerrordiff')->insert([
            'buildid' => $build->Id,
            'type' => 0,
            'difference' => 1,
        ]);
        DB::table('coveragesummarydiff')->insert([
            'buildid' => $build->Id,
            'loctested' => 1,
            'locuntested' => 1,
        ]);
        DB::table('summaryemail')->insert([
            'buildid' => $build->Id,
            'date' => $time,
            'groupid' => 1,
        ]);
        DB::table('subproject2build')->insert([
            'subprojectid' => 1,
            'buildid' => $build->Id,
        ]);
        DB::table('testdiff')->insert([
            'buildid' => $build->Id,
            'type' => 0,
            'difference_positive' => 1,
            'difference_negative' => 1,
        ]);

        // Insert some unused records to make sure they get properly pruned by db:clean
        // without impacting the data created above.
        $crc32 = crc32('');
        $buildfailuredetails_id = DB::table('buildfailuredetails')->insertGetId([
            'type' => 0,
            'stdoutput' => '',
            'stderror' => '',
            'exitcondition' => 'normal',
            'language' => 'PHP',
            'targetname' => 'test_removebuilds',
            'outputfile' => 'test_removebuilds',
            'outputtype' => 'test',
            'crc32' => $crc32,
        ]);
        $configure_id = DB::table('configure')->insertGetId([
            'command' => 'test_removebuilds',
            'log' => 'created by test_removebuilds',
            'status' => 0,
            'warnings' => 0,
            'crc32' => $crc32,
        ]);
        $coveragefile_id = DB::table('coveragefile')->insertGetId([
            'fullpath' => 'tests/test_removebuild.php',
            'file' => 'asdf',
            'crc32' => $crc32,
        ]);
        $dailyupdate_id = DB::table('dailyupdate')->insertGetId([
            'projectid' => 999,
            'date' => $time,
            'command' => 'created by test_removebuilds',
            'type' => 'ok',
            'status' => 0,
            'revision' => 'DEADBEEF',
        ]);
        $image_id = DB::table('image')->insertGetId([
            'img' => 'asdf',
            'extension' => 'png',
            'checksum' => 0,
        ]);
        $note_id = DB::table('note')->insertGetId([
            'text' => 'note for test_removebuildds',
            'name' => 'test_removebuilds.log',
            'crc32' => $crc32,
        ]);
        $testoutput_id = DB::table('testoutput')->insertGetId([
            'output' => 'testoutput for test_removebuildds',
            'command' => 'php test_removebuilds.php',
            'path' => '/cdash/tests/test_removebuilds.php',
            'crc32' => $crc32,
        ]);
        $uploadfile_id = DB::table('uploadfile')->insertGetId([
            'filename' => 'test_removebuilds.php',
            'filesize' => 0,
            'sha1sum' => '00000000',
            'isurl' => 0,
        ]);

        // Verify that db:clean works as expected.
        Artisan::call('db:clean');
        $extra_msg = 'after db:clean';
        $this->verify('buildfailuredetails', 'id', '=', $buildfailuredetails_id, 0, $extra_msg);
        $this->verify('configure', 'id', '=', $configure_id, 0, $extra_msg);
        $this->verify('coveragefile', 'id', '=', $coveragefile_id, 0, $extra_msg);
        $this->verify('dailyupdate', 'id', '=', $dailyupdate_id, 0, $extra_msg);
        $this->verify('image', 'id', '=', $image_id, 0, $extra_msg);
        $this->verify('note', 'id', '=', $note_id, 0, $extra_msg);
        $this->verify('testoutput', 'id', '=', $testoutput_id, 0, $extra_msg);
        $this->verify('uploadfile', 'id', '=', $uploadfile_id, 0, $extra_msg);

        // Verify that our build-related data was created successfully
        // and not accidentally removed by db:clean.
        $this->verify('build', 'id', '=', $build->Id, 1);
        $this->verify('build2group', 'buildid', '=', $build->Id, 1);
        $this->verify('buildemail', 'buildid', '=', $build->Id, 1);
        $this->verify('builderror', 'buildid', '=', $build->Id, 1);
        $this->verify('builderrordiff', 'buildid', '=', $build->Id, 1);
        $this->verify('buildtesttime', 'buildid', '=', $build->Id, 1);
        $this->verify('configureerrordiff', 'buildid', '=', $build->Id, 1);
        $this->verify('coveragesummary', 'buildid', '=', $build->Id, 1);
        $this->verify('coveragesummarydiff', 'buildid', '=', $build->Id, 1);
        $this->verify('coveragefilelog', 'buildid', '=', $build->Id, 2);
        $this->verify('dynamicanalysissummary', 'buildid', '=', $build->Id, 1);
        $this->verify('summaryemail', 'buildid', '=', $build->Id, 1);
        $this->verify('subproject2build', 'buildid', '=', $build->Id, 1);
        $this->verify('testdiff', 'buildid', '=', $build->Id, 1);

        [$buildfailureid, $detailsid] =
            $this->verify_get_columns('buildfailure', ['id', 'detailsid'], 'buildid', '=', $build->Id, 1);
        $this->verify('buildfailure2argument', 'buildfailureid', '=', $buildfailureid, 1);
        $this->verify('buildfailuredetails', 'id', '=', $detailsid, 1);

        $configureid =
            $this->verify_get_rows('build2configure', 'configureid', 'buildid', '=', $build->Id, 1);
        $this->verify('build2configure', 'configureid', '=', $configureid, 2);
        $this->verify('configure', 'id', '=', $configureid, 1);
        $this->verify('configureerror', 'configureid', '=', $configureid, 1);

        $noteids =
            $this->verify_get_rows('build2note', 'noteid', 'buildid', '=', $build->Id, 2);
        $this->verify('note', 'id', 'IN', $noteids, 2);

        $coveragefileids =
            $this->verify_get_rows('coverage', 'fileid', 'buildid', '=', $build->Id, 2);
        $this->verify('coveragefile', 'id', 'IN', $coveragefileids, 2);

        $dynamicanalysisid =
            $this->verify_get_rows('dynamicanalysis', 'id', 'buildid', '=', $build->Id, 1);
        $this->verify('dynamicanalysisdefect', 'dynamicanalysisid', '=', $dynamicanalysisid, 1);

        $outputids =
            $this->verify_get_rows('build2test', 'outputid', 'buildid', '=', $build->Id, 2);
        $imgids = $this->verify_get_rows('test2image', 'imgid', 'outputid', 'IN', $outputids, 2);
        $this->verify('image', 'id', 'IN', $imgids, 2);

        $updateid =
            $this->verify_get_rows('build2update', 'updateid', 'buildid', '=', $build->Id, 1);
        $this->verify('buildupdate', 'id', '=', $updateid, 1);
        $this->verify('updatefile', 'updateid', '=', $updateid, 1);

        $uploadfileids =
            $this->verify_get_rows('build2uploadfile', 'fileid', 'buildid', '=', $build->Id, 2);
        $this->verify('uploadfile', 'id', 'IN', $uploadfileids, 2);

        $labelid =
            $this->verify_get_rows('label2build', 'labelid', 'buildid', '=', $build->Id, 1);
        $this->verify('label', 'id', '=', $labelid, 1);
        $this->verify('label2buildfailure', 'labelid', '=', $labelid, 2);
        $this->verify('label2coveragefile', 'labelid', '=', $labelid, 3);
        $this->verify('label2dynamicanalysis', 'labelid', '=', $labelid, 1);
        $this->verify('label2test', 'labelid', '=', $labelid, 3);

        // Remove the build.
        DatabaseCleanupUtils::removeBuild($build->Id);

        // Check that everything was deleted properly but shared records remain.
        $extra_msg = 'after 1st delete';
        $this->verify('build', 'id', '=', $build->Id, 0, $extra_msg);
        $this->verify('build2configure', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('build2configure', 'buildid', '=', $existing_build->Id, 1, $extra_msg);
        $this->verify('build2group', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('build2note', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('build2test', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('build2update', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('build2uploadfile', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('buildemail', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('builderror', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('builderrordiff', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('buildfailure', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('buildfailure2argument', 'buildfailureid', '=', $buildfailureid, 0, $extra_msg);
        $this->verify('buildfailuredetails', 'id', '=', $detailsid, 1, $extra_msg);
        $this->verify('buildtesttime', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('buildupdate', 'id', '=', $updateid, 1, $extra_msg);
        $this->verify('configure', 'id', '=', $configureid, 1, $extra_msg);
        $this->verify('configureerror', 'configureid', '=', $configureid, 1, $extra_msg);
        $this->verify('configureerrordiff', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('coverage', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('coveragefile', 'id', 'IN', $coveragefileids, 1, $extra_msg);
        $this->verify('coveragefilelog', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('coveragesummary', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('coveragesummarydiff', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('dynamicanalysis', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('dynamicanalysissummary', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('dynamicanalysisdefect', 'dynamicanalysisid', '=', $dynamicanalysisid, 0, $extra_msg);
        $this->verify('image', 'id', 'IN', $imgids, 1, $extra_msg);
        $this->verify('label2build', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('label2buildfailure', 'labelid', '=', $labelid, 1, $extra_msg);
        $this->verify('label2coveragefile', 'labelid', '=', $labelid, 1, $extra_msg);
        $this->verify('label2dynamicanalysis', 'labelid', '=', $labelid, 0, $extra_msg);
        $this->verify('label2test', 'labelid', '=', $labelid, 1, $extra_msg);
        $this->verify('note', 'id', 'IN', $noteids, 1, $extra_msg);
        $this->verify('summaryemail', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('subproject2build', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('test2image', 'outputid', 'IN', $outputids, 1, $extra_msg);
        $this->verify('testdiff', 'buildid', '=', $build->Id, 0, $extra_msg);
        $this->verify('updatefile', 'updateid', '=', $updateid, 1, $extra_msg);
        $this->verify('uploadfile', 'id', 'IN', $uploadfileids, 1, $extra_msg);

        // Remove the other build too to verify that shared resources get cleaned up
        // and to make this test idempotent.
        DatabaseCleanupUtils::removeBuild($existing_build->Id);

        $extra_msg = 'after 2nd delete';
        $this->verify('build', 'id', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('build2configure', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('build2group', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('build2note', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('build2test', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('build2update', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('build2uploadfile', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('buildemail', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('builderror', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('builderrordiff', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('buildfailure', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('buildfailuredetails', 'id', '=', $detailsid, 0, $extra_msg);
        $this->verify('buildtesttime', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('buildupdate', 'id', '=', $updateid, 0, $extra_msg);
        $this->verify('configure', 'id', '=', $configureid, 0, $extra_msg);
        $this->verify('configureerror', 'configureid', '=', $configureid, 0, $extra_msg);
        $this->verify('configureerrordiff', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('coverage', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('coveragefile', 'id', 'IN', $coveragefileids, 0, $extra_msg);
        $this->verify('coveragefilelog', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('coveragesummary', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('coveragesummarydiff', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('dynamicanalysis', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('dynamicanalysissummary', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('dynamicanalysisdefect', 'dynamicanalysisid', '=', $dynamicanalysisid, 0, $extra_msg);
        $this->verify('image', 'id', 'IN', $imgids, 0, $extra_msg);
        $this->verify('label2build', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('label2buildfailure', 'labelid', '=', $labelid, 0, $extra_msg);
        $this->verify('label2coveragefile', 'labelid', '=', $labelid, 0, $extra_msg);
        $this->verify('label2dynamicanalysis', 'labelid', '=', $labelid, 0, $extra_msg);
        $this->verify('label2test', 'labelid', '=', $labelid, 0, $extra_msg);
        $this->verify('note', 'id', 'IN', $noteids, 0, $extra_msg);
        $this->verify('summaryemail', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('subproject2build', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('test2image', 'outputid', 'IN', $outputids, 0, $extra_msg);
        $this->verify('testdiff', 'buildid', '=', $existing_build->Id, 0, $extra_msg);
        $this->verify('updatefile', 'updateid', '=', $updateid, 0, $extra_msg);
        $this->verify('uploadfile', 'id', 'IN', $uploadfileids, 0, $extra_msg);
    }

    public function verify(string $table, string $field, string $compare, string|int $value, int $expected, string $extra_msg = ''): void
    {
        $num_rows = count(DB::select("SELECT $field FROM $table WHERE $field $compare $value"));
        if ($num_rows !== $expected) {
            $this->fail("Expected $expected for $table $extra_msg, found $num_rows");
        }
    }

    /**
     * @param array<int|string> $columns
     *
     * @return array<int|string>
     */
    public function verify_get_columns(string $table, array $columns, string $field, string $compare, string $value, int $expected): array
    {
        $col_arg = implode(',', $columns);
        $result = DB::select("SELECT $col_arg FROM $table WHERE $field $compare $value");
        $num_rows = count($result);
        if ($num_rows !== $expected) {
            $this->fail("Expected $expected for $table, found $num_rows");
        }
        $row = $result[0];
        $retval = [];
        foreach ($columns as $c) {
            $retval[] = $row->$c;
        }
        return $retval;
    }

    public function verify_get_rows(string $table, string $column, string $field, string $compare, string $value, int $expected): string
    {
        $result = DB::select("SELECT $column FROM $table WHERE $field $compare $value");
        $num_rows = count($result);
        if ($num_rows !== $expected) {
            $this->fail("Expected $expected for $table, found $num_rows");
        }
        $arr = [];
        foreach ($result as $row) {
            $arr[] = $row->$column;
        }
        if (count($arr) === 1) {
            return $arr[0];
        }
        return '(' . implode(',', $arr) . ')';
    }
}
