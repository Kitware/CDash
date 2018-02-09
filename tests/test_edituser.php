<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class EditUserTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testEditUserTest()
    {
        //make sure we can't visit the editUser page while logged out
        $this->logout();
        $content = $this->get($this->url . '/editUser.php');
        if (strpos($content, '<title>Login</title>') === false) {
            $this->fail("'<title>Login</title>' not found when expected.");
            return 1;
        }

        //make sure we can visit the page while logged in
        $this->login();
        $content = $this->get($this->url . '/editUser.php');
        if (strpos($content, 'My Profile') === false) {
            $this->fail("'My Profile' not found when expected");
            return 1;
        }

        //change user details
        if (!$this->SetFieldByName('fname', 'Simple')) {
            $this->fail('SetFieldByName on first name returned false');
            return 1;
        }
        if (!$this->SetFieldByName('lname', 'Test')) {
            $this->fail('SetFieldByName on last name returned false');
            return 1;
        }
        if (!$this->SetFieldByName('email', 'simpletest2@localhost')) {
            $this->fail('SetFieldByName on email returned false');
            return 1;
        }
        if (!$this->SetFieldByName('institution', 'testers')) {
            $this->fail('SetFieldByName on institution returned false');
            return 1;
        }
        $content = $this->clickSubmitByName('updateprofile');
        if (strpos($content, 'profile has been updated') === false) {
            $this->fail("'profile has been updated' not found in output.");
            return 1;
        }

        //log in with new email address
        $this->logout();
        $this->login('simpletest2@localhost', 'simpletest');
        $content = $this->get($this->url . '/editUser.php');

        // test incorrect old password
        if (!$this->SetFieldByName('oldpasswd', 'incorrect')) {
            $this->fail('SetFieldByName on password returned false');
            return 1;
        }
        if (!$this->SetFieldByName('passwd', '12345')) {
            $this->fail('SetFieldByName on password returned false');
            return 1;
        }
        if (!$this->SetFieldByName('passwd2', '12345')) {
            $this->fail('SetFieldByName on password returned false');
            return 1;
        }
        $content = $this->clickSubmitByName('updatepassword');
        if (strpos($content, 'Your old password is incorrect') === false) {
            $this->fail("'Your old password is incorrect' not found in output.  Here's what we got instead:\n$content");
            return 1;
        }

        // test minimum password length.
        if (!$this->SetFieldByName('oldpasswd', 'simpletest')) {
            $this->fail('SetFieldByName on password returned false');
            return 1;
        }
        if (!$this->SetFieldByName('passwd', '1234')) {
            $this->fail('SetFieldByName on password returned false');
            return 1;
        }
        if (!$this->SetFieldByName('passwd2', '1234')) {
            $this->fail('SetFieldByName on password returned false');
            return 1;
        }
        $content = $this->clickSubmitByName('updatepassword');
        if (strpos($content, 'Password must be at least 5 characters') === false) {
            $this->fail("'Password must be at least 5 characters' not found in output.  Here's what we got instead:\n$content");
            return 1;
        }

        //change password
        if (!$this->SetFieldByName('oldpasswd', 'simpletest')) {
            $this->fail('SetFieldByName on password returned false');
            return 1;
        }
        if (!$this->SetFieldByName('passwd', '12345')) {
            $this->fail('SetFieldByName on password returned false');
            return 1;
        }
        if (!$this->SetFieldByName('passwd2', '12345')) {
            $this->fail('SetFieldByName on password returned false');
            return 1;
        }
        $content = $this->clickSubmitByName('updatepassword');
        if (strpos($content, 'password has been updated') === false) {
            $this->fail("'password has been updated' not found in output.  Here's what we got instead:\n$content");
            return 1;
        }

        //log back in with the new password
        $this->logout();
        $this->login('simpletest2@localhost', '12345');

        //change details back so following tests aren't messed up
        $content = $this->get($this->url . '/editUser.php');
        if (!$this->SetFieldByName('fname', 'administrator')) {
            $this->fail('SetFieldByName on first name returned false');
            return 1;
        }
        if (!$this->SetFieldByName('lname', '')) {
            $this->fail('SetFieldByName on last name returned false');
            return 1;
        }
        if (!$this->SetFieldByName('email', 'simpletest@localhost')) {
            $this->fail('SetFieldByName on email returned false');
            return 1;
        }
        if (!$this->SetFieldByName('institution', 'Kitware Inc.')) {
            $this->fail('SetFieldByName on institution returned false');
            return 1;
        }
        $content = $this->clickSubmitByName('updateprofile');
        if (strpos($content, 'profile has been updated') === false) {
            $this->fail("'profile has been updated' not found in output.");
            return 1;
        }

        //log back in with old email address to fix password
        $this->logout();
        $this->login('simpletest@localhost', '12345');
        $content = $this->get($this->url . '/editUser.php');
        if (!$this->SetFieldByName('oldpasswd', '12345')) {
            $this->fail('SetFieldByName on password returned false');
            return 1;
        }
        if (!$this->SetFieldByName('passwd', 'simpletest')) {
            $this->fail('SetFieldByName on password returned false');
            return 1;
        }
        if (!$this->SetFieldByName('passwd2', 'simpletest')) {
            $this->fail('SetFieldByName on password returned false');
            return 1;
        }
        $content = $this->clickSubmitByName('updatepassword');
        if (strpos($content, 'password has been updated') === false) {
            $this->fail("'password has been updated' not found in output.");
            return 1;
        }

        $this->pass('Passed');
        return 0;
    }
}
