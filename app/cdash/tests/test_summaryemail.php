<?php

use CDash\Model\BuildGroup;
use CDash\Model\Project;

require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/pdo.php';

class SummaryEmailTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testSummaryEmail()
    {
        // Cleanup from previous runs.
        $project = new Project();
        $project->Id = get_project_id('SummaryEmailProject');
        if ($project->Id >= 0) {
            remove_project_builds($project->Id);
            $project->Delete();
        }
        $this->deleteLog($this->logfilename);

        // Make a separate project for this test.
        $project->Id = $this->createProject(['Name' => 'SummaryEmailProject']);

        // Configure the nightly group to send summary emails.
        $buildgroup = new BuildGroup();
        $buildgroup->SetProjectId($project->Id);
        $buildgroup->SetName('Nightly');
        $buildgroup->SetSummaryEmail(1);
        $buildgroup->Save();

        // Resubmit a previous build to this new project.
        $parts = ['build', 'update', 'test', 'dynamicanalysis'];
        foreach ($parts as $part) {
            $file = dirname(__FILE__) . "/data/EmailProjectExample/2_$part.xml";
            $this->submission('SummaryEmailProject', $file);
        }

        $expected = [
            'about to query for builds to remove',
            'removing old buildids for projectid:',
            'removing old buildids for projectid:',
            'simpletest@localhost',
            'FAILED (w=3): SummaryEmailProject - Win32-MSVC2009 - Nightly',
            'The "Nightly" group has errors, warnings, or test failures.',
            'You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list',
            'Details on the submission can be found at http://cdash.dev/index.php?project=SummaryEmailProject&date=',
            'Project: SummaryEmailProject',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23 10:02:04',
            "Type: Nightly",
            'Total Warnings: 3',
            '*Warnings*',
            '3>f:\program files\microsoft sdks\windows\v6.0a\include\servprov.h(79) : warning C4068: unknown pragma',
            '3>F:\Program Files\Microsoft SDKs\Windows\v6.0A\\\include\urlmon.h(352) : warning C4068: unknown pragma',
            '3>XcedeCatalog.cxx',
            '2>bmScriptAddDashboardLabelAction.cxx',
            '3>f:\program files\microsoft sdks\windows\v6.0a\include\servprov.h(79) : warning C4068: unknown pragma',
            '-CDash on',
        ];
        if (!$this->assertLogContains($expected, 28)) {
            $this->fail('Log did not contain expected contents');
        }
    }
}
