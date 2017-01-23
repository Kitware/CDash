<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/user.php';

class UserTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testUser()
    {
        $this->startCodeCoverage();

        $user = new User();
        $user->Id = 'non_numeric';

        if (!($user->IsAdmin() === false)) {
            $this->fail("User::IsAdmin didn't return false for non-numeric user id");
            return 1;
        }

        $user->Id = '';
        $user->Email = '';

        if (!($user->GetName() === false)) {
            $this->fail("User::GetName didn't return false when given no user id");
            return 1;
        }

        if (!($user->IsAdmin() === false)) {
            $this->fail("User::Exists didn't return false for no user id and no email");
            return 1;
        }

        $user->Email = 'simpletest@localhost';

        if ($user->Exists() === false) {
            $this->fail('User::Exists returned false even though user exists');
            return 1;
        }

        $id = $user->GetIdFromEmail('simpletest@localhost');

        if ($id === false) {
            $this->fail('User::GetIdFromEmail returned false for a valid user');
            return 1;
        }

        $user->Id = $id;
        $user->Admin = '1';
        $user->FirstName = 'administrator';
        $user->Institution = 'Kitware Inc.';

        if ($user->Exists() != true) {
            $this->fail('User::Exists failed given a valid user id');
            return 1;
        }

        $user->Password = md5('simpletest');

        // Coverage for update save
        $user->Save();

        $this->stopCodeCoverage();
        return 0;
    }
}
