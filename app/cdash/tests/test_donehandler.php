<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/ctestparser.php';
require_once 'include/pdo.php';

use CDash\Model\Build;
use CDash\Model\PendingSubmissions;
use CDash\Model\Site;
use Illuminate\Support\Facades\Storage;

class DoneHandlerTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->PDO = CDash\Database::getInstance()->getPdo();
    }

    public function testDoneHandlerLocal()
    {
        $this->performTest();
    }

    public function testDoneHandlerRemote()
    {
        $this->ConfigFile = dirname(__FILE__) . '/../../../.env';
        $this->Original = file_get_contents($this->ConfigFile);

        config(['cdash.remote_workers' => 'true']);
        config(['queue.default' => 'database']);
        file_put_contents($this->ConfigFile, "QUEUE_CONNECTION=database\n", FILE_APPEND | LOCK_EX);
        file_put_contents($this->ConfigFile, "REMOTE_WORKERS=true\n", FILE_APPEND | LOCK_EX);

        $this->performTest(true);

        file_put_contents($this->ConfigFile, $this->Original);
    }

    private function performTest($remote = false)
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

        // Generate a Done.xml file and submit it.
        $tmpfname = tempnam(Storage::path('inbox'), 'Done');
        $handle = fopen($tmpfname, 'w');
        fwrite($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Done><buildId>$build->Id</buildId><time>$timestamp</time></Done>");
        fclose($handle);
        $received_buildid = $this->submission_assign_buildid(
            $tmpfname, 'InsightExample', $buildname, $site->GetName(), $stamp);
        if ($remote) {
            Artisan::call('queue:work --once');
        }

        $this->assertEqual($build->Id, $received_buildid);

        // Verify that the build is marked as done.
        $this->assertEqual($build->GetDone(), 1);

        // Mark the build as "not done" again.
        $build->MarkAsDone(0);

        // Clean backup directory.
        $this->removeParsedFiles();

        // Invoke the Done handler again to verify that requeuing works
        // as intended.
        $pending = new PendingSubmissions();
        $pending->Build = $build;
        $pending->NumFiles = 2;
        $pending->Recheck = 1;
        $pending->Save();

        $this->submission_assign_buildid($tmpfname, 'InsightExample', $buildname, $site->GetName(), $stamp);

        if ($remote) {
            foreach (range(0, 5) as $i) {
                Artisan::call('queue:work --once');
            };
        }

        $files = Storage::files('parsed');
        $contents = file_get_contents(Storage::path($files[0]));
        $this->assertTrue(strpos($contents, "Done retries=\"5\"") !== false);

        unlink($tmpfname);
        remove_build($build->Id);
    }
}
