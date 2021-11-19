<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Database;
use CDash\Model\Project;

class LotsOfSubProjectsTestCase extends KWWebTestCase
{
    private $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = null;
        $this->deleteLog($this->logfilename);
    }

    public function __destruct()
    {
        // Delete project & build created by this test.
        if ($this->project) {
            remove_project_builds($this->project->Id);
            $this->project->Delete();
        }

        // Delete all the extra labels we created.
        \DB::table('label')->where('text', 'LIKE', 'LotsOfSubprojects%')->delete();

        // Delete generated XML file.
        unlink('LotsOfSubprojects_Configure.xml');
    }

    public function testLotsOfSubProjects()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'LotsOfSubProjects',
        ]);
        $this->project->Fill();

        // Generate our testing data.
        // We do this programmatically here to avoid adding another
        // big test file to our repository.
        $test_filename = 'LotsOfSubProjects_Configure.xml';
        $handle = fopen($test_filename, 'w');
        fwrite($handle, file_get_contents(dirname(__FILE__) . '/data/LotsOfSubProjects/Before.xml'));
        foreach (range(1, 100) as $i) {
            fwrite($handle, "	<Subproject name=\"LotsOfSubProjects{$i}\">" . PHP_EOL);
            fwrite($handle, "		<Label>LotsOfSubProjects{$i}</Label>"  . PHP_EOL);
            fwrite($handle, '	</Subproject>' . PHP_EOL);
        }
        fwrite($handle, file_get_contents(dirname(__FILE__) . '/data/LotsOfSubProjects/After.xml'));

        // Submit our testing data.
        if (!$this->submission('LotsOfSubProjects', $test_filename)) {
            $this->fail("Failed to submit $test_filename");
        }

        // No errors in the log.
        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        // Verify 101 builds (1 parent + 100 children).
        $results = \DB::select(
            DB::raw('SELECT id FROM build WHERE projectid = :projectid'),
            [':projectid' => $this->project->Id]
        );
        $this->assertEqual(101, count($results));

        // Verify 100 labels.
        $results = \DB::select(
            DB::raw("SELECT id FROM label WHERE text LIKE 'LotsOfSubprojects%'")
        );
        $this->assertEqual(100, count($results));
    }
}
