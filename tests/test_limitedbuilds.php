<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Project;

class LimitedBuildsTestCase extends KWWebTestCase
{
    private $testDataFiles;
    private $testDataDir;
    private $builds;
    private $parentBuilds;

    public function __construct()
    {
        parent::__construct();

        $this->testDataDir = dirname(__FILE__) . '/data/BuildModel';
        $this->LimitBuildsLine = '$CDASH_BUILDS_PER_PROJECT = 1;';
        $this->UnlimitedProjectLine = '$CDASH_UNLIMITED_PROJECTS = [\'Unlimited\'];';
        $this->PDO = get_link_identifier()->getPdo();
        $this->Projects = [];
    }

    /* TODO: REWRITE TEST
    public function testSetup()
    {
        // Enable config settings to test.
        $this->addLineToConfig($this->LimitBuildsLine);
        $this->addLineToConfig($this->UnlimitedProjectLine);

        // Create testing projects.
        $insert_stmt = $this->PDO->prepare('INSERT INTO project (name) VALUES (?)');

        $insert_stmt->execute(['Limited']);
        $limited = new Project();
        $limited->Id = pdo_insert_id('project');
        $this->Projects[] = $limited;

        $insert_stmt->execute(['Unlimited']);
        $unlimited = new Project();
        $unlimited->Id = pdo_insert_id('project');
        $this->Projects[] = $unlimited;
    }

    public function testLimitedBuilds()
    {
        // Submit two builds to the 'Limited' project.
        // The second submission is expected to fail.
        $this->submission('Limited', $this->testDataDir . '/build1.xml');
        $this->submission('Limited', $this->testDataDir . '/build2.xml');
        $this->assertTrue($this->Projects[0]->GetNumberOfBuilds() === 1);

        // Verify that index.php warns about the project being full.
        $response = $this->get($this->url . '/api/v1/index.php?project=Limited');
        $response = json_decode($response);
        $this->assertTrue(strpos($response->warning, 'Maximum number of builds reached') === 0);
    }

    public function testUnlimitedBuilds()
    {
        // Submit two builds to the 'Unlimited' project.
        // Both submissions are expected to pass.
        $this->submission('Unlimited', $this->testDataDir . '/build1.xml');
        $this->submission('Unlimited', $this->testDataDir . '/build2.xml');
        $this->assertTrue($this->Projects[1]->GetNumberOfBuilds() === 2);

        // Verify that index.php shows no warning.
        $response = $this->get($this->url . '/api/v1/index.php?project=Unlimited');
        $response = json_decode($response);
        $this->assertFalse(property_exists($response, 'warning'));
    }

    public function __destruct()
    {
        foreach ($this->Projects as $project) {
            remove_project_builds($project->Id);
        }
        $delete_stmt = $this->PDO->prepare('DELETE FROM project WHERE name = ?');
        $delete_stmt->execute(['Limited']);
        $delete_stmt->execute(['Unlimited']);
        $this->removeLineFromConfig($this->LimitBuildsLine);
        $this->removeLineFromConfig($this->UnlimitedProjectLine);
    }
    */
}
