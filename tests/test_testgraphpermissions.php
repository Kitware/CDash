<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class TestGraphPermissionsTestCase extends KWWebTestCase
{
    public function testTestGraphPermissions()
    {
        $pdo = get_link_identifier()->getPdo();

        // Get buildid
        $stmt = $pdo->query("SELECT id, projectid FROM build WHERE name = 'Linux-g++-4.1-LesionSizingSandbox_Debug'");
        $row = $stmt->fetch();
        $buildid = $row['id'];
        $projectid = $row['projectid'];

        // Get testid
        $stmt = $pdo->prepare("SELECT testid FROM build2test WHERE buildid=?");
        $stmt->execute([$buildid]);
        $row = $stmt->fetch();
        $testid = $row['testid'];

        // Make project private
        $stmt = $pdo->prepare('UPDATE project SET public=0 WHERE id=?');
        $stmt->execute([$projectid]);

        // Verify that we cannot access the graphs (because we're not logged in)
        $response = $this->get($this->url . "/ajax/showtesttimegraph.php?buildid=$buildid&testid=$testid");
        if ($response !== 'You are not authorized to view this page.') {
            $this->fail("Unauthorized case #1 fails");
        }
        $response = $this->get($this->url . "/ajax/showtestpassinggraph.php?buildid=$buildid&testid=$testid");
        if ($response !== 'You are not authorized to view this page.') {
            $this->fail("Unauthorized case #2 fails");
        }
        $response = $this->get($this->url . "/ajax/showtestfailuregraph.php?testname=itkVectorSegmentationLevelSetFunctionTest1&projectid=$projectid&starttime=1235350800");
        if ($response !== 'You are not authorized to view this page.') {
            $this->fail("Unauthorized case #3 fails");
        }

        // Login and make sure we can see the graphs now.
        $this->login();
        $response = $this->get($this->url . "/ajax/showtesttimegraph.php?buildid=$buildid&testid=$testid");
        if (!json_decode($response)) {
            $this->fail("Authorized case #1 fails");
        }
        $response = $this->get($this->url . "/ajax/showtestpassinggraph.php?buildid=$buildid&testid=$testid");
        if (!json_decode($response)) {
            $this->fail("Authorized case #2 fails");
        }
        $response = $this->get($this->url . "/ajax/showtestfailuregraph.php?testname=itkVectorSegmentationLevelSetFunctionTest1&projectid=$projectid&starttime=1235350800");
        if (strpos($response, '<br>') === false) {
            $this->fail("Authorized case #3 fails");
        }

        // Restore project to public status.
        $stmt = $pdo->prepare('UPDATE project SET public=1 WHERE id=?');
        $stmt->execute([$projectid]);
    }
}
