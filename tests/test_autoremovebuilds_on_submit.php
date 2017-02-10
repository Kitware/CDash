<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
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
        $this->startCodeCoverage();

        $result = $this->db->query("SELECT id FROM project WHERE name = 'EmailProjectExample'");
        $projectid = $result[0]['id'];

        // Submit the first build
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $testxml1 = "$rep/1_test.xml";
        if (!$this->submission('EmailProjectExample', $testxml1)) {
            $this->fail('submission 1 failed');
            $this->stopCodeCoverage();
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

        // Looks like it's a new day
        $this->db->query("DELETE FROM dailyupdate WHERE projectid='$projectid'");

        $testxml2 = "$rep/2_test.xml";
        if (!$this->submission('EmailProjectExample', $testxml2)) {
            $this->fail('submission 2 failed');
            $this->stopCodeCoverage();
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

        // Make sure we didn't inadvertently delete the whole upload directory.
        global $CDASH_ROOT_DIR;
        if (!file_exists("$CDASH_ROOT_DIR/public/upload")) {
            $this->fail('upload diretory does not exist');
        }

        $this->pass('Passed');
        $this->stopCodeCoverage();
    }
}
