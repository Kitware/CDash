<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'models/user.php';

class RecoverPasswordTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->deleteLog($this->logfilename);
    }

    public function testRecoverPassword()
    {
        $this->login();
        $this->get($this->url . '/recoverPassword.php');
        if (strpos($this->getBrowser()->getContentAsText(), 'your email address') === false) {
            $this->fail("'your email address' not found when expected.");
            return 1;
        }

        if (!$this->setFieldByName('email', 'simpletest@localhost')) {
            $this->fail('Failed to set email');
            return 1;
        }
        if (!$this->clickSubmitByName('recover')) {
            $this->fail('clicking recover returned false');
        }

        // fix the password so others can still login...
        $user = new User();
        $userid = $user->GetIdFromEmail('simpletest@localhost');
        $user->Password = User::PasswordHash('simpletest');
        if (!$user->Save()) {
            $this->fail('user->Save() returned false');
        }
    }
}
