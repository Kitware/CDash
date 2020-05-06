<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use App\Models\User;

class RehashPasswordTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testSubscribeProjectShowsLabels()
    {
        // Update password to be hashed with md5.
        $user = User::find(1);
        $md5_pass = md5('simpletest');
        $user->password =  $md5_pass;
        $user->save();

        // Login.
        $client = $this->getGuzzleClient();

        // Verify it's now hashed with a more secure algorithm.
        $user = User::find(1);
        $this->assertTrue($user->password != $md5_pass);
        $this->assertTrue(password_verify('simpletest', $user->password));
    }
}
