<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use Illuminate\Support\Facades\DB;

require_once dirname(__FILE__) . '/cdash_test_case.php';

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
        $buildid_result = DB::select(
            'SELECT buildid, COUNT(1) FROM build2note
       GROUP BY buildid HAVING COUNT(1) > 1 ORDER BY buildid LIMIT 1')[0] ?? [];
        if ($buildid_result === []) {
            $this->fail('No build found with multiple notes');
            return 1;
        }
        $buildid = $buildid_result->buildid;

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
            if (str_contains($note['name'], 'TrilinosDriverDashboard.cmake')) {
                $driverFound = true;
            }
            if (str_contains($note['name'], 'cron_driver.bat')) {
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
}
