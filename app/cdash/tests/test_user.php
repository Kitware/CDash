<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\User;

class UserTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testUser()
    {
        $user = new User();
        $user->Id = 'non_numeric';

        if (!($user->IsAdmin() === false)) {
            $this->fail("User::IsAdmin didn't return false for non-numeric user id");
            return 1;
        }

        $user->Id = '';
        $user->Email = '';

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

        $user->Password = User::PasswordHash('simpletest');

        // Coverage for update save
        $user->Save();
        return 0;
    }
}
