<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class RemoveBuildsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testRemoveBuilds()
    {
        $this->login();
        $this->get($this->url . '/removeBuilds.php?projectid=5');
        $this->clickSubmitByName('Submit');
        if (strpos($this->getBrowser()->getContentAsText(), 'Removed') === false) {
            $this->fail("'Removed' not found when expected");
            return 1;
        }
        $this->pass('Passed');
    }

    public function testBuildRemovalWorksAsExpected()
    {
        require_once 'include/common.php';
        require_once 'include/pdo.php';
        require_once 'models/build.php';
        require_once 'models/buildconfigure.php';
        require_once 'models/builderror.php';
        require_once 'models/buildfailure.php';
        require_once 'models/buildgroup.php';
        require_once 'models/buildnote.php';
        require_once 'models/buildupdate.php';
        require_once 'models/coverage.php';
        require_once 'models/dynamicanalysis.php';
        require_once 'models/dynamicanalysissummary.php';
        require_once 'models/image.php';
        require_once 'models/label.php';
        require_once 'models/test.php';
        require_once 'models/uploadfile.php';

        $time = gmdate(FMT_DATETIME);

        // Find an existing site.
        $row = pdo_single_row_query('SELECT id FROM site LIMIT 1');
        $siteid = $row['id'];

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
        $info = new BuildInformation();
        $info->SetValue('OSNAME', 'Windows');
        $build->Information = $info;

        // BuildError
        $error = new BuildError();
        $error->Text = 'error: asdf';
        $build->AddError($error);

        // BuildFailure
        $failure = new BuildFailure();
        $failure->StdError = 'failure: asdf';
        $failure->AddArgument('arg1');
        $failure->AddLabel($label);
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

        // BuildNote
        $note = new BuildNote();
        $note->Name = 'my note';
        $note->Text = 'note text';
        $note->Time = $time;
        $note->BuildId = $build->Id;
        $note->Insert();

        $shared_note = new BuildNote();
        $shared_note->Name = 'my shared note';
        $shared_note->Text = 'shared note text';
        $shared_note->Time = $time;
        $shared_note->BuildId = $build->Id;
        $shared_note->Insert();

        $shared_note->BuildId = $existing_build->Id;
        $shared_note->Insert();

        // buildtesttime
        $build->SaveTotalTestsTime(8);

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

        pdo_query("INSERT INTO build2update (buildid, updateid)
            VALUES ($existing_build->Id, $update->UpdateId)");

        // Coverage
        $file1 = new CoverageFile();
        $file1->FullPath = '/path/to/unshared.php';
        $file1->File .= "this unshared line gets covered<br>";
        $file1->File .= "this unshared line does not<br>";

        $coverage1 = new Coverage();
        $coverage1->Covered = 1;
        $coverage1->CoverageFile = $file1;
        $coverage1->LocTested = 1;
        $coverage1->LocUntested = 1;
        $coverage1->AddLabel($label);

        $file2 = new CoverageFile();
        $file2->FullPath = '/path/to/shared.php';
        $file2->File .= "this shared line gets covered<br>";
        $file2->File .= "this shared line does not<br>";

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
        $test = new Test();
        $test->ProjectId = 1;
        $test->CompressedOutput = false;
        $test->Details = 'Completed';
        $test->Name = 'removal test';
        $test->Path = '/path/to/removal/test';
        $test->Command = 'php test_removebuilds.php';
        $test->Output = 'build removed successfully';

        $measurement = new TestMeasurement();
        $measurement->Name = 'Exit Value';
        $measurement->Type = 'text/string';
        $measurement->Value = 5;
        $test->AddMeasurement($measurement);

        $image = new Image();
        $image->Extension = 'image/png';
        $image->Data = 'iVBORw0KGgoAAAANSUhEUgAAABwAAAASCAMAAAB/2U7WAAAABl'
            . 'BMVEUAAAD///+l2Z/dAAAASUlEQVR4XqWQUQoAIAxC2/0vXZDr'
            . 'EX4IJTRkb7lobNUStXsB0jIXIAMSsQnWlsV+wULF4Avk9fLq2r'
            . '8a5HSE35Q3eO2XP1A1wQkZSgETvDtKdQAAAABJRU5ErkJggg==';
        $image->Name = 'remove_me.png';
        $test->AddImage($image);

        $test->Insert();

        $buildtest = new BuildTest();
        $buildtest->BuildId = $build->Id;
        $buildtest->TestId = $test->Id;
        $buildtest->Status = 'passed';
        $buildtest->Insert();

        $test->AddLabel($label);
        $test->InsertLabelAssociations($build->Id);

        $test2 = new Test();
        $test2->ProjectId = 1;
        $test2->CompressedOutput = false;
        $test2->Details = 'Completed';
        $test2->Name = 'shared test';
        $test2->Path = '/path/to/shared/test';
        $test2->Command = 'php test_sharedtest.php';
        $test2->Output = 'test shared successfully';

        $measurement2 = new TestMeasurement();
        $measurement2->Name = 'Exit Value';
        $measurement2->Type = 'text/string';
        $measurement2->Value = 0;
        $test2->AddMeasurement($measurement2);

        $image2 = new Image();
        $image2->Extension = 'image/gif';
        $image2->Name = 'smile.gif';
        $image2->Data = base64_encode(file_get_contents(dirname(__FILE__) . '/data/smile.gif'));
        $test2->AddImage($image2);

        $test2->Insert();

        $buildtest2 = new BuildTest();
        $buildtest2->BuildId = $build->Id;
        $buildtest2->TestId = $test2->Id;
        $buildtest2->Status = 'passed';
        $buildtest2->Insert();
        $buildtest2->BuildId = $existing_build->Id;
        $buildtest2->Insert();

        $test2->AddLabel($label);
        $test2->InsertLabelAssociations($build->Id);

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
        pdo_query(
            "INSERT INTO buildemail (userid, buildid, category)
            VALUES (1, $build->Id, 0)");
        pdo_query(
            "INSERT INTO builderrordiff
            (buildid, type, difference_positive, difference_negative)
            VALUES ($build->Id, 0, 1, 1)");
        pdo_query(
            "INSERT INTO configureerrordiff (buildid, type, difference)
            VALUES ($build->Id, 0, 1)");
        pdo_query(
            "INSERT INTO coveragesummarydiff (buildid, loctested, locuntested)
            VALUES ($build->Id, 1, 1)");
        pdo_query(
            "INSERT INTO summaryemail (buildid, date, groupid)
            VALUES ($build->Id, '$time', 1)");
        pdo_query(
            "INSERT INTO subproject2build (subprojectid, buildid)
            VALUES (1, $build->Id)");
        pdo_query(
            "INSERT INTO testdiff
            (buildid, type, difference_positive, difference_negative)
            VALUES ($build->Id, 0, 1, 1)");


        // Check that everything was created successfully.
        $this->verify('build', 'id', '=', $build->Id, 1);
        $this->verify('build2group', 'buildid', '=', $build->Id, 1);
        $this->verify('buildemail', 'buildid', '=', $build->Id, 1);
        $this->verify('builderror', 'buildid', '=', $build->Id, 1);
        $this->verify('builderrordiff', 'buildid', '=', $build->Id, 1);
        $this->verify('buildinformation', 'buildid', '=', $build->Id, 1);
        $this->verify('buildtesttime', 'buildid', '=', $build->Id, 1);
        $this->verify('configure', 'buildid', '=', $build->Id, 1);
        $this->verify('configureerror', 'buildid', '=', $build->Id, 1);
        $this->verify('configureerrordiff', 'buildid', '=', $build->Id, 1);
        $this->verify('coveragesummary', 'buildid', '=', $build->Id, 1);
        $this->verify('coveragesummarydiff', 'buildid', '=', $build->Id, 1);
        $this->verify('coveragefilelog', 'buildid', '=', $build->Id, 2);
        $this->verify('dynamicanalysissummary', 'buildid', '=', $build->Id, 1);
        $this->verify('summaryemail', 'buildid', '=', $build->Id, 1);
        $this->verify('subproject2build', 'buildid', '=', $build->Id, 1);
        $this->verify('testdiff', 'buildid', '=', $build->Id, 1);

        list($buildfailureid, $detailsid) =
            $this->verify_get_columns('buildfailure', ['id', 'detailsid'], 'buildid', '=', $build->Id, 1);
        $this->verify('buildfailure2argument', 'buildfailureid', '=', $buildfailureid, 1);
        $this->verify('buildfailuredetails', 'id', '=', $detailsid, 1);

        $noteids =
            $this->verify_get_rows('build2note', 'noteid', 'buildid', '=', $build->Id, 2);
        $this->verify('note', 'id', 'IN', $noteids, 2);

        $coveragefileids =
            $this->verify_get_rows('coverage', 'fileid', 'buildid', '=', $build->Id, 2);
        $this->verify('coveragefile', 'id', 'IN', $coveragefileids, 2);

        $dynamicanalysisid =
            $this->verify_get_rows('dynamicanalysis', 'id', 'buildid', '=', $build->Id, 1);
        $this->verify('dynamicanalysisdefect', 'dynamicanalysisid', '=', $dynamicanalysisid, 1);

        $testids =
            $this->verify_get_rows('build2test', 'testid', 'buildid', '=', $build->Id, 2);
        $this->verify('test', 'id', 'IN', $testids, 2);
        $this->verify('testmeasurement', 'testid', 'IN', $testids, 2);
        $imgids = $this->verify_get_rows('test2image', 'imgid', 'testid', 'IN', $testids, 2);
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
        $this->verify('label2test', 'labelid', '=', $labelid, 2);

        // Remove the build.
        remove_build($build->Id);

        // Check that everything was deleted properly.
        $this->verify('build', 'id', '=', $build->Id, 0, true);
        $this->verify('build2group', 'buildid', '=', $build->Id, 0, true);
        $this->verify('build2note', 'buildid', '=', $build->Id, 0, true);
        $this->verify('build2test', 'buildid', '=', $build->Id, 0, true);
        $this->verify('build2update', 'buildid', '=', $build->Id, 0, true);
        $this->verify('build2uploadfile', 'buildid', '=', $build->Id, 0, true);
        $this->verify('buildemail', 'buildid', '=', $build->Id, 0, true);
        $this->verify('builderror', 'buildid', '=', $build->Id, 0, true);
        $this->verify('builderrordiff', 'buildid', '=', $build->Id, 0, true);
        $this->verify('buildfailure', 'buildid', '=', $build->Id, 0, true);
        $this->verify('buildfailure2argument', 'buildfailureid', '=', $buildfailureid, 0, true);
        $this->verify('buildfailuredetails', 'id', '=', $detailsid, 1, true);
        $this->verify('buildinformation', 'buildid', '=', $build->Id, 0, true);
        $this->verify('buildtesttime', 'buildid', '=', $build->Id, 0, true);
        $this->verify('buildupdate', 'id', '=', $updateid, 1, true);
        $this->verify('configure', 'buildid', '=', $build->Id, 0, true);
        $this->verify('configureerror', 'buildid', '=', $build->Id, 0, true);
        $this->verify('configureerrordiff', 'buildid', '=', $build->Id, 0, true);
        $this->verify('coverage', 'buildid', '=', $build->Id, 0, true);
        $this->verify('coveragefile', 'id', 'IN', $coveragefileids, 1, true);
        $this->verify('coveragefilelog', 'buildid', '=', $build->Id, 0, true);
        $this->verify('coveragesummary', 'buildid', '=', $build->Id, 0, true);
        $this->verify('coveragesummarydiff', 'buildid', '=', $build->Id, 0, true);
        $this->verify('dynamicanalysis', 'buildid', '=', $build->Id, 0, true);
        $this->verify('dynamicanalysissummary', 'buildid', '=', $build->Id, 0, true);
        $this->verify('dynamicanalysisdefect', 'dynamicanalysisid', '=', $dynamicanalysisid, 0, true);
        $this->verify('image', 'id', 'IN', $imgids, 1, true);
        $this->verify('label2build', 'buildid', '=', $build->Id, 0, true);
        $this->verify('label2buildfailure', 'labelid', '=', $labelid, 1, true);
        $this->verify('label2coveragefile', 'labelid', '=', $labelid, 1, true);
        $this->verify('label2dynamicanalysis', 'labelid', '=', $labelid, 0, true);
        $this->verify('label2test', 'labelid', '=', $labelid, 0, true);
        $this->verify('note', 'id', 'IN', $noteids, 1, true);
        $this->verify('summaryemail', 'buildid', '=', $build->Id, 0, true);
        $this->verify('subproject2build', 'buildid', '=', $build->Id, 0, true);
        $this->verify('test', 'id', 'IN', $testids, 1, true);
        $this->verify('test2image', 'testid', 'IN', $testids, 1, true);
        $this->verify('testdiff', 'buildid', '=', $build->Id, 0, true);
        $this->verify('testmeasurement', 'testid', 'IN', $testids, 1, true);
        $this->verify('updatefile', 'updateid', '=', $updateid, 1, true);
        $this->verify('uploadfile', 'id', 'IN', $uploadfileids, 1, true);
    }

    public function verify($table, $field, $compare, $value, $expected, $deleted=false)
    {
        $delete_msg = '';
        if ($deleted) {
            $delete_msg = 'after deletion';
        }
        $num_rows = pdo_num_rows(pdo_query("SELECT $field FROM $table WHERE $field $compare $value"));
        if ($num_rows !== $expected) {
            $this->fail("Expected $expected for $table $delete_msg, found $num_rows");
        }
    }

    public function verify_get_columns($table, $columns, $field, $compare, $value, $expected)
    {
        $col_arg = implode(',', $columns);
        $result = pdo_query("SELECT $col_arg FROM $table WHERE $field $compare $value");
        $num_rows = pdo_num_rows($result);
        if ($num_rows !== $expected) {
            $this->fail("Expected $expected for $table, found $num_rows");
        }
        $row = pdo_fetch_array($result);
        $retval = array();
        foreach ($columns as $c) {
            $retval[] = $row[$c];
        }
        return $retval;
    }

    public function verify_get_rows($table, $column, $field, $compare, $value, $expected)
    {
        $result = pdo_query("SELECT $column FROM $table WHERE $field $compare $value");
        $num_rows = pdo_num_rows($result);
        if ($num_rows !== $expected) {
            $this->fail("Expected $expected for $table, found $num_rows");
        }
        $arr = array();
        while ($row = pdo_fetch_array($result)) {
            $arr[] = $row[$column];
        }
        if (count($arr) === 1) {
            return $arr[0];
        }
        $retval = '(' . implode(',', $arr) . ')';
        return $retval;
    }
}
