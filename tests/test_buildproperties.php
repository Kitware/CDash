<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Build;
use CDash\Model\BuildError;
use CDash\Model\BuildFailure;
use CDash\Model\BuildTest;
use CDash\Model\Project;
use CDash\Model\Test;

class BuildPropertiesTestCase extends KWWebTestCase
{
    /** @var Project Project */
    private $Project;
    public function __construct()
    {
        parent::__construct();
        $this->PDO = get_link_identifier()->getPdo();
        $this->Project = null;
    }

    public function __destruct()
    {
        if ($this->Project) {
            remove_project_builds($this->Project->Id);
            $this->Project->Delete();
        }
    }

    public function testUploadBuildProperties()
    {
        // Create testing project.
        $this->login();
        $settings = [
            'Name' => 'BuildPropertiesProject',
        ];
        $projectid = $this->createProject($settings);
        if ($projectid < 1) {
            $this->fail('Failed to create project');
        }
        $this->Project = new Project();
        $this->Project->Id = $projectid;

        // Create a series of builds.
        $this->Builds = [];
        $this->create_build('clean', 'clean.json', '20170526-0500', 'ad4ff396f78f60b61ef8b18be034dfee');
        $this->create_build('warning', 'warning.json', '20170526-0500', '75ffc73d29054aff9c589eac7a292d0c');
        $this->create_build('error1', 'compile_error.json', '20170527-0500', '617e896e3153d04a3715eebc2f6cb94c');
        $this->create_build('error2', 'compile_error.json', '20170527-0500', '617e896e3153d04a3715eebc2f6cb94c');
        $this->create_build('failedtest1', 'test_failure.json', '20170527-0500', '617442e8e6c8501328bba924f6d3e4a4');
        $this->create_build('failedtest2', 'test_failure.json', '20170528-0500', '617442e8e6c8501328bba924f6d3e4a4');
        $this->create_build('failedtest3', 'test_failure.json', '20170528-0500', '617442e8e6c8501328bba924f6d3e4a4');
        $this->create_build('failedtest4', 'test_failure.json', '20170529-0500', '617442e8e6c8501328bba924f6d3e4a4');
        $this->create_build('fixed', 'fixed.json', '20170529-0500', '0d7a8415c91ea126550bdff6f5b18b2f');

        // Add a test for these builds.
        $test = new Test();
        $test->ProjectId = $this->Project->Id;
        $test->Details = '';
        $test->Name = 'BuildPropUnitTest';
        $test->Path = '/tmp';
        $test->Command = 'echo foo';
        $test->Output = 'foo';
        $test->Insert();
        foreach ($this->Builds as $name => $build) {
            $buildtest = new BuildTest();
            $buildtest->BuildId = $build->Id;
            $buildtest->TestId = $test->Id;
            $numpass = 0;
            $numfail = 0;
            if (strpos($name, 'failedtest') !== false) {
                $buildtest->Status = 'failed';
                $numfail = 1;
            } else {
                $buildtest->Status = 'passed';
                $numpass = 1;
            }
            $buildtest->Insert();
            $build->UpdateTestNumbers($numpass, $numfail, 0);
        }

        // Manually set warning/error/failure tallies for these builds.
        $stmt = $this->PDO->prepare(
            'UPDATE build
            SET builderrors = ?, buildwarnings = ?
            WHERE id = ?');
        pdo_execute($stmt, [0, 0, $this->Builds['clean']->Id]);
        pdo_execute($stmt, [0, 1, $this->Builds['warning']->Id]);
        pdo_execute($stmt, [1, 0, $this->Builds['error1']->Id]);
        pdo_execute($stmt, [1, 0, $this->Builds['error2']->Id]);
        pdo_execute($stmt, [0, 0, $this->Builds['failedtest1']->Id]);
        pdo_execute($stmt, [0, 0, $this->Builds['failedtest2']->Id]);
        pdo_execute($stmt, [0, 0, $this->Builds['failedtest3']->Id]);
        pdo_execute($stmt, [0, 0, $this->Builds['failedtest4']->Id]);
        pdo_execute($stmt, [0, 0, $this->Builds['fixed']->Id]);

        // Also create real errors and warnings to test our analytics functions.
        $error = new BuildError();
        $error->Type = 0;
        $error->LogLine = 1;
        $error->Text = 'this is an error';
        $error->SourceFile = 'foo.c';
        $error->SourceLine = 1;
        $error->PreContext = 'this is precontext';
        $error->PostContext = 'this is postcontext';
        $error->RepeatCount = '0';

        $error->BuildId = $this->Builds['error1']->Id;
        $error->Insert();
        $error->BuildId = $this->Builds['error2']->Id;
        $error->Insert();

        $warning = new BuildFailure();
        $warning->Type = 1;
        $warning->WorkingDirectory = '/tmp';
        $warning->StdOutput = 'warning #1';
        $warning->StdError = 'this is a warning';
        $warning->ExitCondition = 0;
        $warning->Language = 'C';
        $warning->TargetName = 'foo';
        $warning->OutputFile = 'foo.lib';
        $warning->OutputType = 'static library';
        $warning->SourceFile = '/tmp/foo.c';
        $warning->BuildId = $this->Builds['warning']->Id;
        $warning->Insert();
    }

    public function testListDefects()
    {
        $buildids = [];
        foreach ($this->Builds as $name => $build) {
            $buildids[] = $build->Id;
        }
        $defects = ['builderrors', 'buildwarnings', 'testfailed'];
        $query_string = http_build_query(['buildid' => $buildids, 'defect' => $defects]);
        $response = $this->get($this->url . "/api/v1/buildProperties.php?$query_string");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $num_defects = count($jsonobj['defects']);
        if ($num_defects !== 3) {
            $this->fail("Expected 3 defects, found $num_defects");
        }
    }

    public function testComputeClassifiers()
    {
        $response = $this->get($this->url . "/api/v1/buildProperties.php?project=BuildPropertiesProject&from=2017-05-26&to=2017-05-29");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $builds = [];
        foreach ($jsonobj['builds'] as $build) {
            if ($build['testfailed'] > 0) {
                $build['success'] = false;
            } else {
                $build['success'] = true;
            }
            $builds[] = json_encode($build);
        }

        $query_string = http_build_query(['builds' => $builds]);
        $response = $this->get($this->url . "/api/v1/computeClassifier.php?$query_string");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $num_classifiers = count($jsonobj);
        if ($num_classifiers !== 3) {
            $this->fail("Expected 3 classifiers, found $num_classifiers");
        }
        foreach ($jsonobj as $entry) {
            $classifier = $entry['classifier'];
            $found = $entry['accuracy'];
            $expected = 0;
            switch ($classifier) {
                case 'debug == true':
                    $expected = 77.8;
                    break;
                case 'debug == false':
                    $expected = 77.8;
                    break;
                case 'buildtime > 7.02':
                    $expected = 100;
                    break;
                default:
                    $this->fail("Unexpected classifier $classifier");
                    break;
            }
            if ($found != $expected) {
                $this->fail("Expected $expected but found $found for $classifier");
            }
        }
    }

    private function create_build($buildname, $filename, $date, $md5)
    {
        $timestamp = strtotime($date);
        // Do the POST step of the submission.
        $fields = [
            'project' => 'BuildPropertiesProject',
            'build' => $buildname,
            'site' => 'localhost',
            'stamp' => "$date-Experimental",
            'starttime' => "$timestamp",
            'endtime' => "$timestamp",
            'track' => 'Experimental',
            'type' => 'BuildPropertiesJSON',
            'datafilesmd5[0]=' => $md5];
        $client = new GuzzleHttp\Client();
        global $CDASH_BASE_URL;
        try {
            $response = $client->request(
                'POST',
                $CDASH_BASE_URL . '/submit.php',
                [
                    'form_params' => $fields
                ]
            );
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->fail("POST submit failed for $buildname");
        }

        // Get buildid for subsequent PUT.
        $response_array = json_decode($response->getBody(), true);
        $build = new Build();
        $build->Id = $response_array['buildid'];

        // Do the PUT submission.
        $puturl = $this->url . "/submit.php?type=BuildPropertiesJSON&md5=$md5&filename=$filename&buildid=$build->Id";
        $filepath = dirname(__FILE__) . "/data/BuildProperties/$filename";
        if (!$this->uploadfile($puturl, $filepath)) {
            $this->fail("PUT submit failed for $buildname");
        }

        // Make sure these build properties were recorded in the database.
        $stmt = $this->PDO->prepare(
            'SELECT properties from buildproperties where buildid = ?');
        pdo_execute($stmt, [$build->Id]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->fail("Failed to find build properties for $buildname");
        }

        // Make sure they match what we uploaded.
        $found_properties = $row['properties'];
        $expected_properties = json_encode(json_decode(file_get_contents($filepath)));
        if ($found_properties !== $expected_properties) {
            $this->fail("Build properties mismatch for $buildname.\nFound: $found_properties\n\nExpected: $expected_properties");
        }

        $this->Builds[$buildname] = $build;
    }
}
