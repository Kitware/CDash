<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/pdo.php';

class EmailTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testCreateProjectTest()
    {
        $settings = array(
                'Name' => 'EmailProjectExample',
                'Description' => 'Project EmailProjectExample test for cdash testing',
                'EmailBrokenSubmission' => 1,
                'EmailRedundantFailures' => 0);
        $this->createProject($settings);
    }

    public function testRegisterUser()
    {
        $url = $this->url . '/register.php';
        $content = $this->connect($url);
        if ($content == false) {
            return;
        }

        $this->setField('fname', 'Firstname');
        $this->setField('lname', 'Lastname');
        $this->setField('email', 'user1@kw');
        $this->setField('passwd', 'user1');
        $this->setField('passwd2', 'user1');
        $this->setField('institution', 'Kitware Inc');
        $this->clickSubmitByName('sent', array('url' => 'catchbot'));

        // Make sure the user was created successfully.
        if (!$this->userExists('user1@kw')) {
            $this->fail("Failed to register new user");
        }

        // Login as the user.
        $this->login('user1@kw', 'user1');

        // Subscribe to the project.
        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->query("SELECT id FROM project WHERE name = 'EmailProjectExample'");
        $row = $stmt->fetch();
        if (!$row) {
            $this->fail('Could not lookup projectid');
        }
        $projectid = $row['id'];
        $this->connect($this->url . "/subscribeProject.php?projectid=$projectid");
        $this->setField('credentials[0]', 'user1kw');
        $this->setField('emailsuccess', '1');
        $this->clickSubmitByName('subscribe');
        if (!$this->checkLog($this->logfilename)) {
            return;
        }
    }

    public function testSubmissionFirstBuild()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $file = "$rep/1_build.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }
        $file = "$rep/1_configure.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }
        $file = "$rep/1_test.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }
        if (!$this->checkLog($this->logfilename)) {
            return;
        }
        $this->pass("Submission of $file has succeeded");
    }

    public function testSubmissionEmailBuild()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $file = "$rep/2_build.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }

        $file = "$rep/2_update.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }

        if (!$this->compareLog($this->logfilename, $rep . '/cdash_1.log')) {
            return;
        }
        $this->pass('Passed');
    }

    public function testSubmissionEmailTest()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $file = "$rep/2_test.xml";

        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }
        if (!$this->compareLog($this->logfilename, "$rep/cdash_2.log")) {
            return;
        }

        $this->pass('Passed');
    }

    public function testSubmissionEmailDynamicAnalysis()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $file = "$rep/2_dynamicanalysis.xml";

        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }
        if (!$this->compareLog($this->logfilename, "$rep/cdash_3.log")) {
            return;
        }
        $this->pass('Passed');
    }

    public function testEmailSentToGitCommitter()
    {
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $file = "$rep/3_update.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            //return;
        }

        $this->deleteLog($this->logfilename);
        $file = "$rep/3_test.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            //return;
        }

        if (!$this->compareLog($this->logfilename, "$rep/cdash_committeremail.log")) {
            $this->fail('Log did not match cdash_committeremail.log');
            return;
        }
        $this->pass('Passed');
    }
}
