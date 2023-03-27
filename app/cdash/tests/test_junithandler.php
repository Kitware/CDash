<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Project;

class JUnitHandlerTestCase extends KWWebTestCase
{
    protected $PDO;
    protected $Project;

    public function __construct()
    {
        parent::__construct();
        $this->PDO = get_link_identifier()->getPdo();
        $this->Project = null;
    }

    public function __destruct()
    {
        if ($this->Project) {
            remove_project_builds($this->Project->Id);
            $this->Project->Delete();
        }
    }

    public function testJUnitHandler()
    {
        // Login as admin.
        $this->login();

        // Create project.
        $settings = [
            'Name' => 'JUnitHandlerProject',
            'Public' => 1
        ];
        $projectid = $this->createProject($settings);
        if ($projectid < 1) {
            $this->fail('Failed to create project');
        }
        $this->Project = new Project();
        $this->Project->Id = $projectid;

        // Submit our test data.
        $xml = dirname(__FILE__) . '/data/JUNit_example.xml';
        if (!$this->submission('JUnitHandlerProject', $xml)) {
            $this->fail('Failed to submit test data');
        }

        // Get newly created buildid.
        $stmt = $this->PDO->query("SELECT id, stamp FROM build WHERE name = 'junit-test-build'");
        $row = $stmt->fetch();
        $buildid = $row['id'];
        if ($buildid < 1) {
            $this->fail('Failed to create build');
        }

        // Verify that we honored its custom track.
        $stamp = $row['stamp'];
        $expected = '20170517-1200-my-custom-track';
        if ($stamp !== $expected) {
            $this->fail("Expected stamp to be $expected, found $stamp");
        }

        // Make sure the testing results look correct.
        $this->get("$this->url/api/v1/viewTest.php?buildid=$buildid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        if ($jsonobj['numPassed'] !== 2) {
            $this->fail("Did not find 2 'Passed' tests when expected");
        }
        if ($jsonobj['numFailed'] !== 3) {
            $this->fail("Did not find 3 'Failed' tests when expected");
        }
        if ($jsonobj['numNotRun'] !== 1) {
            $this->fail("Did not find 1 'Not Run' test when expected");
        }
        if (trim($jsonobj['totaltime']) !== '500ms') {
            $this->fail("Did not find 500ms totaltime when expected");
        }
    }
}
