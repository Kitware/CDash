<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';

use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\Site;

class IndexNextPreviousTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testIndexNextPrevious()
    {
        $projectname = 'NextPreviousProject';
        // Cleanup from previous runs.
        $project = new Project();
        $project->Id = get_project_id($projectname);
        if ($project->Id >= 0) {
            remove_project_builds($project->Id);
            $project->Delete();
        }

        // Make a separate project for this test.
        $project->Id = $this->createProject(['Name' => $projectname]);

        // Make some builds.
        $first_date = '2016-10-10';
        $second_date = '2017-10-10';
        $third_date = '2018-10-10';
        $build_rows = [
            [$first_date, 1476079800, 'Experimental'],
            [$second_date, 1507637400, 'Nightly'],
            [$third_date, 1539195000, 'Experimental']
        ];
        foreach ($build_rows as $build_row) {
            $date = str_replace('-', '', $build_row[0]);
            $timestamp = $build_row[1];
            $group = $build_row[2];
            $build = new Build();
            $build->Name = 'next-previous-build';
            $build->ProjectId = $project->Id;
            $site = new Site();
            $site->Id = 1;
            $build->SiteId = $site->Id;
            $stamp = "$date-1410-$group";
            $build->SetStamp($stamp);
            $build->StartTime = gmdate(FMT_DATETIME, $timestamp);
            $this->assertTrue($build->AddBuild());
            $this->assertTrue($build->Id > 0);
        }

        // Verify next/previous links point to day with builds.
        $this->get($this->url . "/api/v1/index.php?project=$projectname&date=$first_date");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertFalse($jsonobj['menu']['previous']);
        $this->assertEqual($jsonobj['menu']['next'], "index.php?project=NextPreviousProject&date=$second_date");

        $this->get($this->url . "/api/v1/index.php?project=$projectname&date=$second_date");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual($jsonobj['menu']['previous'], "index.php?project=NextPreviousProject&date=$first_date");
        $this->assertEqual($jsonobj['menu']['next'], "index.php?project=NextPreviousProject&date=$third_date");

        $this->get($this->url . "/api/v1/index.php?project=$projectname&date=$third_date");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual($jsonobj['menu']['previous'], "index.php?project=NextPreviousProject&date=$second_date");
        $this->assertFalse($jsonobj['menu']['next']);

        // Verify viewBuildGroup only finds days with builds in that group.
        $this->get($this->url . "/api/v1/index.php?project=$projectname&buildgroup=Experimental&date=$third_date");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual($jsonobj['menu']['previous'], "viewBuildGroup.php?project=NextPreviousProject&buildgroup=Experimental&date=$first_date");
        $this->assertFalse($jsonobj['menu']['next']);
    }
}
