<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

require_once dirname(__FILE__) . '/cdash_test_case.php';

class ManageProjectRolesTestCase extends KWWebTestCase
{
    protected $projectid;

    public function __construct()
    {
        parent::__construct();
    }

    public function testRegisterUser()
    {
        if (!$this->connectAndGetProjectId()) {
            return 1;
        }

        $email = 'simpleuser@localhost';

        $this->get($this->url . "/manageProjectRoles.php?projectid=$this->projectid#fragment-3");
        if (!$this->setFieldByName('registeruseremail', $email)) {
            $this->fail('Set user email returned false');
        }
        if (!$this->setFieldByName('registeruserfirstname', 'Simple')) {
            $this->fail('Set user first name returned false');
        }
        if (!$this->setFieldByName('registeruserlastname', 'User')) {
            $this->fail('Set user last name returned false');
        }
        if (!$this->setFieldByName('registeruserrepositorycredential', 'simpleuser')) {
            $this->fail('Set user repository credential returned false');
        }
        $this->clickSubmitByName('registerUser');
        if (!str_contains($this->getBrowser()->getContentAsText(), $email)) {
            $this->fail("'{$email}' not found when expected");
        }

        // Remove the user we just added to this project.
        $user = User::firstWhere('email', $email);
        $payload = ['removeuser' => 'Remove', 'userid' => $user->id];
        $this->post("{$this->url}/manageProjectRoles.php?projectid={$this->projectid}", $payload);

        // Verify that they are no longer associated with this project.
        if (DB::table('user2project')->where('userid', $user->id)->where('projectid', $this->projectid)->exists()) {
            $this->fail('user2project row still exists after deletion');
        }
        if (DB::table('user2repository')->where('userid', $user->id)->where('projectid', $this->projectid)->exists()) {
            $this->fail('user2repository row still exists after deletion');
        }
    }

    public function connectAndGetProjectId()
    {
        $this->login();

        //get projectid for PublicDashboards
        $content = $this->connect($this->url . '/manageProjectRoles.php');
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (strpos($line, 'PublicDashboard') !== false) {
                preg_match('#<option value="([0-9]+)"#', $line, $matches);
                $this->projectid = $matches[1];
                break;
            }
        }
        if ($this->projectid === -1) {
            $this->fail('Unable to find projectid for PublicDashboard');
            return false;
        }
        return true;
    }
}
