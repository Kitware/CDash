<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Database;
use CDash\Model\Build;
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
        $this->get_build_stmt =  $this->PDO->prepare('SELECT id FROM build WHERE projectid = ?');
        $this->Projects = [];

        $this->deleteLog($this->logfilename);
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

    private function submitBuild($num, $project_name)
    {
        $proj_param = ['project' => $project_name];
        $this->putCtestFile("{$this->testDataDir}/build{$num}.xml", $proj_param);
        $this->putCtestFile("{$this->testDataDir}/configure{$num}.xml", $proj_param);
    }

    public function testLimitedBuilds()
    {
        config([
            'cdash.builds_per_project' => 1,
            'cdash.unlimited_projects' => ['Unlimited'],
        ]);

        // Submit two builds to the 'Limited' project.
        // The second submission will cause the first build to get deleted.
        $this->submitBuild(1, 'Limited');
        $this->assertEqual($this->Projects[0]->GetNumberOfBuilds(), 1);

        $this->get_build_stmt->execute([$this->Projects[0]->Id]);
        $buildid1 = $this->get_build_stmt->fetchColumn();

        $this->submitBuild(2, 'Limited');
        $this->assertEqual($this->Projects[0]->GetNumberOfBuilds(), 1);

        $this->get_build_stmt->execute([$this->Projects[0]->Id]);
        $buildid2 = $this->get_build_stmt->fetchColumn();

        $this->assertTrue($buildid1 !== $buildid2);

        $build = new Build();
        $build->Id = $buildid1;
        $this->assertFalse($build->Exists());
        $build->Id = $buildid2;
        $this->assertTrue($build->Exists());
    }

    public function testUnlimitedBuilds()
    {
        config([
            'cdash.builds_per_project' => 1,
            'cdash.unlimited_projects' => ['Unlimited'],
        ]);

        // Submit two builds to the 'Unlimited' project.
        $this->submitBuild(1, 'Unlimited');
        $this->submitBuild(2, 'Unlimited');

        // Verify that they both exist.
        $this->assertEqual($this->Projects[1]->GetNumberOfBuilds(), 2);
    }

    public function __destruct()
    {
        foreach ($this->Projects as $project) {
            remove_project_builds($project->Id);
        }
        $delete_stmt = $this->PDO->prepare('DELETE FROM project WHERE name = ?');
        $delete_stmt->execute(['Limited']);
        $delete_stmt->execute(['Unlimited']);
        $this->deleteLog($this->logfilename);
    }
}
