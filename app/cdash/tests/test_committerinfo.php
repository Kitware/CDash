<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class CommitterInfoTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testCommitterInfo()
    {
        $file = dirname(__FILE__) . '/data/git-Update2.xml';
        if (!$this->submission('TestCompressionExample', $file)) {
            return;
        }

        $query = $this->db->query("SELECT committer,committeremail FROM updatefile WHERE filename='fakegitfile1.txt'");
        $committer = $query[0]['committer'];
        $committerEmail = $query[0]['committeremail'];

        if ($committer != 'Test Committer') {
            $this->fail("Incorrect update committer value: expected 'Test Committer' but was '$committer'");
            return;
        }

        if ($committerEmail != 'simpleuser@localhost') {
            $this->fail("Incorrect update committer email value: expected 'simpleuser@localhost' but was '$committerEmail'");
            return;
        }

        $this->pass('Test passed');
    }
}
