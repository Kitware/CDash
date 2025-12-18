<?php

require_once __DIR__ . '/cdash_test_case.php';
use CDash\Database;
use Tests\Traits\CreatesProjects;

class ChangeIdTestCase extends KWWebTestCase
{
    use CreatesProjects;

    protected $ProjectId;

    public function __construct()
    {
        parent::__construct();
        $this->ProjectId = -1;
    }

    public function testChangeId(): void
    {
        $project = $this->makePublicProject();
        $this->ProjectId = $project->id;
        $project->save([
            'cvsurl' => 'github.com/Kitware/ChangeIdProject',
            'cvsviewertype' => 'github',
        ]);

        // Submit our testing data.
        $dir = __DIR__ . '/data/GithubPR';
        $this->submission($project->name, "$dir/UpdateBug_Build.xml");
        $this->submission($project->name, "$dir/UpdateBug_Test.xml");
        $this->submission($project->name, "$dir/Update.xml");

        // Make sure the builds have a changeid associated with them.
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT changeid, generator FROM build WHERE projectid = :projectid');
        $db->execute($stmt, [':projectid' => $this->ProjectId]);
        $rows = $stmt->fetchAll();
        $this->assertEqual(count($rows), 2);
        foreach ($rows as $row) {
            $this->assertEqual($row['changeid'], 555);
            $this->assertEqual($row['generator'], 'ctest-3.14.0-rc1');
        }

        $project->delete();
    }
}
