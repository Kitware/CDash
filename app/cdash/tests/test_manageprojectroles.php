<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ManageProjectRolesTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testRegisterUser()
    {
        if (!$this->connectAndGetProjectId()) {
            return 1;
        }
        $this->get($this->url . "/manageProjectRoles.php?projectid=$this->projectid#fragment-3");
        if (!$this->setFieldByName('registeruseremail', 'simpleuser@localhost')) {
            $this->fail('Set user email returned false');
            return 1;
        }
        if (!$this->setFieldByName('registeruserfirstname', 'Simple')) {
            $this->fail('Set user first name returned false');
            return 1;
        }
        if (!$this->setFieldByName('registeruserlastname', 'User')) {
            $this->fail('Set user last name returned false');
            return 1;
        }
        if (!$this->setFieldByName('registeruserrepositorycredential', 'simpleuser')) {
            $this->fail('Set user repository credential returned false');
            return 1;
        }
        $this->clickSubmitByName('registerUser');
        if (strpos($this->getBrowser()->getContentAsText(), 'simpleuser@localhost') === false) {
            $this->fail("'simpleuser@localhost' not found when expected");
            return 1;
        }
        $this->pass('Passed');
    }

    public function connectAndGetProjectId()
    {
        $this->login();

        //get projectid for PublicDashboards
        $content = $this->connect($this->url . '/manageProjectRoles.php');
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (strpos($line, 'PublicDashboard') !== false) {
                preg_match('#<option value="([0-9]+)"#', $line, $matches);
                $this->projectid = $matches[1];
                break;
            }
        }
        if ($this->projectid === -1) {
            $this->fail('Unable to find projectid for PublicDashboard');
            return false;
        }
        return true;
    }
}
