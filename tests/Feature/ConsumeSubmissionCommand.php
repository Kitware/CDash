<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

use CDash\Model\Project;

class ConsumeSubmissionCommand extends TestCase
{
    public function setUp() : void
    {
        parent::setUp();
        $this->queue_config = base_path('app/cdash/config/queue.local.php');
        copy(base_path('app/cdash/tests/data/queue_test_config.php'), "$this->queue_config");
    }

    public function tearDown() : void
    {
        // Remove testing config file.
        if (file_exists($this->queue_config)) {
            unlink($this->queue_config);
        }

        // Remove testing queue directory.
        require_once base_path('app/cdash/include/common.php');
        DeleteDirectory(base_path('/test_queue_dir'));
    }

    /**
     * Feature test for the submission:consume artisan command.
     *
     * @return void
     */
    public function testConsumeSubmissionCommand()
    {
        // Make a project.
        $this->project = new Project();
        $this->project->Name = 'TestProject';
        $this->project->Public = 1;
        $this->project->Save();
        $this->project->InitialSetup();

        // Modify config to enable use of queue.
        $path = base_path();
        $cdash_config = \CDash\Config::getInstance();
        $cdash_config->set('CDASH_BERNARD_SUBMISSION', true);

        // Queue a submission.
        require_once "$path/app/cdash/include/do_submit.php";
        $xml_path = "$path/app/cdash/tests/data/InsightExperimentalExample/Insight_Experimental_Build.xml";
        $fp = fopen($xml_path, 'r');
        do_submit_queue($fp, 1, null, '', '127.0.0.1');

        // Start up a consumer worker.
        Artisan::call('submission:consume --one-shot');

        // Verify that the submission was successfully parsed.
        $build = \DB::table('build')->find(1);
        $this->assertEquals($build->stamp, '20090223-0710-Experimental');
        $this->assertEquals($build->name, 'Linux-g++-4.1-LesionSizingSandbox_Debug');
        $this->assertEquals($build->type, 'Experimental');
        $this->assertEquals($build->generator, 'ctest2.7-20080827');
        $this->assertEquals($build->starttime, '2009-02-23 07:10:38');
        $this->assertEquals($build->buildwarnings, 3);
    }
}
