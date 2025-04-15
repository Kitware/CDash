<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use App\Models\User;
use Illuminate\Support\Carbon;

require_once dirname(__FILE__) . '/cdash_test_case.php';

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
        /** @var User $user */
        $user = User::firstWhere('email', 'simpletest@localhost');
        $user->password = password_hash('simpletest', PASSWORD_DEFAULT);
        $user->password_updated_at = Carbon::now();
        if (!$user->save()) {
            $this->fail('user->save() returned false');
        }
    }
}
