<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/pdo.php';

class CreatePublicDashboardTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testCreatePublicDashboard()
    {
        $settings = array(
                'Name' => 'PublicDashboard',
                'Description' => "This project is for CMake dashboards run on this machine to submit to from their test suites... CMake dashboards on this machine should set CMAKE_TESTS_CDASH_SERVER to $this->url",
                'EmailAdministrator' => 1);
        $this->createProject($settings);
    }
}
