<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class NoBackupTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->ConfigLine = '$CDASH_BACKUP_TIMEFRAME = \'0\';';
    }

    public function testNoBackup()
    {
        // Enable config setting.
        $this->addLineToConfig($this->ConfigLine);

        // Submit XML file.
        $xml = dirname(__FILE__) . '/data/nobackup/Build.xml';
        if (!$this->submission('InsightExample', $xml)) {
            $this->fail('failed to submit Build.xml');
            return 1;
        }

        // Submit gcov.tar to test the 'PUT' submission path.
        $post_result = $this->post($this->url . '/submit.php', array(
            'project' => 'InsightExample',
            'build' => 'nobackup',
            'site' => 'localhost',
            'stamp' => '20161004-0500-Nightly',
            'starttime' => '1475599870',
            'endtime' => '1475599870',
            'track' => 'Nightly',
            'type' => 'GcovTar',
            'datafilesmd5[0]=' => '5454e16948a1d58d897e174b75cc5633'));
        $post_json = json_decode($post_result, true);
        if ($post_json['status'] != 0) {
            $this->fail(
                'POST returned ' . $post_json['status'] . ":\n" .
                $post_json['description'] . "\n");
            return 1;
        }
        $buildid = $post_json['buildid'];
        if (!is_numeric($buildid) || $buildid < 1) {
            $this->fail(
                "Expected positive integer for buildid, instead got $buildid");
            return 1;
        }
        $puturl = $this->url . "/submit.php?type=GcovTar&md5=5454e16948a1d58d897e174b75cc5633&filename=gcov.tar&buildid=$buildid";
        $filename = dirname(__FILE__) . '/data/gcov.tar';
        $put_result = $this->uploadfile($puturl, $filename);
        $put_json = json_decode($put_result, true);
        if ($put_json['status'] != 0) {
            $this->fail(
                'PUT returned ' . $put_json['status'] . ":\n" .
                $put_json['description'] . "\n");
            return 1;
        }

        // Make sure they were both parsed correctly.
        echo "Waiting for async processing (2 seconds)\n";
        sleep(2);

        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->prepare(
                'SELECT b.builderrors, cs.loctested FROM build b
                JOIN coveragesummary cs ON (cs.buildid=b.id)
                WHERE b.id=?');
        $stmt->execute(array($buildid));
        $row = $stmt->fetch();
        if ($row['builderrors'] != 0) {
            $this->fail("Unexpected number of build errors found");
        }
        if ($row['loctested'] < 1) {
            $this->fail("Unexpected number of loctested found");
        }

        $this->checkLog($this->logfilename);

        // Cleanup
        $this->removeLineFromConfig($this->ConfigLine);
        remove_build($buildid);
    }
}
