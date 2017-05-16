<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/pdo.php';

class LoginTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testLogin($email='simpletest@localhost', $password='simpletest')
    {
        $content = $this->connect($this->url);
        if ($content == false) {
            return;
        }
        $this->clickLink('Login');
        $this->setField('login', $email);
        $this->setField('passwd', $password);
        $this->clickSubmitByName('sent');
        $this->assertNoText('Wrong email or password');
    }

    public function testRegisterWithBotsDetection()
    {
        $content = $this->connect($this->url);
        if ($content == false) {
            return;
        }
        $this->analyse($this->clickLink('Register'));
        $this->fillOutRegisterForm('test@kw', 'kitware');
        $this->setField('url', 'catchbot');
        $this->clickSubmitByName('sent');
        $this->assertText('Bots are not allowed to obtain CDash accounts!', 'Bots detected in test_login.php.42');
    }

    public function testRegister()
    {
        $url = $this->url . '/register.php';
        $content = $this->connect($url);
        if ($content == false) {
            return;
        }
        $this->fillOutRegisterForm('test@kw', 'kitware');
        $this->clickSubmitByName('sent', array('url' => 'catchbot'));
        if (!$this->userExists('test@kw')) {
            $this->fail('Failed to register test@kw');
        }
    }

    public function testPasswordTooShort()
    {
        $url = $this->url . '/register.php';
        $content = $this->connect($url);
        if ($content == false) {
            return;
        }
        $this->fillOutRegisterForm('too@short', '1234');
        $this->clickSubmitByName('sent', array('url' => 'catchbot'));
        $this->assertText('Your password must be at least 5 characters.');
    }

    public function fillOutRegisterForm($email, $passwd)
    {
        $fname = 'test';
        $lname = 'kw';
        $institution = 'developer';
        $this->setField('fname', $fname);
        $this->setField('lname', $lname);
        $this->setField('email', $email);
        $this->setField('passwd', $passwd);
        $this->setField('passwd2', $passwd);
        $this->setField('institution', $institution);
    }

    public function testRegistrationWithEmailVerification()
    {
        $configLine = '$CDASH_REGISTRATION_EMAIL_VERIFY = true;';
        $this->addLineToConfig($configLine);

        $url = $this->url . '/register.php';
        $content = $this->connect($url);
        if ($content == false) {
            return $this->fail('Failed to load registration page.');
        }
        $email = 'verifytest@kw';
        $password = 'kitware';
        $this->fillOutRegisterForm($email, $password);
        $this->clickSubmitByName('sent', array('url' => 'catchbot'));
        $this->assertText('A confirmation email has been sent.');

        // Grab registration key directly from database
        $row = pdo_single_row_query("SELECT registrationkey FROM usertemp WHERE email = '$email'");
        if (!$row) {
            return $this->fail('Failed to register user.');
        }

        $url = $this->url . '/register.php?key=' . $row['registrationkey'];
        $content = $this->connect($url);
        if ($content == false) {
            return $this->fail('Failed to load verification page.');
        }

        if (!$this->userExists('verifytest@kw')) {
            $this->fail('Failed to register verifytest@kw');
        }

        // Try to login
        $this->testLogin($email, $password);

        $this->removeLineFromConfig($configLine);
    }
}
