<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use CDash\Config;
use CDash\Database;
use CDash\Model\BuildGroup;

require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';

class AutoRemoveBuildsOnSubmitTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function enableAutoRemoveConfigSetting()
    {
        $filename = dirname(__FILE__) . '/../config/config.local.php';
        $handle = fopen($filename, 'r');
        $contents = fread($handle, filesize($filename));
        fclose($handle);
        unset($handle);
        $handle = fopen($filename, 'w');
        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            if (strpos($line, '?>') !== false) {
                fwrite($handle, '// test config settings injected by file [' . __FILE__ . "]\n");
                fwrite($handle, '$CDASH_AUTOREMOVE_BUILDS = \'1\';' . "\n");
                fwrite($handle, '$CDASH_ASYNCHRONOUS_SUBMISSION = false;' . "\n");
            }
            if ($line != '') {
                fwrite($handle, "$line\n");
            }
        }
        fclose($handle);
        unset($handle);
    }

    public function setAutoRemoveTimeFrame()
    {
        // set project autoremovetimeframe
        $result = $this->db->query('UPDATE project ' .
            "SET autoremovetimeframe='7' WHERE name='EmailProjectExample'");
    }

    public function testBuildsRemovedOnSubmission()
    {
        $this->enableAutoRemoveConfigSetting();

        $this->setAutoRemoveTimeFrame();
        $this->deleteLog($this->logfilename);

        $result = $this->db->query("SELECT id FROM project WHERE name = 'EmailProjectExample'");
        $projectid = $result[0]['id'];

        // Submit the first build
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $testxml1 = "$rep/1_test.xml";
        if (!$this->submission('EmailProjectExample', $testxml1)) {
            $this->fail('submission 1 failed');
            return;
        }

        // Check that the test is actually there
        if (!$query = pdo_query("SELECT name FROM build WHERE projectid='$projectid' AND stamp='20090223-0100-Nightly'")) {
            $this->fail('pdo_query returned false');
            return 1;
        }
        $query_array = pdo_fetch_array($query);
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
        $new_build_group->SetEndTime('2010-01-01 00:00:00');
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
            ':endtime' => '2010-02-25 00:00:00'
        ];
        $db->execute($stmt, $query_params);

        // Looks like it's a new day
        $this->pdo->query("DELETE FROM dailyupdate WHERE projectid='$projectid'");

        $testxml2 = "$rep/2_test.xml";
        if (!$this->submission('EmailProjectExample', $testxml2)) {
            $this->fail('submission 2 failed');
            return 1;
        }

        // The removal of the builds are done asynchronously so we might need to wait a little bit
        // in order for the process to be done
        sleep(10); // seconds

        // Check that the first test is gone
        if (!$query = pdo_query("SELECT id FROM build WHERE projectid='$projectid' AND stamp='20090223-0100-Nightly'")) {
            $this->fail('pdo_query returned false');
            return 1;
        }

        if (pdo_num_rows($query) > 0) {
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
        $config = Config::getInstance();
        if (!file_exists("{$config->get('CDASH_ROOT_DIR')}/public/upload")) {
            $this->fail('upload directory does not exist');
        }

        $this->pass('Passed');
    }
}
