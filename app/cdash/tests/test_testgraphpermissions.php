<?php

use CDash\Database;

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class TestGraphPermissionsTestCase extends KWWebTestCase
{
    private $build;

    private $project;

    public function __construct()
    {
        parent::__construct();

        $db = Database::getInstance();
        $stmt = $db->query("SELECT id, projectid FROM build WHERE name = 'Linux-g++-4.1-LesionSizingSandbox_Debug'");
        $row = $stmt->fetch();
        $this->build = $row['id'];
        $this->project = $row['projectid'];

        // Make project private
        $stmt = $db->prepare('UPDATE project SET public=0 WHERE id=?');
        $stmt->execute([$this->project]);
    }

    public function __destruct()
    {
        $db = Database::getInstance();
        // Restore project to public status.
        $stmt = $db->prepare('UPDATE project SET public=1 WHERE id=?');
        $stmt->execute([$this->project]);
    }

    public function testTestGraphPermissions(): void
    {
        $db = Database::getInstance();

        // Get testname
        $stmt = $db->prepare('SELECT testname FROM build2test WHERE buildid=?');
        $stmt->execute([$this->build]);
        $row = $stmt->fetch();
        $testname = $row['testname'];

        $result = $this->get($this->url . "/api/v1/testGraph.php?buildid={$this->build}&testname=$testname&type=time");
        // Verify that we cannot access the graphs (because we're not logged in)
        $response = json_decode($result, true);
        if ($response['error'] !== 'You do not have access to the requested project or the requested project does not exist.') {
            $this->fail('Unauthorized case #1 fails');
        }
        $response = json_decode($this->get($this->url . "/api/v1/testGraph.php?buildid={$this->build}&testname=$testname&type=status"), true);
        if ($response['error'] !== 'You do not have access to the requested project or the requested project does not exist.') {
            $this->fail('Unauthorized case #2 fails');
        }

        // Login and make sure we can see the graphs now.
        $this->login();
        $response = json_decode($this->get($this->url . "/api/v1/testGraph.php?buildid={$this->build}&testname=$testname&type=time"), true);
        if (array_key_exists('requirelogin', $response)) {
            $this->fail('Authorized case #1 fails');
        }
        $response = json_decode($this->get($this->url . "/api/v1/testGraph.php?buildid={$this->build}&testname=$testname&type=status"), true);
        if (array_key_exists('requirelogin', $response)) {
            $this->fail('Authorized case #2 fails');
        }
    }
}
