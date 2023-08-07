<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/pdo.php';

use CDash\Model\Project;

class UserNotesAPICase extends KWWebTestCase
{
    public function testAddNoteRequiresAuth(): void
    {
        // Change the Trilinos project to a private project
        $id = pdo_single_row_query("SELECT id FROM project WHERE name='TrilinosDriver'")['id'];
        $project = new Project();
        $project->Id = $id;
        $project->Fill();
        $project->Public = 0;
        $project->Save();

        $endpoint = "{$this->url}/api/v1/addUserNote.php";
        $response = $this->post($endpoint, ['buildid' => $id]);
        $actual = json_decode($response);

        $this->assertTrue(isset($actual->requirelogin));
        $this->assertEqual($actual->requirelogin, 1);

        $this->login();

        $buildUserNote = [
            'buildid' => $id,
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
        $result = pdo_query($query);
        if (false === $result) {
            $this->fail("Test successful but delete of data failed [sql: {$query}");
        }
    }

    public function testAddNoteRequiresBuildId(): void
    {
        $this->login();
        $endpoint = "{$this->url}/api/v1/addUserNote.php";

        $response = $this->post($endpoint);
        $actual = json_decode($response);
        $this->assertTrue(isset($actual->error));
        $this->assertEqual($actual->error, 'Invalid buildid!');
    }
}
