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
    protected $PDO;

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

        // Verify that our API lists this build even though it hasn't submitted on this day.
        $this->get($this->url . "/api/v1/index.php?project=$projectname&date=2019-04-18");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);

        $found = false;
        foreach ($buildgroup['builds'] as $build_response) {
            if ($build_response['buildname'] == $buildname && $build_response['expectedandmissing'] == 1) {
                $found = true;
            }
        }

        // Verify that the API tells us how long this build has been missing.
        $url = $this->url . "/api/v1/expectedbuild.php?siteid={$build->SiteId}&groupid={$build->GroupId}&name=" . urlencode($build->Name) . "&type={$build->Type}&currenttime=" . time();
        $this->get($url);
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        if (!array_key_exists('lastSubmission', $jsonobj)) {
            $this->fail('No lastSubmission found in response');
        }
        if ($jsonobj['lastSubmission'] == -1) {
            $this->fail('lastSubmission is -1');
        }
        if (strlen($jsonobj['lastSubmission']) < 3) {
            $this->fail('lastSubmission response shorter than expected');
        }

        // Use the API to soft-delete this rule.
        $this->login();
        $this->delete($url);
        $stmt = $this->PDO->prepare(
            'SELECT endtime FROM build2grouprule
            WHERE siteid    = :siteid    AND
                  groupid   = :groupid   AND
                  buildname = :buildname AND
                  buildtype = :buildtype');
        $query_params = [
            ':siteid' => $build->SiteId,
            ':groupid' => $build->GroupId,
            ':buildname' => $build->Name,
            ':buildtype' => $build->Type];
        $this->PDO->execute($stmt, $query_params);
        $endtime = $stmt->fetchColumn();
        if ($endtime === false) {
            $this->fail('No endtime found when expected');
        }
        if ($endtime === '1980-01-01 00:00:00') {
            $this->fail('API failed to soft delete');
        }
        if (strlen($endtime) < 3) {
            $this->fail('endtime shorter than expected');
        }

        // Make it unexpected again by hard-deleting this buildgroup rule.
        $rule->Delete(false);
        pdo_query("DELETE FROM build2grouprule WHERE buildname='$buildname'");

        if (!$found) {
            $this->fail("Expected missing build '$buildname' not included");
        }
    }
}
