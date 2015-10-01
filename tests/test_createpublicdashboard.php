<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class CreatePublicDashboardTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testCreatePublicDashboard()
    {
        $content = $this->connect($this->url);
        if (!$content) {
            return;
        }

        $this->login();
        if (!$this->analyse($this->clickLink('Create new project'))) {
            return;
        }

        $this->setField('name', 'PublicDashboard');
        $this->setField('description', 'This project is for CMake dashboards run on this machine to submit to from their test suites... CMake dashboards on this machine should set CMAKE_TESTS_CDASH_SERVER to "'.$this->url.'"');
        $this->setField('public', '1');
        $this->setField('emailAdministrator', '1');
        $this->clickSubmitByName('Submit');

        $this->checkErrors();
        $this->assertText('The project PublicDashboard has been created successfully.');
    }
}
