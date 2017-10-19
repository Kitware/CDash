<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/project.php';
require_once 'models/subproject.php';
require_once 'models/user.php';
require_once 'models/labelemail.php';

class SubProjectEmailTestCase extends KWWebTestCase
{
    private $builds;
    private $parentBuilds;

    public function __construct()
    {
        parent::__construct();
        $this->PDO = get_link_identifier()->getPdo();
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
            'EmailRedundantFailures' => 0
        ];
        $projectid = $this->createProject($settings);
        if ($projectid < 1) {
            $this->fail('Failed to create project');
        }
        $this->Project = new Project();
        $this->Project->Id = $projectid;

        // Unsubscribe default admin from this project.
        $stmt = $this->PDO->prepare(
            'DELETE FROM user2project WHERE userid = ? AND projectid = ?');
        pdo_execute($stmt, [1, $this->Project->Id]);

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
            $user = new User($email);
            $userid = $user->GetIdFromEmail($email);
            if (!$userid) {
                // These users are typically created by previous tests,
                // but we can create them here if they don't exist yet.
                $user->FirstName = $subproj_lower;
                $user->LastName = 'regressions';
                $user->Email = $email;
                $user->Password = User::PasswordHash($email);
                $user->Admin = 0;
                $user->Save();
                $userid = $user->Id;
            }
            $user_project = new UserProject();
            $user_project->UserId = $userid;
            $user_project->ProjectId = $this->Project->Id;
            $user_project->EmailType = 3; // any build
            $user_project->EmailCategory = 54; // everything except warnings
            $user_project->Save();

            // Subscribe this user to the subproject's label.
            $label_email = new LabelEmail;
            $label_email->UserId = $userid;
            $label_email->ProjectId = $this->Project->Id;
            $label = new Label;
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
            if (strpos($line, '"TESTING: EMAIL"') !== false) {
                if ($email_address_found) {
                    $this->fail("Multiple emails found in log.\n$line");
                }
                $email_address_found = true;
                if (strpos($line, 'mesquite-regression@noemail') === false) {
                    $this->fail("Email not sent to mesquite-regression\n$line");
                }
            } elseif (strpos($line, '"TESTING: EMAILTITLE"') !== false) {
                $expected = 'FAILED (b=5): SubProjectEmails/Mesquite';
                if (strpos($line, $expected) === false) {
                    $this->fail("Did not find $expected in subject\n$line\n$line");
                }
            }
        }
    }
}
