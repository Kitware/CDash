<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use App\Models\Build;
use App\Models\Configure;
use CDash\Model\Project;
use Illuminate\Support\Facades\DB;

class ConfigureAppendTestCase extends KWWebTestCase
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

    public function testConfigureAppend()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'ConfigureAppend',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        $test_dir = dirname(__FILE__) . '/data/ConfigureAppend/';
        $files = ['Configure_1.xml', 'Configure_2.xml'];
        foreach ($files as $file) {
            if (!$this->submission('ConfigureAppend', "{$test_dir}/{$file}")) {
                $this->fail("Failed to submit {$file}");
            }
        }

        // Verify that the two configures were combined successfully.
        $build_results = DB::select('
            SELECT
                id,
                configureerrors,
                configurewarnings,
                configureduration
            FROM build
            WHERE projectid = ?
        ', [$this->project->Id]);
        $this->assertTrue(1 === count($build_results));

        /** @var Configure $configure */
        $configure = Build::findOrFail((int) $build_results[0]->id)->configure()->firstOrFail();
        $this->assertEqual($configure->status, 3);

        $log = $configure->log;
        $this->assertTrue(strpos($log, 'This is the first part of my configure') !== false);
        $this->assertTrue(strpos($log, 'This is the second part of my configure') !== false);

        $this->assertEqual($build_results[0]->configureerrors, 3);
        $this->assertEqual($build_results[0]->configurewarnings, 5);
        $this->assertEqual($build_results[0]->configureduration, 60);
    }
}
