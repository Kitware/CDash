<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use CDash\Database;
use CDash\Model\Project;

require_once dirname(__FILE__) . '/cdash_test_case.php';

class ManageCoverageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testManageCoverageTest()
    {
        $project = new Project();
        $project->Name = 'InsightExample';
        $projectid = $project->GetIdByName();

        // Get projectid for InsightExample.
        if ($projectid <= 0) {
            $this->fail('Unable to find projectid for InsightExamples');
            return 1;
        }

        // make sure we can't visit the manageCoverage page while logged out
        $this->logout();
        if (!$this->expectsPageRequiresLogin('/manageCoverage.php')) {
            return 1;
        }

        // get a valid coverage buildid
        $db = Database::getInstance();
        $db->getPdo();
        $stmt = $db->prepare("SELECT id FROM build WHERE name LIKE '%simple' AND projectid = :projectid");
        $db->execute($stmt, [':projectid' => $projectid]);
        $buildid = $stmt->fetchColumn();
        $retries = 0;
        while ($buildid === false) {
            $retries++;
            if ($retries > 10) {
                $this->fail('Too many attempts to find buildid');
                return 1;
            }
            sleep(1);
            $db->execute($stmt, [':projectid' => $projectid]);
            $buildid = $stmt->fetchColumn();
        }

        $this->login();
        $content = $this->connect($this->url . "/manageCoverage.php?buildid=$buildid&projectid=$projectid");
        if (strpos($content, 'simple.cxx') === false) {
            $this->fail("'simple.cxx' not found when expected for buildid=" . $buildid);
            return 1;
        }

        if (!$this->setFieldByName('prioritySelection', 2)) {
            $this->fail('SetFieldByName #1 returned false');
            return 1;
        }

        $this->pass('Passed');
        return 0;
    }
}
