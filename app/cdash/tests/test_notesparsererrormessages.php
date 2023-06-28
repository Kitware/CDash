<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;

class NotesParserErrorMessagesTestCase extends KWWebTestCase
{
    private $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = null;
    }

    public function __destruct()
    {
        // Delete project & build created by this test.
        if ($this->project) {
            remove_project_builds($this->project->Id);
            $this->project->Delete();
        }
    }

    public function testNotesParserErrorMessages()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'NotesParserErrorMessages',
        ]);
        $this->project->Fill();
        $this->deleteLog($this->logfilename);

        $test_dir = dirname(__FILE__) . '/data/NotesParserErrorMessages/';

        $this->submission('NotesParserErrorMessages', "{$test_dir}/NoName.xml");
        $expected = [
            'about to query for builds to remove',
            'removing old buildids for projectid:',
            'removing old buildids for projectid:',
            'Note missing name for build'
        ];
        $this->assertLogContains($expected, 5);
        $this->deleteLog($this->logfilename);

        $this->submission('NotesParserErrorMessages', "{$test_dir}/NoText.xml");
        $this->assertLogContains(["No note text for 'my very own note' on build"], 2);
        $this->deleteLog($this->logfilename);

        $this->submission('NotesParserErrorMessages', "{$test_dir}/NoTime.xml");
        $expected = [
            "Cannot create build 'note_errors' for note 'my very own note' because time was not set",
            "No note time for 'my very own note' on build",
        ];
        $this->assertLogContains($expected, 3);
        $this->deleteLog($this->logfilename);
    }
}
