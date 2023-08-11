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
        $empty_content = $this->get($this->url . '/api/v1/getuserid.php?author=simpleuser@localhost');
        if ($empty_content !== '') {
            $this->fail("Expected valid output not found when querying API for userid while unauthenticated: $empty_content");
        }

        $version = $this->get($this->url . '/api/v1/getversion.php');
        $config = \CDash\Config::getInstance();

        if ($version !== $config->get('CDASH_VERSION')) {
            $this->fail("Expected output not found when querying API for version: $version");
        }

        $hasfile = $this->get($this->url . '/api/v1/hasfile.php');
        if ($hasfile !== 'md5sum not specified') {
            $this->fail("No output found when querying API for hasfile: $hasfile");
        }

        $hasfile = $this->get($this->url . '/api/v1/hasfile.php?md5sums=1q2w3e4r5t');
        if (strpos($hasfile, '1q2w3e4r5t') === false) {
            $this->fail("Expected output not found when querying API for hasfile\n$hasfile\n");
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
