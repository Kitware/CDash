<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Model\User;

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
        if (!str_contains($this->getBrowser()->getContentAsText(), 'your email address')) {
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
        $user->Id = App\Models\User::firstWhere('email', 'simpletest@localhost')?->id;
        $user->Fill();
        $user->Password = password_hash('simpletest', PASSWORD_DEFAULT);
        if (!$user->Save()) {
            $this->fail('user->Save() returned false');
        }
    }
}
