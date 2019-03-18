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
use CDash\Model\BuildGroupRule;

class ExpectedAndMissingTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->PDO = Database::getInstance();
        $this->PDO->getPdo();
    }

    public function testParentExpected()
    {
        return $this->expectedTest('Windows_NT-MSVC10-SERIAL_DEBUG_DEV',
            'Trilinos');
    }

    public function testNormalExpected()
    {
        return $this->expectedTest('Linux-g++-4.1-LesionSizingSandbox_Debug',
            'InsightExample');
    }

    private function expectedTest($buildname, $projectname)
    {
        // Find the id of an old build.
        $query = 'SELECT id FROM build WHERE name = :buildname';
        if ($projectname === 'Trilinos') {
            $query .= ' AND parentid = -1';
        }
        $stmt = $this->PDO->prepare($query);
        $this->PDO->execute($stmt, [':buildname' => $buildname]);
        $buildid = $stmt->fetchColumn();

        $build = new Build();
        $build->Id = $buildid;
        $build->FillFromId($buildid);

        // Mark this build as expected.
        $rule = new BuildGroupRule($build);
        $rule->Expected = 1;
        if (!$rule->SetExpected()) {
            $this->fail("Error marking $buildname as expected");
        }

        // Verify that our API lists this build even though it hasn't submitted today.
        $this->get($this->url . "/api/v1/index.php?project=$projectname");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);

        $found = false;
        foreach ($buildgroup['builds'] as $build) {
            if ($build['buildname'] == $buildname && $build['expectedandmissing'] == 1) {
                $found = true;
            }
        }

        // Make it unexpected again by hard-deleting this buildgroup rule.
        $rule->Delete(false);
        pdo_query("DELETE FROM build2grouprule WHERE buildname='$buildname'");

        if (!$found) {
            $this->fail("Expected missing build '$buildname' not included");
        }
    }
}
