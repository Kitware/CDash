<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');
require_once('cdash/common.php');
require_once('cdash/pdo.php');

class ExpectedAndMissingTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
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
        // Mark an old build as expected.
        $query ="
            SELECT b.siteid, b.type, g.id AS groupid FROM build AS b
            INNER JOIN build2group AS b2g ON (b.id=b2g.buildid)
            INNER JOIN buildgroup AS g ON (b2g.groupid=g.id)
            WHERE b.name='$buildname'";
        if ($projectname === 'Trilinos') {
            $query .= " AND b.parentid=-1";
        }
        $build_row = pdo_single_row_query($query);

        $groupid = $build_row['groupid'];
        $buildtype = $build_row['type'];
        $siteid = $build_row['siteid'];
        if (!pdo_query("
                    INSERT INTO build2grouprule(groupid,buildtype,buildname,siteid,expected,starttime,endtime)
                    VALUES ('$groupid','$buildtype','$buildname','$siteid','1','2013-01-01 00:00:00','1980-01-01 00:00:00')")) {
            $this->fail("Error marking $buildname as expected");
            return 1;
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

        // Make it unexpected again.
        pdo_query("DELETE FROM build2grouprule WHERE buildname='$buildname'");

        if (!$found) {
            $this->fail("Expected missing build '$buildname' not included");
            return 1;
        }

        $this->pass("Passed");
        return 0;
    }
}
