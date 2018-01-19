<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/project.php';

class CoverageDirectoriesTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testCoverageDirectories()
    {
        $project = new Project();
        $project->Id = get_project_id('CoverageDirectories');
        if ($project->Id >= 0) {
            remove_project_builds($project->Id);
            $project->Delete();
        }

        $settings = array(
                'Name' => 'CoverageDirectories',
                'Description' => 'Test to make sure directories display proper files');
        $projectid = $this->createProject($settings);
        if ($projectid < 1) {
            $this->fail('Failed to create project');
            return;
        }

        $filesToSubmit = array('Coverage.xml', 'CoverageLog-0.xml');
        $dir = dirname(__FILE__) . '/data/CoverageDirectories';
        foreach ($filesToSubmit as $file) {
            if (!$this->submission('CoverageDirectories', "$dir/$file")) {
                $this->fail("Failed to submit $file");
                return;
            }
        }

        // Find buildid for coverage.
        $content = $this->connect($this->url . '/api/v1/index.php?project=CoverageDirectories&date=20180119');
        $jsonobj = json_decode($content, true);
        if (count($jsonobj['coverages']) < 1) {
            $this->fail('No coverage build found when expected');
            return;
        }
        $buildid = $jsonobj['coverages'][0]['buildid'];

        $content = $this->connect($this->url . '/ajax/getviewcoverage.php?sEcho=1&iColumns=5&sColumns=&iDisplayStart=0&iDisplayLength=25&mDataProp_0=0&mDataProp_1=1&mDataProp_2=2&mDataProp_3=3&mDataProp_4=4&iSortCol_0=2&sSortDir_0=asc&iSortingCols=1&bSortable_0=true&bSortable_1=true&bSortable_2=true&bSortable_3=true&bSortable_4=true&buildid=' . $buildid . '&status=6&dir=utils&ndirectories=0&nno=0&nzero=0&nlow=0&nmedium=0&nsatisfactory=0&ncomplete=3&nall=3&metricerror=0.49&metricpass=0.7&userid=0&displaylabels=0&showfilters=1&limit=0&filtercombine=&filtercount=1&field1=filename/string&compare1=63&value1=&_=1516378120118');
        $jsonobj = json_decode($content, true);
        if (count($jsonobj['aaData']) != 1) {
            $this->fail('File count in utils/ directory is not 1');
            return;
        }

        if (strpos($jsonobj['aaData'][0][0], 'hello.cpp') === false) {
            $this->fail('utils/ directory does not contain hello.cpp');
            return;
        }

        $this->assertTrue(true, 'utils/ directory contained correct files');
    }
}
