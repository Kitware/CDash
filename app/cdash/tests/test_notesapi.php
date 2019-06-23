<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Project;

class NotesAPICase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testNotesAPI()
    {
        echo "1. testNotesAPI\n";

        // Find the smallest buildid that has more than one note.
        // This was 13 at the time this test was written, but things
        // like this have a habit of changing.
        $buildid_result = pdo_single_row_query(
            'SELECT buildid, COUNT(1) FROM build2note
       GROUP BY buildid HAVING COUNT(1) > 1 ORDER BY buildid LIMIT 1');
        if (empty($buildid_result)) {
            $this->fail('No build found with multiple notes');
            return 1;
        }
        $buildid = $buildid_result['buildid'];

        // Use the API to get the notes for this build.
        $this->get($this->url . "/api/v1/viewNotes.php?buildid=$buildid");
        $response = json_decode($this->getBrowser()->getContentAsText(), true);

        // Verify some details about this builds notes.
        $numNotes = count($response['notes']);
        if ($numNotes != 2) {
            $this->fail("Expected two notes, found $numNotes");
            return 1;
        }

        $driverFound = false;
        $cronFound = false;
        foreach ($response['notes'] as $note) {
            if (strpos($note['name'], 'TrilinosDriverDashboard.cmake') !== false) {
                $driverFound = true;
            }
            if (strpos($note['name'], 'cron_driver.bat') !== false) {
                $cronFound = true;
            }
        }
        if ($driverFound === false) {
            $this->fail('Expected to find a note named TrilinosDriverDashboard.cmake');
            return 1;
        }
        if ($cronFound === false) {
            $this->fail('Expected to find a note named cron_driver.bat');
            return 1;
        }

        $this->pass('Passed');
        return 0;
    }

    public function testAddNoteRequiresAuth()
    {
        // Change the Trilinos project to a private project
        $id = pdo_get_field_value("SELECT id FROM project WHERE name='TrilinosDriver'", 'id', null);
        $project = new Project();
        $project->Id = $id;
        $project->Fill();
        $project->Public = 0;
        $project->Save();

        $endpoint = "{$this->url}/api/v1/addUserNote.php?buildid={$id}";
        $response = $this->get($endpoint);
        $actual = json_decode($response);
        $expected = 'Permission denied';

        $this->assertTrue(isset($actual->error));
        $this->assertEqual($actual->error, $expected);

        $this->login();

        $buildUserNote = [
            'AddNote' => 'testAddNoteRequiresAuth',
            'Status' => 1
        ];

        $response = $this->post($endpoint, $buildUserNote);
        $actual = json_decode($response);
        $expected = 'testAddNoteRequiresAuth';

        $this->assertTrue(isset($actual->note->text));
        $this->assertEqual($actual->note->text, $expected);

        // Change the Trilinos project back to public
        $project->Public = 1;
        $project->Save();

        $query = "DELETE FROM buildnote WHERE note='{$expected}'";
        if (!pdo_delete_query($query)) {
            $this->fail("Test successful but delete of data failed [sql: {$query}");
        }
    }

    public function testAddNoteRequiresBuildId()
    {
        $this->login();
        $endpoint = "{$this->url}/api/v1/addUserNote.php?";

        $response = $this->get($endpoint);
        $actual = json_decode($response);
        $expected = 'Valid buildid required';
        $this->assertTrue(isset($actual->error));
        $this->assertEqual($actual->error, $expected);
    }
}
