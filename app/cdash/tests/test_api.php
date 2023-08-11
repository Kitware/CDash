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
        $version = $this->get($this->url . '/api/v1/getversion.php');
        $config = \CDash\Config::getInstance();

        if ($version !== $config->get('CDASH_VERSION')) {
            $this->fail("Expected output not found when querying API for version: $version");
        }
    }
}
