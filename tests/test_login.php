<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class LoginTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testHomePage()
    {
        $content = $this->connect($this->url);
        if ($content == false) {
            return;
        }
        $this->clickLink('Login');
        $this->setField('login', 'simpletest@localhost');
        $this->setField('passwd', 'simpletest');
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
        $this->fillOutRegisterForm();
        $this->setField('url', 'catchbot');
        $this->clickSubmitByName('sent');
        $this->assertText('Bots are not allowed to obtain CDash accounts!', 'Bots detected in test_login.php.42');
    }

    public function testRegister()
    {
        $url = $this->url.'/register.php';
        $content = $this->connect($url);
        if ($content == false) {
            return;
        }
        $this->fillOutRegisterForm();
        $this->clickSubmitByName('sent', array('url' => 'catchbot'));
        $this->assertText('Registration Complete. Please login with your email and password.');
    }

    public function fillOutRegisterForm()
    {
        $fname        = 'test';
        $lname        = 'kw';
        $email        = 'test@kw';
        $passwd       = 'kitware';
        $institution  = 'developer';
        $this->setField('fname', $fname);
        $this->setField('lname', $lname);
        $this->setField('email', $email);
        $this->setField('passwd', $passwd);
        $this->setField('passwd2', $passwd);
        $this->setField('institution', $institution);
    }
}
