<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';




use App\Models\User;
use CDash\Model\Project;

class ProjectModelTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testProjectModel()
    {
        $project = new Project();

        $this->assertTrue($project->GetNumberOfErrorConfigures(0, 0) === false, 'GetNumberOfErrorConfigures!=false');
        $this->assertTrue($project->GetNumberOfWarningConfigures(0, 0) === false, 'GetNumberOfWarningConfigures!=false');
        $this->assertTrue($project->GetNumberOfPassingConfigures(0, 0) === false, 'GetNumberOfPassingConfigures!=false');
        $this->assertTrue($project->GetNumberOfPassingTests(0, 0) === false, 'GetNumberOfPassingTests!=false');
        $this->assertTrue($project->GetNumberOfFailingTests(0, 0) === false, 'GetNumberOfFailingTests!=false');
        $this->assertTrue($project->GetNumberOfNotRunTests(0, 0) === false, 'GetNumberOfNotRunTests!=false');
        $this->assertTrue($project->SendEmailToAdmin('', '') === false, 'SendEmailToAdmin!=false');

        if (!($project->Delete() === false)) {
            $this->fail("Project::Delete didn't return false for no id");
            return 1;
        }

        $project->Id = '27123';
        if (!($project->Exists() === false)) {
            $this->fail("Project::Exists didn't return false for bogus id");
            return 1;
        }

        //Cover empty contents case
        $project->AddLogo('', '');
        $project->Id = '2';
        $contents1 = file_get_contents('data/smile.gif', true);
        $contents2 = file_get_contents('data/smile2.gif', true);

        //Cover all execution paths
        $project->AddLogo($contents1, 'gif');
        $project->AddLogo($contents2, 'gif');
        $project->AddLogo($contents1, 'gif');

        return 0;
    }

    public function testConvertToJsonHasNoPrivateMembers()
    {
        $project = new Project();
        $project->Id = 0;
        $User = new user();
        $User->id = 1;
        $output = $project->ConvertToJSON($User);

        $this->assertFalse(in_array('PDO', array_keys($output)));
    }
}
