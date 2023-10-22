<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use App\Services\TestingDay;

use CDash\Database;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

require_once dirname(__FILE__) . '/cdash_test_case.php';



class AutoRemoveBuildsOnSubmitTestCase extends KWWebTestCase
{
    private $config_file;

    public function __construct()
    {
        parent::__construct();
        $this->config_file = base_path('.env');
    }

    public function __destruct()
    {
        rename("{$this->config_file}.bak", $this->config_file);
    }

    public function enableAutoRemoveConfigSetting()
    {
        copy($this->config_file, "{$this->config_file}.bak");
        file_put_contents($this->config_file, "AUTOREMOVE_BUILDS=true", FILE_APPEND);
    }

    public function setAutoRemoveTimeFrame()
    {
        // set project autoremovetimeframe
        $db = Database::getInstance();
        $sql = 'UPDATE project SET autoremovetimeframe=:time WHERE name=:project';
        /** @var PDOStatement $stmt */
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':time', 7);
        $stmt->bindValue(':project', 'EmailProjectExample');
        $stmt->execute();
    }

    public function testBuildsRemovedOnSubmission()
    {
        $this->enableAutoRemoveConfigSetting();

        $this->setAutoRemoveTimeFrame();
        $this->deleteLog($this->logfilename);

        /** @var \CDash\Database $db */
        $db = Database::getInstance();

        /** @var PDO $pdo */
        $pdo = $db->getPdo();

        $result = $db->query("SELECT id FROM project WHERE name = 'EmailProjectExample'");
        $projectid = $result->fetchColumn();

        // Submit the first build
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $testxml1 = "$rep/1_test.xml";

        if (!$this->submission('EmailProjectExample', $testxml1)) {
            $this->fail('submission 1 failed');
            return;
        }

        // Check that the test is actually there
        $sql = "SELECT name FROM build WHERE projectid=:id AND stamp=:stamp";

        /** @var PDOStatement $stmt */
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $projectid);
        $stmt->bindValue(':stamp', '20090223-0100-Nightly');

        if (!$stmt->execute()) {
            $this->fail('pdo_query returned false');
            return 1;
        }
        $query_array = pdo_fetch_array($stmt);

        if ($query_array[0] != 'Win32-MSVC2009') {
            echo $query_array[0];
            $this->fail('First build not inserted correctly');
            return 1;
        }

        // Make an expired buildgroup and rule to verify that they get deleted.
        $projectid = get_project_id('EmailProjectExample');
        $new_build_group = new BuildGroup();
        $new_build_group->SetProjectId($projectid);
        $new_build_group->SetName('delete me');
        $new_build_group->SetEndTime(Carbon::create(2010));
        $new_build_group->Save();
        $new_group_id = $new_build_group->GetId();

        $existing_build_group = new BuildGroup();
        $existing_build_group->SetProjectId($projectid);
        $existing_build_group->SetName('Experimental');
        $existing_group_id = $existing_build_group->GetId();
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO build2grouprule (groupid, endtime)
            VALUES (:groupid, :endtime)');
        $query_params = [
            ':groupid' => $existing_group_id,
            ':endtime' => '2010-02-25 00:00:00',
        ];
        $db->execute($stmt, $query_params);

        // Looks like it's a new day
        DB::delete("DELETE FROM dailyupdate WHERE projectid='{$projectid}'");

        $testxml2 = "$rep/2_test.xml";
        if (!$this->submission('EmailProjectExample', $testxml2)) {
            $this->fail('submission 2 failed');
            return 1;
        }

        // The removal of the builds are done asynchronously so we might need to wait a little bit
        // in order for the process to be done
        sleep(10); // seconds

        $sql = "SELECT id FROM build WHERE projectid=:id AND stamp=:stamp";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $projectid);
        $stmt->bindValue(':stamp', '20090223-0100-Nightly');

        // Check that the first test is gone
        if (!$stmt->execute()) {
            $this->fail('pdo_query returned false');
            return 1;
        }

        if (pdo_num_rows($stmt) > 0) {
            $this->fail('Auto remove build on submit failed');
            return 1;
        }

        // Check that the build group and rule were properly deleted.
        $stmt = $db->prepare('SELECT id FROM buildgroup WHERE id = ?');
        $db->execute($stmt, [$new_group_id]);
        if ($stmt->fetchColumn()) {
            $this->fail('build group not deleted');
        }
        $stmt = $db->prepare('SELECT groupid FROM build2grouprule WHERE id = ? AND endtime = ?');
        $db->execute($stmt, [$existing_group_id, '2010-01-01 00:00:00']);
        if ($stmt->fetchColumn()) {
            $this->fail('build group rule not deleted');
        }

        // Make sure we didn't inadvertently delete the whole upload directory.
        if (!file_exists(base_path('app/cdash/public/upload'))) {
            $this->fail('upload directory does not exist');
        }

        // Make sure the dailyupdate was recorded for the correct day.
        $stmt = $db->prepare('SELECT date FROM dailyupdate WHERE projectid = ?');
        $db->execute($stmt, [$projectid]);
        $found = $stmt->fetchColumn();
        $project = new Project();
        $project->Id = $projectid;
        $project->Fill();
        $expected = TestingDay::get($project, date(FMT_DATETIME));
        $this->assertEqual($expected, $found);

        $this->pass('Passed');
    }
}
