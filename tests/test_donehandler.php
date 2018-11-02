<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/ctestparser.php';
require_once 'include/pdo.php';

use CDash\Config;
use CDash\Lib\Parser\CTest\DoneParser;
use CDash\Model\Build;
use CDash\Model\PendingSubmissions;
use CDash\Model\Site;

class DoneHandlerTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->PDO = CDash\Database::getInstance()->getPdo();
    }

    public function testDoneHandler()
    {
        // Make a build.
        $build = new Build();
        $buildname = 'done-handler-test-build';
        $build->Name = $buildname;
        $projectid = get_project_id('InsightExample');
        $build->ProjectId = $projectid;
        $site = new Site();
        $site->Id = 1;
        $build->SiteId = $site->Id;
        $stamp = '20181010-1410-Experimental';
        $build->SetStamp($stamp);
        $timestamp = 1539195000;
        $build->StartTime = gmdate(FMT_DATETIME, $timestamp);
        $this->assertTrue($build->AddBuild());
        $this->assertTrue($build->Id > 0);

        $config = Config::getInstance();
        // Generate a Done.xml file and submit it.
        $tmpfname = tempnam($config->get('CDASH_BACKUP_DIRECTORY'), 'Done');
        $handle = fopen($tmpfname, 'w');
        fwrite($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Done><buildId>$build->Id</buildId><time>$timestamp</time></Done>");
        fclose($handle);
        $received_buildid = $this->submission_assign_buildid(
                $tmpfname, 'InsightExample', $buildname, $site->GetName(), $stamp);
        $this->assertEqual($build->Id, $received_buildid);

        // Verify that the build is marked as done.
        $this->assertEqual($build->GetDone(), 1);

        // Mark the build as "not done" again.
        $build->MarkAsDone(0);

        // Invoke the Done handler again to verify that requeuing works
        // as intended.
        $pending = new PendingSubmissions();
        $pending->Build = $build;
        $pending->NumFiles = 2;
        $pending->Save();
        $fp = fopen($tmpfname, 'r');

        /** @var DoneParser $handler */
        $handler = ctest_parse($fp, $projectid, $build->Id);
        fclose($fp);
        $this->assertTrue($handler instanceof DoneParser);
        $this->assertTrue($handler->shouldRequeue());
        $contents = file_get_contents($handler->backupFileName);
        $this->assertTrue(strpos($contents, "Done retries=\"1\"") !== false);

        unlink($tmpfname);
        remove_build($build->Id);
    }
}
