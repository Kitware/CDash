<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';



class NoBackupTestCase extends KWWebTestCase
{
    protected $ConfigFile;
    protected $Originals;

    public function __construct()
    {
        parent::__construct();
        $this->ConfigFile = dirname(__FILE__) . '/../../../.env';
        $this->Original = file_get_contents($this->ConfigFile);
    }

    public function __destruct()
    {
        file_put_contents($this->ConfigFile, $this->Original);
    }

    public function testNoBackup()
    {
        // Enable config setting.
        file_put_contents($this->ConfigFile, "BACKUP_TIMEFRAME=0\n", FILE_APPEND | LOCK_EX);

        // Submit XML file.
        $xml = dirname(__FILE__) . '/data/nobackup/Build.xml';
        if (!$this->submission('InsightExample', $xml)) {
            $this->fail('failed to submit Build.xml');
            return 1;
        }

        // Submit gcov.tar to test the 'PUT' submission path.
        $post_result = $this->post($this->url . '/submit.php', [
            'project' => 'InsightExample',
            'build' => 'nobackup',
            'site' => 'localhost',
            'stamp' => '20161004-0500-Nightly',
            'starttime' => '1475599870',
            'endtime' => '1475599870',
            'track' => 'Nightly',
            'type' => 'GcovTar',
            'datafilesmd5[0]=' => '5454e16948a1d58d897e174b75cc5633']);
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
        if (strpos($put_result, '{"status":0}') === false) {
            $this->fail(
                "status:0 not found in PUT results:\n$put_result\n");
            return 1;
        }

        // Make sure they were both parsed correctly.
        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->prepare(
            'SELECT b.builderrors, cs.loctested FROM build b
                JOIN coveragesummary cs ON (cs.buildid=b.id)
                WHERE b.id=?');
        $stmt->execute([$buildid]);
        $row = $stmt->fetch();
        if ($row['builderrors'] != 0) {
            $this->fail("Unexpected number of build errors found");
        }
        if ($row['loctested'] < 1) {
            $this->fail("Unexpected number of loctested found");
        }

        $this->checkLog($this->logfilename);

        remove_build($buildid);
    }
}
