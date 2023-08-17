<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';


class SequenceIndependenceTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testOriginalOrder()
    {
        $file_order = [
            'Build',
            'Configure',
            'Coverage',
            'CoverageLog',
            'DynamicAnalysis',
            'Notes',
            'Test',
            'Update',
            'Upload',
        ];
        if ($this->PerformOrderTest($file_order)) {
            $this->pass('Passed');
        }
    }

    public function testReverseOrder()
    {
        $file_order = [
            'Upload',
            'Update',
            'Test',
            'Notes',
            'DynamicAnalysis',
            'CoverageLog',
            'Coverage',
            'Configure',
            'Build'];
        if ($this->PerformOrderTest($file_order)) {
            $this->pass('Passed');
        }
    }

    public function testConfigureFirst()
    {
        $file_order = [
            'Configure',
            'Test',
            'Notes',
            'CoverageLog',
            'Coverage',
            'Build',
            'DynamicAnalysis',
            'Upload',
            'Update'];
        if ($this->PerformOrderTest($file_order)) {
            $this->pass('Passed');
        }
    }

    public function testCoverageFirst()
    {
        $file_order = [
            'Coverage',
            'DynamicAnalysis',
            'Configure',
            'Build',
            'CoverageLog',
            'Upload',
            'Notes',
            'Update',
            'Test'];
        if ($this->PerformOrderTest($file_order)) {
            $this->pass('Passed');
        }
    }

    public function testCoverageLogFirst()
    {
        $file_order = [
            'CoverageLog',
            'Notes',
            'Coverage',
            'Configure',
            'Upload',
            'Build',
            'DynamicAnalysis',
            'Update',
            'Test'];
        if ($this->PerformOrderTest($file_order)) {
            $this->pass('Passed');
        }
    }

    public function testDynamicAnalysisFirst()
    {
        $file_order = [
            'DynamicAnalysis',
            'Notes',
            'Configure',
            'Upload',
            'Test',
            'Coverage',
            'Update',
            'CoverageLog',
            'Build'];
        if ($this->PerformOrderTest($file_order)) {
            $this->pass('Passed');
        }
    }

    public function testNotesFirst()
    {
        $file_order = [
            'Notes',
            'DynamicAnalysis',
            'CoverageLog',
            'Update',
            'Configure',
            'Upload',
            'Build',
            'Test',
            'Coverage'];
        if ($this->PerformOrderTest($file_order)) {
            $this->pass('Passed');
        }
    }

    public function testTestFirst()
    {
        $file_order = [
            'Test',
            'CoverageLog',
            'DynamicAnalysis',
            'Build',
            'Configure',
            'Notes',
            'Update',
            'Coverage',
            'Upload'];
        if ($this->PerformOrderTest($file_order)) {
            $this->pass('Passed');
        }
    }

    public function testUpdateFirst()
    {
        $file_order = [
            'Update',
            'Notes',
            'DynamicAnalysis',
            'Upload',
            'Configure',
            'Test',
            'CoverageLog',
            'Build',
            'Coverage'];
        if ($this->PerformOrderTest($file_order)) {
            $this->pass('Passed');
        }
    }

    public function PerformOrderTest($file_order)
    {
        $success = true;
        $success &= $this->SubmitFiles($file_order);
        $success &= $this->VerifyBuild();
        return $success;
    }

    public function SubmitFiles($file_order)
    {
        // Mark any existing build as 'done' so that it will be replaced
        // upon submission of these new files.
        $update_query = "UPDATE build SET done=1
            WHERE name='Linux-g++-4.1-LesionSizingSandbox_Debug'";
        pdo_query($update_query);
        if (!pdo_query($update_query)) {
            $this->fail('update query returned false');
            return false;
        }

        // Submit the files in the order specified.
        $rep = dirname(__FILE__) . '/data/InsightExperimentalExample';
        foreach ($file_order as $type) {
            $file = "$rep/Insight_Experimental_$type.xml";
            if (!$this->submission('InsightExample', $file)) {
                $this->fail("Submission of $file failed");
                return false;
            }
        }
        return true;
    }

    public function VerifyBuild()
    {
        // Get data from the build table.
        $build_query =
            "SELECT id, starttime, endtime, configureerrors, configurewarnings,
            configureduration, builderrors, buildwarnings, buildduration, testnotrun,
            testfailed, testpassed
            FROM build WHERE name='Linux-g++-4.1-LesionSizingSandbox_Debug'";
        $build_result = pdo_query($build_query);
        if (!$build_result) {
            $this->fail('build query returned false');
            return false;
        }

        // Make sure we only found one matching result.
        $num_builds = pdo_num_rows($build_result);
        if ($num_builds != 1) {
            $this->fail("Expected 1 build, found $num_builds");
            return false;
        }

        // Verify the result from the build table.
        $build_row = pdo_fetch_array($build_result);
        if ($build_row['starttime'] != '2009-02-23 07:10:37') {
            $this->fail("Expected starttime to be '2009-02-23 07:10:37', found " . $build_row['starttime']);
            return false;
        }
        if ($build_row['endtime'] != '2009-02-23 12:32:22') {
            $this->fail("Expected endtime to be '2009-02-23 12:32:22', found " . $build_row['endtime']);
            return false;
        }
        if ($build_row['configureerrors'] != 0) {
            $this->fail('Expected configureerrors to be 0, found ' . $build_row['configureerrors']);
            return false;
        }
        if ($build_row['configurewarnings'] != 0) {
            $this->fail('Expected configurewarnings to be 0, found ' . $build_row['configurewarnings']);
            return false;
        }
        if ($build_row['configureduration'] != 0.00) {
            $this->fail('Expected configureduration to be 0.00, found ' . $build_row['configureduration']);
            return false;
        }
        if ($build_row['builderrors'] != 0) {
            $this->fail('Expected builderrors to be 0, found ' . $build_row['builderrors']);
            return false;
        }
        if ($build_row['buildwarnings'] != 3) {
            $this->fail('Expected buildwarnings to be 3, found ' . $build_row['buildwarnings']);
            return false;
        }
        if ($build_row['buildduration'] != 3103) {
            $this->fail('Expected buildduration to be 3103, found ' . $build_row['buildduration']);
            return false;
        }
        if ($build_row['testnotrun'] != 1) {
            $this->fail('Expected testnotrun to be 1, found ' . $build_row['testnotrun']);
            return false;
        }
        if ($build_row['testfailed'] != 5) {
            $this->fail('Expected testfailed to be 5, found ' . $build_row['testfailed']);
            return false;
        }
        if ($build_row['testpassed'] != 46) {
            $this->fail('Expected testpassed to be 46, found ' . $build_row['testpassed']);
            return false;
        }

        // Verify note.
        $buildid = $build_row['id'];
        $note_query = "
            SELECT b2n.time, n.name
            FROM build2note AS b2n
            INNER JOIN note AS n ON (n.id=b2n.noteid)
            WHERE b2n.buildid=$buildid";
        $note_result = pdo_query($note_query);
        if (!$note_result) {
            $this->fail('note query returned false');
            return false;
        }
        $num_notes = pdo_num_rows($note_result);
        if ($num_notes != 1) {
            $this->fail("Expected 1 note, found $num_notes");
            return false;
        }
        $note_row = pdo_fetch_array($note_result);
        if ($note_row['time'] != '2009-02-23 12:32:00') {
            $this->fail("Expected note time to be '2009-02-23 12:32:00', found " . $note_row['time']);
            return false;
        }
        if ($note_row['name'] != '/home/ibanez/src/Work/Luis/DashboardScripts/camelot_itk_lesion_sizing_sandbox_debug_gcc41.cmake') {
            $this->fail("Expected note name to be '/home/ibanez/src/Work/Luis/DashboardScripts/camelot_itk_lesion_sizing_sandbox_debug_gcc41.cmake', found " . $note_row['name']);
            return false;
        }

        // Verify dynamic analysis.
        $DA_query =
            "SELECT sum(dd.value) AS numdefects
            FROM dynamicanalysisdefect AS dd, dynamicanalysis as d
            WHERE d.buildid='$buildid' AND dd.dynamicanalysisid=d.id";
        $DA_result = pdo_query($DA_query);
        if (!$DA_result) {
            $this->fail('dynamic analysis query returned false');
            return false;
        }
        $DA_row = pdo_fetch_array($DA_result);
        if ($DA_row['numdefects'] != 225) {
            $this->fail('Expected 225 defects, found ' . $DA_row['numdefects']);
            return false;
        }

        // Verify coverage.
        $coverage_result = pdo_query(
            "SELECT loctested, locuntested FROM coveragesummary
                WHERE buildid='$buildid'");
        if (!$coverage_result) {
            $this->fail('coverage query returned false');
            return false;
        }
        $coverage_row = pdo_fetch_array($coverage_result);
        if ($coverage_row['loctested'] != 2185) {
            $this->fail('Expected 2185 loctested, found ' . $coverage_row['loctested']);
            return false;
        }
        if ($coverage_row['locuntested'] != 674) {
            $this->fail('Expected 674 locuntested, found ' . $coverage_row['locuntested']);
            return false;
        }

        $num_files_row = pdo_single_row_query(
            "SELECT COUNT(1) AS numfiles
                FROM coveragefilelog
                WHERE buildid='$buildid'");
        $num_files_covered = $num_files_row['numfiles'];
        if ($num_files_covered != 2) {
            $this->fail("Expected 2 files covered, found $num_files_covered");
            return false;
        }

        // Verify updates.
        $update_query =
            "SELECT bu.nfiles
            FROM buildupdate AS bu
            INNER JOIN build2update AS b2u ON (b2u.updateid=bu.id)
            WHERE b2u.buildid='$buildid'";
        $update_result = pdo_query($update_query);
        if (!$update_result) {
            $this->fail('update query returned false');
            return false;
        }
        $num_updates = pdo_num_rows($update_result);
        if ($num_updates != 1) {
            $this->fail("Expected 1 update, found $num_updates");
            return false;
        }
        $update_row = pdo_fetch_array($update_result);
        if ($update_row['nfiles'] != 0) {
            $this->fail('Expected number of update files to be 0, found ' . $update_row['nfiles']);
            return false;
        }

        // Verify uploaded file.
        $upload_query =
            "SELECT uf.filename
            FROM uploadfile AS uf
            INNER JOIN build2uploadfile AS b2uf ON (b2uf.fileid=uf.id)
            WHERE b2uf.buildid='$buildid'";
        $upload_result = pdo_query($upload_query);
        if (!$upload_result) {
            $this->fail('upload query returned false');
            return false;
        }
        $num_uploads = pdo_num_rows($upload_result);
        if ($num_uploads != 1) {
            $this->fail("Expected 1 upload, found $num_uploads");
            return false;
        }
        $upload_row = pdo_fetch_array($upload_result);
        if ($upload_row['filename'] != 'tmp.txt') {
            $this->fail("Expected uploaded file to be named 'tmp.txt', found " . $upload_row['filename']);
            return false;
        }
        return true;
    }
}
