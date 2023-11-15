<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';




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

        $user->Id = '';
        $user->Email = 'simpletest@localhost';

        if ($user->Exists() === false) {
            $this->fail('User::Exists returned false even though user exists');
            return 1;
        }

        $user->Id = App\Models\User::firstWhere('email', 'simpletest@localhost')?->id;
        $user->Admin = '1';
        $user->FirstName = 'administrator';
        $user->Institution = 'Kitware Inc.';

        if ($user->Exists() != true) {
            $this->fail('User::Exists failed given a valid user id');
            return 1;
        }

        $user->Password = password_hash('simpletest', PASSWORD_DEFAULT);

        // Coverage for update save
        $user->Save();
        return 0;
    }
}
