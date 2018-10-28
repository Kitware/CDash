<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\User;

class PasswordRotationTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->ConfigFile = dirname(__FILE__) . '/../config/config.local.php';
        $this->RotationConfig = '$CDASH_PASSWORD_EXPIRATION = 1;';
        $this->UniqueConfig = '$CDASH_UNIQUE_PASSWORD_COUNT = 2;';
        $this->UserId = null;
    }

    /* TODO: REWRITE TEST

    public function testRegisterUser()
    {
        // Enable password rotation.
        $this->addLineToConfig($this->RotationConfig);

        // Create a new user for this test.
        $url = $this->url . '/register.php';
        $content = $this->connect($url);
        if ($content == false) {
            return;
        }
        $this->setField('fname', 'Jane');
        $this->setField('lname', 'Smith');
        $this->setField('email', 'jane@smith');
        $this->setField('passwd', '12345');
        $this->setField('passwd2', '12345');
        $this->setField('institution', 'me');
        $this->clickSubmitByName('sent', array('url' => 'catchbot'));
        // Make sure the user was created successfully.
        if (!$this->userExists('jane@smith')) {
            $this->fail('Failed to register jane@smith');
        }

        // Get the id for this user.
        $user = new User();
        $user->Email = 'jane@smith';
        if (!$user->Exists()) {
            $this->fail("User does not exist after registration");
            return false;
        }
        $this->UserId = $user->Id;

        // Make sure the password was recorded for rotation.
        $row = pdo_single_row_query("SELECT * FROM password WHERE userid=$this->UserId");
        if (!$row || !array_key_exists('password', $row)) {
            $this->fail("No entry in password table after rotation");
        }
    }

    public function testExpiredPassword()
    {
        // Make the password too old.
        pdo_query("UPDATE password SET date='2011-07-22 15:37:57' WHERE userid=$this->UserId");

        // Make sure we get redirected when visiting a non-Angular page.
        $this->login('jane@smith', '12345');
        $content = $this->get($this->url . '/upgrade.php');
        if (strpos($content, 'Your password has expired') === false) {
            $this->fail("'Your password has expired' not found when expected");
            return 1;
        }

        // Make sure API endpoints tell the controller to redirect.
        $content = $this->connect($this->url . '/api/v1/index.php?project=InsightExample');
        $jsonobj = json_decode($content, true);
        if (!array_key_exists('redirect', $jsonobj)) {
            $this->fail("No 'redirect' key found when expected");
            return 1;
        }
        $expected = 'editUser.php?reason=expired';
        $found = $jsonobj['redirect'];
        if (strpos($found, $expected) === false) {
            $this->fail("Expected $expected, found $found");
            return 1;
        }
    }

    public function passwordChangeAttempt($current, $new)
    {
        $this->login('jane@smith', $current);
        $content = $this->get($this->url . '/editUser.php');
        if (!$this->SetFieldByName('oldpasswd', $current)) {
            $this->fail('SetFieldByName on password returned false');
            return false;
        }
        if (!$this->SetFieldByName('passwd', $new)) {
            $this->fail('SetFieldByName on password returned false');
            return false;
        }
        if (!$this->SetFieldByName('passwd2', $new)) {
            $this->fail('SetFieldByName on password returned false');
            return false;
        }
        $content = $this->clickSubmitByName('updatepassword');
        return $content;
    }

    public function checkOutput($content, $expected)
    {
        if (strpos($content, $expected) === false) {
            $this->fail("'$expected' not found in output.  Here's what we got instead:\n$content");
            return false;
        }
    }

    public function testUniquePasswordCount()
    {
        // Fail to change due to re-using the same password
        $content = $this->passwordChangeAttempt('12345', '12345');
        $this->checkOutput($content, 'You have recently used this password');

        // Get the current password hash to compare against later.
        $row = pdo_single_row_query("SELECT * FROM password WHERE userid=$this->UserId");
        if (!$row || !array_key_exists('password', $row)) {
            $this->fail("No entry in password table after rotation");
        }
        $md5pass = $row['password'];

        // Enable unique password count.
        $this->addLineToConfig($this->UniqueConfig);

        // Successfully change password twice.
        $content = $this->passwordChangeAttempt('12345', 'qwert');
        $this->checkOutput($content, 'Your password has been updated');
        $content = $this->passwordChangeAttempt('qwert', 'asdfg');
        $this->checkOutput($content, 'Your password has been updated');

        // Make sure the oldest password was deleted since we're only keeping
        // the two most recent entries.
        $result = pdo_query("SELECT * FROM password WHERE userid=$this->UserId");
        $num_rows = pdo_num_rows($result);
        if ($num_rows != 2) {
            $this->fail("Expected 2 rows, got $num_rows");
        }

        while ($row = pdo_fetch_array($result)) {
            if ($row['password'] == $md5pass) {
                $this->fail("Found old password that should have been deleted");
            }
        }

        // Verify that we can set our password back to the original one
        // since it now exceed our unique count of 2.
        $content = $this->passwordChangeAttempt('asdfg', '12345');
        $this->checkOutput($content, 'Your password has been updated');
    }

    public function testCleanup()
    {
        pdo_query('DELETE FROM ' . qid('user') . "WHERE id=$this->UserId");
        pdo_query("DELETE FROM password WHERE userid=$this->UserId");
        $this->removeLineFromConfig($this->RotationConfig);
        $this->removeLineFromConfig($this->UniqueConfig);
    }
    */
}
