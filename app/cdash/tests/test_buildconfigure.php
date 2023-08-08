<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';




use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\Label;

class BuildConfigureTestCase extends KWWebTestCase
{
    protected $PDO;

    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildConfigure()
    {
        $configure = new BuildConfigure();
        $configure->BuildId = 'foo';
        if ($configure->Exists()) {
            $this->fail("configure with invalid buildid should not exist");
        }
        $log_contents = file_get_contents($this->logfilename);
        if (strpos($log_contents, 'BuildId is not numeric') === false) {
            $this->fail("'BuildId is not numeric' not found from Exists()");
        }

        $configure->BuildId = null;
        if ($configure->Exists()) {
            $this->fail("Configure exists with null buildid");
        }
        $log_contents = file_get_contents($this->logfilename);
        if (strpos($log_contents, 'BuildId not set') === false) {
            $this->fail("'BuildId is not numeric' not found from Exists()");
        }

        $configure->BuildId = 1;
        $configure->Command = "cmake .";
        $configure->Log = "configure log";
        $configure->StartTime = gmdate(FMT_DATETIME);
        $configure->EndTime = gmdate(FMT_DATETIME);
        $configure->Status = 0;
        if (!$configure->Insert()) {
            $this->fail("configure->Insert returned false");
        }

        $label = new Label();
        $configure->AddLabel($label);

        $configure->BuildId = 2;
        // This is expected to return false because the configure row already exists.
        if ($configure->Insert()) {
            $this->fail("configure->Insert returned true");
        }

        if (!$configure->GetConfigureForBuild(PDO::FETCH_ASSOC)) {
            $this->fail("configure->GetConfigureForBuild returned false");
        }

        if ($configure->Delete()) {
            $this->fail("configure->Delete returned true");
        }

        $configure->BuildId = 2;
        if (!$configure->Delete()) {
            $this->fail("configure->Delete returned false");
        }

        if ($configure->Exists()) {
            $this->fail("configure exists after delete");
        }

        $this->deleteLog($this->logfilename);
    }

    public function testBuildConfigureDiff()
    {
        $this->PDO = Database::getInstance();
        // Clean up any previous runs of this test.
        $stmt = $this->PDO->prepare(
            "SELECT id FROM build WHERE name = 'configure_warning_diff'");
        $this->PDO->execute($stmt);
        while ($row = $stmt->fetch()) {
            remove_build($row['id']);
        }

        // Make two consecutive builds.
        $build_rows = [
            ['2016-10-10', 1476079800],
            ['2017-10-10', 1507637400],
        ];
        $builds = [];
        foreach ($build_rows as $build_row) {
            $date = str_replace('-', '', $build_row[0]);
            $timestamp = $build_row[1];
            $build = new Build();
            $build->Name = 'configure_warning_diff';
            $build->ProjectId = 1;
            $build->SiteId = 1;
            $stamp = "$date-1410-Experimental";
            $build->SetStamp($stamp);
            $build->StartTime = gmdate(FMT_DATETIME, $timestamp);
            $builds[] = $build;
        }

        $builds[0]->SetNumberOfConfigureWarnings(-1);
        $builds[1]->SetNumberOfConfigureWarnings(0);
        foreach ($builds as $build) {
            $this->assertTrue($build->AddBuild());
            $this->assertTrue($build->Id > 0);
        }
        $builds[1]->ComputeConfigureDifferences();

        $stmt = $this->PDO->prepare(
            'SELECT COUNT(1) from configureerrordiff WHERE buildid = :buildid');
        $this->PDO->execute($stmt, [$builds[1]->Id]);
        $num_rows = $stmt->fetchColumn();
        $this->assertEqual($num_rows, 0);
    }
}
