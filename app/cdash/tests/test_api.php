<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';




class APITestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testAPI()
    {
        $buildid = $this->get($this->url . '/api/v1/getbuildid.php?project=EmailProjectExample&siteid=3&name=Win32-MSVC2009&stamp=20090223-0100-Nightly');
        if ($buildid !== '<?xml version="1.0" encoding="UTF-8"?><buildid>3</buildid>') {
            $this->fail("Expected output not found when querying API for buildid: $buildid");
        }
        $buildid = $this->get($this->url . '/api/v1/getbuildid.php?project=EmailProjectExample&site=Dash20.kitware&name=Win32-MSVC2009&stamp=20090223-0100-Nightly');
        if ($buildid !== '<?xml version="1.0" encoding="UTF-8"?><buildid>3</buildid>') {
            $this->fail("Expected output not found when querying by site name for buildid: $buildid");
        }

        $empty_content = $this->get($this->url . '/api/v1/getuserid.php?author=simpleuser@localhost');
        if ($empty_content !== '') {
            $this->fail("Expected valid output not found when querying API for userid while unauthenticated: $empty_content");
        }

        $version = $this->get($this->url . '/api/v1/getversion.php');
        $config = \CDash\Config::getInstance();

        if ($version !== $config->get('CDASH_VERSION')) {
            $this->fail("Expected output not found when querying API for version: $version");
        }

        $this->login();
        $userid = $this->get($this->url . '/api/v1/getuserid.php?author=simpleuser@localhost');
        if (!preg_match("/..xml version..1.0. encoding..UTF-8....userid.\d+..userid./", $userid)) {
            $this->fail("Output does not match expected pattern when querying API for userid (test1): $userid");
        }

        $userid = $this->get($this->url . '/api/v1/getuserid.php?author=simpleuser&project=PublicDashboard');
        if (!preg_match("/..xml version..1.0. encoding..UTF-8....userid.\d+..userid./", $userid)) {
            $this->fail("Output does not match expected pattern when querying API for userid (test2): $userid");
        }

        $userid = $this->get($this->url . '/api/v1/getuserid.php');
        if ($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>error<no-author-param/></userid>') {
            $this->fail("Expected error output not found when querying API for userid (test3): $userid");
        }

        $userid = $this->get($this->url . '/api/v1/getuserid.php?author=');
        if ($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>error<empty-author-param/></userid>') {
            $this->fail("Expected error output not found when querying API for userid (test4): $userid");
        }

        $userid = $this->get($this->url . '/api/v1/getuserid.php?author=blahblahblahblahblah');
        if ($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>error<no-project-param/></userid>') {
            $this->fail("Expected error output not found when querying API for userid (test5): $userid");
        }

        $userid = $this->get($this->url . '/api/v1/getuserid.php?author=blahblahblahblahblah&project=');
        if ($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>error<empty-project-param/></userid>') {
            $this->fail("Expected error output not found when querying API for userid (test6): $userid");
        }

        $userid = $this->get($this->url . '/api/v1/getuserid.php?author=blahblahblahblahblah&project=garbage-project-1234567890');
        if ($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>error<no-such-project/></userid>') {
            $this->fail("Expected error output not found when querying API for userid (test7): $userid");
        }

        $userid = $this->get($this->url . '/api/v1/getuserid.php?author=blahblahblahblahblah&project=PublicDashboard');
        if ($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>not found<no-such-user/></userid>') {
            $this->fail("Expected valid output not found when querying API for userid (test8): $userid");
        }
    }
}
