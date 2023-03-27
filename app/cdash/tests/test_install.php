<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'tests/install_test.php';

class InstallTestCase extends BaseInstallTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testInstall()
    {
        $this->install();
    }
}
