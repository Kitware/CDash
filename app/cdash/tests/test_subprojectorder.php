<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Database;
use CDash\Model\Project;

class SubProjectOrderTestCase extends KWWebTestCase
{
    private $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = null;
    }

    public function __destruct()
    {
        // Delete project & build created by this test.
        if ($this->project) {
            remove_project_builds($this->project->Id);
            $this->project->Delete();
        }
    }

    public function testSubProjectOrder()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'SubProjectOrder',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        $test_data = dirname(__FILE__) . '/data/MultipleSubprojects/Build.xml';
        if (!$this->submission('SubProjectOrder', $test_data)) {
            $this->fail('failed to submit Build.xml');
        }

        // Get the parent build we created.
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT id FROM build
            WHERE projectid = :projectid AND parentid = -1');
        $db->execute($stmt, [':projectid' => $this->project->Id]);
        $parent_buildid = $stmt->fetchColumn();

        // Verify subproject order.
        $this->get("{$this->url}/api/v1/index.php?project=SubProjectOrder&parentid={$parent_buildid}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $this->assertTrue(1 === $buildgroup['builds'][0]['position']);
        $this->assertTrue(2 === $buildgroup['builds'][1]['position']);
        $this->assertTrue(3 === $buildgroup['builds'][2]['position']);
    }
}
