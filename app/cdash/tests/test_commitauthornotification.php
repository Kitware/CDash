<?php

use CDash\Model\BuildGroup;

class CommitAuthorNotificationTestCase extends KWWebTestCase
{
    private $dataDir;
    private $projectId;
    private $projectName = 'CommitAuthorNotification';

    public function __construct()
    {
        parent::__construct();
        $this->dataDir = dirname(__FILE__) . "/data/{$this->projectName}";
        $this->projectId = $this->createProject([
            'Name' => $this->projectName,
            'Description' => "Project {$this->projectName} test for cdash testing",
            'EmailBrokenSubmission' => 1,
            'EmailRedundantFailures' => 0
        ]);
        $group = new BuildGroup();
        $group->SetName('Continuous');
        $group->SetProjectId($this->projectId);
        $group->SetEmailCommitters(true);
        $group->Save();
    }

    public function __destruct()
    {
        $this->deleteProject($this->projectId);
    }

    private function submitFile($file)
    {
        $submission_file = "{$this->dataDir}/$file";
        if (!$this->submission($this->projectName, $submission_file)) {
            $this->fail("Submission of {$submission_file} failed");
            return;
        }
    }

    public function testCommitAuthorsDoNotRecieveBuildWarningsNotifications()
    {
        $this->deleteLog($this->logfilename);

        $this->submitFile('1_update.xml');
        $this->submitFile('1_build.xml');

        $log = file_get_contents($this->logfilename);

        $this->assertFalse(str_contains($log, 'bot@domain.tld'));
        $this->assertFalse(str_contains($log, 'jane.doe@domain.tld'));
        $this->assertFalse(str_contains($log, 'john.doe@domain.tld'));
    }

    public function testCommitAuthorsReceiveBuildFailureNotifications()
    {
        $this->deleteLog($this->logfilename);

        $this->submitFile('2_update.xml');
        $this->submitFile('2_build.xml');

        $log = file_get_contents($this->logfilename);

        $this->assertTrue(str_contains($log, 'bot@domain.tld'));
        $this->assertTrue(str_contains($log, 'jane.doe@domain.tld'));
        $this->assertTrue(str_contains($log, 'john.doe@domain.tld'));
    }

    public function testCommitAuthorsReceiveTestFailureNotifications()
    {
        $this->deleteLog($this->logfilename);

        $this->submitFile('2_test.xml');

        $log = file_get_contents($this->logfilename);

        $this->assertTrue(str_contains($log, 'bot@domain.tld'));
        $this->assertTrue(str_contains($log, 'jane.doe@domain.tld'));
        $this->assertTrue(str_contains($log, 'john.doe@domain.tld'));
    }
}

// PHP_IDE_CONFIG=serverName=docker
