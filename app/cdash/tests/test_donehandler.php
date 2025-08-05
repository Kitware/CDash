<?php

require_once dirname(__FILE__) . '/cdash_test_case.php';

use App\Models\Site;
use App\Utils\DatabaseCleanupUtils;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\PendingSubmissions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class DoneHandlerTestCase extends KWWebTestCase
{
    protected $PDO;
    protected $ConfigFile;
    protected $Original;

    public function __construct()
    {
        parent::__construct();
        $this->PDO = Database::getInstance()->getPdo();
    }

    public function testDoneHandlerLocal(): void
    {
        $this->performTest();
    }

    public function testDoneHandlerRemote(): void
    {
        if (config('filesystems.default') !== 'local') {
            // Skip this test case if we're already testing remote storage.
            return;
        }

        $this->ConfigFile = dirname(__FILE__) . '/../../../.env';
        $this->Original = file_get_contents($this->ConfigFile);

        config(['cdash.remote_workers' => 'true']);
        config(['queue.default' => 'database']);
        file_put_contents($this->ConfigFile, "QUEUE_CONNECTION=database\n", FILE_APPEND | LOCK_EX);
        file_put_contents($this->ConfigFile, "QUEUE_RETRY_BASE=0\n", FILE_APPEND | LOCK_EX);
        file_put_contents($this->ConfigFile, "REMOTE_WORKERS=true\n", FILE_APPEND | LOCK_EX);

        $this->performTest(true);

        // Verify that we didn't leave any files behind in the inbox directory.
        $this->assertEqual(count(Storage::files('inbox')), 0);

        file_put_contents($this->ConfigFile, $this->Original);
    }

    private function performTest($remote = false): void
    {
        // Make a build.
        $build = new Build();
        $buildname = 'done-handler-test-build';
        $build->Name = $buildname;
        $projectid = get_project_id('InsightExample');
        $build->ProjectId = $projectid;
        $site = Site::find(1);
        $build->SiteId = $site->id;
        $stamp = '20181010-1410-Experimental';
        $build->SetStamp($stamp);
        $timestamp = 1539195000;
        $build->StartTime = gmdate(FMT_DATETIME, $timestamp);
        $this->assertTrue($build->AddBuild());
        $this->assertTrue($build->Id > 0);

        // Generate a Done.xml file and submit it.
        $tmpfname = tempnam(Storage::disk('local')->path('tmp'), 'Done');
        $handle = fopen($tmpfname, 'w');
        fwrite($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Done><buildId>$build->Id</buildId><time>$timestamp</time></Done>");
        fclose($handle);
        $received_buildid = $this->submission_assign_buildid(
            $tmpfname, 'InsightExample', $buildname, $site->name, $stamp);
        if ($remote) {
            Artisan::call('queue:work --once');
        }

        $this->assertEqual($build->Id, $received_buildid);

        // Verify that the build is marked as done.
        $this->assertTrue($build->GetDone());

        // Mark the build as "not done" again.
        $build->MarkAsDone(false);

        // Clean backup directory.
        $this->removeParsedFiles();

        // Invoke the Done handler again to verify that requeuing works
        // as intended.
        $pending = new PendingSubmissions();
        $pending->Build = $build;
        $pending->NumFiles = 2;
        $pending->Recheck = 1;
        $pending->Save();

        $this->submission_assign_buildid($tmpfname, 'InsightExample', $buildname, $site->name, $stamp);

        if ($remote) {
            foreach (range(0, 5) as $i) {
                Artisan::call('queue:work --once');
            }
        }

        $contents = Storage::get(Storage::files('parsed')[0]);
        $this->assertTrue(str_contains($contents, 'Done retries="5"'));

        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        unlink($tmpfname);
        DatabaseCleanupUtils::removeBuild($build->Id);
    }
}
