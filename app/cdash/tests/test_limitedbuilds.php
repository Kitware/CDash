<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Config;
use CDash\Database;
use CDash\Model\Project;

class LimitedBuildsTestCase extends KWWebTestCase
{
    private $testDataDir;
    private $Projects;

    public function __construct()
    {
        parent::__construct();

        $this->testDataDir = dirname(__FILE__) . '/data/BuildModel';
        $this->PDO = Database::getInstance();
        $this->Projects = [];
    }

    public function testSetup()
    {
        // Create testing projects.
        $limited = new Project();
        $limited->Id = $this->createProject(['Name' => 'Limited']);
        $this->Projects[] = $limited;

        $unlimited = new Project();
        $unlimited->Id = $this->createProject(['Name' => 'Unlimited']);
        $this->Projects[] = $unlimited;
    }

    public function testLimitedBuilds()
    {
        $config = Config::getInstance();
        $config->set('CDASH_BUILDS_PER_PROJECT', 1);
        $config->set('CDASH_UNLIMITED_PROJECTS', ['Unlimited']);

        // Submit two builds to the 'Limited' project.
        // The second submission is expected to fail.
        $build1 = "{$this->testDataDir}/build1.xml";
        $build2 = "{$this->testDataDir}/build2.xml";
        $this->putCtestFile($build1, ['project' => 'Limited']);
        $this->putCtestFile($build2, ['project' => 'Limited']);
        $this->assertTrue($this->Projects[0]->GetNumberOfBuilds() === 1);

        // Verify that index.php warns about the project being full.
        $response = $this->get($this->url . '/api/v1/index.php?project=Limited');
        $response = json_decode($response);
        $this->assertTrue(strpos($response->warning, 'Maximum number of builds reached') === 0);
    }

    public function testUnlimitedBuilds()
    {
        $config = Config::getInstance();
        $config->set('CDASH_BUILDS_PER_PROJECT', 1);
        $config->set('CDASH_UNLIMITED_PROJECTS', ['Unlimited']);

        // Submit two builds to the 'Unlimited' project.
        // Both submissions are expected to pass.
        $build1 = "{$this->testDataDir}/build1.xml";
        $build2 = "{$this->testDataDir}/build2.xml";
        $this->putCtestFile($build1, ['project' => 'Unlimited']);
        $this->putCtestFile($build2, ['project' => 'Unlimited']);

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
    }
}
