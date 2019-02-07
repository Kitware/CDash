<?php

require_once dirname(__FILE__) . '/cdash_test_case.php';
use CDash\Database;

class ChangeIdTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->ProjectId = -1;
    }

    public function testChangeId()
    {
        $this->login();

        // Create a project for this test.
        $settings = [
            'Name' => 'ChangeIdProject',
            'Description' => 'ChangeIdProject',
            'CvsUrl' => 'github.com/Kitware/ChangeIdProject',
            'CvsViewerType' => 'github',
        ];
        $this->ProjectId = $this->createProject($settings);
        if ($this->ProjectId < 1) {
            $this->fail('Failed to create project');
            return;
        }

        // Submit our testing data.
        $dir = dirname(__FILE__) . '/data/GithubPR';
        $this->submission('ChangeIdProject', "$dir/UpdateBug_Build.xml");
        $this->submission('ChangeIdProject', "$dir/UpdateBug_Test.xml");

        // Make sure the build has a changeid associated with it.
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT changeid, generator FROM build WHERE projectid = :projectid');
        $db->execute($stmt, [':projectid' => $this->ProjectId]);
        $row = $stmt->fetch();
        if ($row['changeid'] != 555) {
            $this->fail('Expected changeid not found');
        }
        if ($row['generator'] != 'ctest-3.14.0-rc1') {
            $this->fail('Expected generator not found');
        }
        $this->deleteProject($this->ProjectId);
    }
}
