<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/user.php';

class AccountLockoutTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->ConfigFile = dirname(__FILE__) . '/../config/config.local.php';
        $this->AttemptsConfig = '$CDASH_LOCKOUT_ATTEMPTS = 2;';
        $this->LengthConfig = '$CDASH_LOCKOUT_LENGTH = 1;';
    }

    public function testAccountLockout()
    {
        // Enable our config settings.
        $this->addLineToConfig($this->AttemptsConfig);
        $this->addLineToConfig($this->LengthConfig);

        // Get the id for the simpletest user.
        $user = new User();
        $user->Email = 'simpletest@localhost';
        if (!$user->Exists()) {
            $this->fail("simpletest user does not exist");
            return false;
        }
        $userid = $user->Id;

        // Lock the account out by specifying the wrong password multiple times.
        $this->login('simpletest@localhost', 'asdf');
        $this->login('simpletest@localhost', 'asdf');

        // Make sure we get the same error message when we attempt to login
        // whether or not we use the correct password.
        $this->login('simpletest@localhost', 'asdf');
        $this->assertText('Your account is locked');
        $this->login('simpletest@localhost', 'simpletest');
        $this->assertText('Your account is locked');

        // Manually set the lock to expire.
        pdo_query(
            "UPDATE lockout SET unlocktime = '1980-01-01 00:00:00'
            WHERE userid=$userid");

        // Make sure we can successfully log in now.
        $this->login('simpletest@localhost', 'simpletest');
        $response = $this->get($this->url . '/api/v1/viewProjects.php');
        $response = json_decode($response, true);
        if ($response['user']['id'] < 1) {
            $this->fail("Failed to login after lock expiration");
        }

        $this->removeLineFromConfig($this->AttemptsConfig);
        $this->removeLineFromConfig($this->LengthConfig);
        $this->pass('Test passed');
    }
}
