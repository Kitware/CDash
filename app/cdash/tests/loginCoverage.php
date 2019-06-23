<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class LoginTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testLogin()
    {
        $content = $this->login('baduser@badhost.com');
        $this->assertText('Wrong email or password.');

        $content = $this->login('simpletest@localhost', 'badpasswd');
        $this->assertText('Wrong email or password.');

        $content = $this->logout();
        $this->assertText('Login');
    }
}
