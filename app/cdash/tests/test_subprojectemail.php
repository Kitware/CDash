<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

use App\Models\User;
use CDash\Database;
use CDash\Model\Label;
use CDash\Model\LabelEmail;
use CDash\Model\Project;
use CDash\Model\SubProject;
use CDash\Model\UserProject;
use Illuminate\Support\Facades\DB;

class SubProjectEmailTestCase extends KWWebTestCase
{
    protected $PDO;
    protected $Project;

    public function __construct()
    {
        parent::__construct();
        $this->PDO = Database::getInstance();
    }

    public function testSubProjectEmail()
    {
        // Clean out previously existing testing data (if necessary).
        $stmt = $this->PDO->prepare('SELECT id FROM project WHERE name = ?');
        pdo_execute($stmt, ['SubProjectEmails']);
        $existing_projectid = $stmt->fetchColumn();
        if ($existing_projectid !== false) {
            $project = new Project();
            $project->Id = $existing_projectid;
            remove_project_builds($project->Id);
            $project->Delete();
        }

        // Create project.
        $settings = [
            'Name' => 'SubProjectEmails',
            'Public' => 1,
            'EmailBrokenSubmission' => 1,
            'EmailRedundantFailures' => 0,
        ];
        $projectid = $this->createProject($settings);
        if ($projectid < 1) {
            $this->fail('Failed to create project');
        }
        $this->Project = new Project();
        $this->Project->Id = $projectid;

        // Unsubscribe default admin from this project.
        DB::delete('DELETE FROM user2project WHERE userid = 1 AND projectid = ?', [$this->Project->Id]);

        // Configure this project to send email for Experimental builds.
        $stmt = $this->PDO->prepare(
            'UPDATE buildgroup SET summaryemail = 0
            WHERE projectid = ? AND name =  ?');
        pdo_execute($stmt, [$this->Project->Id, 'Experimental']);

        // Subscribe some users to receive emails about these subprojects.
        $subprojects = ['Mesquite', 'Shards'];
        foreach ($subprojects as $subproject_name) {
            $subproject = new SubProject();
            $subproject->SetName($subproject_name);
            $subproject->SetProjectId($this->Project->Id);
            $subproject->Save();

            $subproj_lower = strtolower($subproject_name);
            $email = "$subproj_lower-regression@noemail";
            $user = User::where('email', '=', $email)->first();

            if (!$user) {
                // These users are typically created by previous tests,
                // but we can create them here if they don't exist yet.
                $user->firstname = $subproj_lower;
                $user->lastname = 'regressions';
                $user->email = $email;
                $user->password = password_hash($email, PASSWORD_DEFAULT);
                $user->admin = 0;
                $user->save();
                $userid = $user->id;
            }
            $userid = $user->id;
            $user_project = new UserProject();
            $user_project->UserId = $userid;
            $user_project->ProjectId = $this->Project->Id;
            $user_project->EmailType = 3; // any build
            $user_project->EmailCategory = 54; // everything except warnings
            $user_project->Save();

            // Subscribe this user to the subproject's label.
            $label_email = new LabelEmail();
            $label_email->UserId = $userid;
            $label_email->ProjectId = $this->Project->Id;
            $label = new Label();
            $label->SetText($subproject_name);
            $labelid = $label->GetIdFromText();
            $label_email->LabelId = $labelid;
            $label_email->Insert();
        }

        // Submit testing data.
        $filenames = [
            'Trilinos_hut11.kitware_Windows_NT-MSVC10-SERIAL_DEBUG_DEV_20110722-1515-Experimental_131134835059_Build.xml',
            'Trilinos_hut11.kitware_Windows_NT-MSVC10-SERIAL_DEBUG_DEV_20110722-1515-Experimental_131134907377_Build.xml',
            'Trilinos_hut11.kitware_Windows_NT-MSVC10-SERIAL_DEBUG_DEV_20110722-1515-Experimental_131134909069_Update.xml'];
        foreach ($filenames as $filename) {
            $file = dirname(__FILE__) . "/data/ActualTrilinosSubmission/$filename";
            if (!$this->submission('SubProjectEmails', $file)) {
                $this->fail("Failed to submit $file");
            }
        }

        // Verify correct email behavior by examining the log.
        $log_contents = file($this->logfilename);
        $email_address_found = false;
        foreach ($log_contents as $line) {
            if (str_contains($line, 'Sent email titled')) {
                if ($email_address_found) {
                    $this->fail("Multiple emails found in log.\n$line");
                }
                $email_address_found = true;
                if (!str_contains($line, 'mesquite-regression@noemail')) {
                    $this->fail("Email not sent to mesquite-regression\n$line");
                }

                $expected = 'FAILED (b=5): SubProjectEmails/Mesquite';
                if (!str_contains($line, $expected)) {
                    $this->fail("Did not find $expected in subject\n$line\n$line");
                }
            }
        }
    }
}
