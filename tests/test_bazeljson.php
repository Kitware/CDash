<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Project;

class BazelJSONTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->PDO = get_link_identifier()->getPdo();
        $this->BuildId = 0;
    }

    public function __destruct()
    {
        if ($this->BuildId > 0) {
        }
    }

    public function testBazelJSON()
    {
        // Submit testing data.
        $buildid = $this->submit_data('InsightExample', 'BazelJSON',
            '0a9b0aeeb73618cd10d6e1bee221fd71',
            dirname(__FILE__) . '/data/Bazel/bazel_BEP.json');
        if (!$buildid) {
            return false;
        }

        // Validate the build.
        $stmt = $this->PDO->query(
                "SELECT builderrors, buildwarnings, testfailed, testpassed,
                configureerrors, configurewarnings
                FROM build WHERE id = $buildid");
        $row = $stmt->fetch();

        $answer_key = [
            'builderrors' => 1,
            'buildwarnings' => 2,
            'testfailed' => 1,
            'testpassed' => 1,
            'configureerrors' => 0,
            'configurewarnings' => 0
        ];
        foreach ($answer_key as $key => $expected) {
            $found = $row[$key];
            if ($found != $expected) {
                $this->fail("Expected $expected for $key but found $found");
            }
        }

        // Lookup specific test ID
        $test_stmt = $this->PDO->prepare(
            'SELECT t.id FROM test t
            JOIN build2test b2t on b2t.testid = t.id
            JOIN build b on b.id = b2t.buildid
            WHERE b.id = ? AND t.name = ?');
        pdo_execute($test_stmt, [$buildid, '//main:hello-good']);
        $testid = $test_stmt->fetchColumn();

        // Use the API to verify that only output for the specified test is displayed
        $this->get($this->url . "/api/v1/testDetails.php?test=$testid&build=$buildid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $output = $jsonobj['test']['output'];

        $not_expected = "Executed 2 out of 2 tests";
        if (strpos($output, $not_expected) !== false) {
            $this->fail("Unexpected output! Should not include test summary");
        }

        // Cleanup.
        remove_build($buildid);
    }

    public function testBazelSubProjs()
    {
        // Create a new project.
        $settings = [
            'Name' => 'BazelSubProj',
            'Public' => 1
        ];
        $projectid = $this->createProject($settings);
        if ($projectid < 1) {
            $this->fail('Failed to create project');
        }
        $project = new Project();
        $project->Id = $projectid;

        // Setup subprojects.
        $parentid = $this->submit_data('BazelSubProj', 'SubProjectDirectories',
            '9e909746b706562eb263262a1496f202',
            dirname(__FILE__) . '/data/Bazel/subproj/subproj_list.txt');
        if (!$parentid) {
            return false;
        }

        // Submit build and test data.
        $parentid2 = $this->submit_data('BazelSubProj', 'BazelJSON',
            'a860786f23529d62472ba363525cd2f3',
            dirname(__FILE__) . '/data/Bazel/subproj/subproj_build.json');
        if (!$parentid2 || $parentid !== $parentid2) {
            $this->fail("parentid mismatch $parentid vs $parentid2");
            return false;
        }
        $parentid3 = $this->submit_data('BazelSubProj', 'BazelJSON',
            'c261e5014fddb72b372b85449be3301e',
            dirname(__FILE__) . '/data/Bazel/subproj/subproj_test.json');
        if (!$parentid3 || $parentid !== $parentid3) {
            $this->fail("parentid mismatch $parentid vs $parentid3");
            return false;
        }

        // Validate the parent build.
        $stmt = $this->PDO->query(
                "SELECT builderrors, buildwarnings, testfailed, testpassed
                FROM build WHERE id = $parentid");
        $row = $stmt->fetch();
        $answer_key = [
            'builderrors' => 0,
            'buildwarnings' => 2,
            'testfailed' => 1,
            'testpassed' => 1
        ];
        foreach ($answer_key as $key => $expected) {
            $found = $row[$key];
            if ($found != $expected) {
                $this->fail("Expected $expected for $key but found $found");
            }
        }


        // Validate the children builds.
        $stmt = $this->PDO->query(
                "SELECT builderrors, buildwarnings, testfailed, testpassed,
                        sp.name
                FROM build b
                JOIN subproject2build sp2b ON sp2b.buildid = b.id
                JOIN subproject sp ON sp.id = sp2b.subprojectid
                WHERE parentid = $parentid");
        while ($row = $stmt->fetch()) {
            $subproject_name = $row['name'];
            $answer_key = [];
            switch ($row['name']) {
                case 'subproj1':
                    $answer_key = [
                        'builderrors' => 0,
                        'buildwarnings' => 1,
                        'testfailed' => 0,
                        'testpassed' => 1
                    ];
                    break;
                case 'subproj2':
                    $answer_key = [
                        'builderrors' => 0,
                        'buildwarnings' => 1,
                        'testfailed' => 1,
                        'testpassed' => 0
                    ];
                    break;
                default:
                    $this->fail("Unexpected subproject $subproject_name");
                    break;
            }
            foreach ($answer_key as $key => $expected) {
                $found = $row[$key];
                if ($found != $expected) {
                    $this->fail("Expected $expected for $key but found $found for subproject $subproject_name");
                }
            }
        }

        // Cleanup.
        remove_project_builds($projectid);
        $project->Delete();
    }

    public function testBazelTestFailed()
    {
        // Submit testing data.
        $buildid = $this->submit_data('InsightExample', 'BazelJSON',
            '83f69abfe3982e79c17a0d669bddadf7',
            dirname(__FILE__) . '/data/Bazel/bazel_testFailed.json');
        if (!$buildid) {
            return false;
        }

        // Validate the build.
        $stmt = $this->PDO->query(
                "SELECT builderrors, buildwarnings, testfailed, testpassed
                FROM build WHERE id = $buildid");
        $row = $stmt->fetch();

        $answer_key = [
            'builderrors' => 0,
            'buildwarnings' => 0,
            'testfailed' => 3,
            'testpassed' => 1
        ];
        foreach ($answer_key as $key => $expected) {
            $found = $row[$key];
            if ($found != $expected) {
                $this->fail("Expected $expected for $key but found $found");
            }
        }

        // Lookup specific test ID
        $test_stmt = $this->PDO->prepare(
            'SELECT t.id FROM test t
            JOIN build2test b2t on b2t.testid = t.id
            JOIN build b on b.id = b2t.buildid
            WHERE b.id = ? AND t.name = ?');
        pdo_execute($test_stmt, [$buildid, '//drake/bindings:pydrake_common_install_test']);
        $testid = $test_stmt->fetchColumn();

        // Use the API to verify that all of the build output is displayed.
        $this->get($this->url . "/api/v1/testDetails.php?test=$testid&build=$buildid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $output = $jsonobj['test']['output'];

        $expected = "FAIL: testDrakeFindResourceOrThrowInInstall (__main__.TestCommonInstall)";
        if (strpos($output, $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }

        // Cleanup.
        remove_build($buildid);
    }

    public function testBazelTimeout()
    {
        // Submit testing data.
        $buildid = $this->submit_data('InsightExample', 'BazelJSON',
            'c0bd82ecbd65043f2a7f2cc0d638871f',
            dirname(__FILE__) . '/data/Bazel/bazel_timeout.json');
        if (!$buildid) {
            return false;
        }

        // Validate the build.
        $stmt = $this->PDO->query(
                "SELECT builderrors, buildwarnings, testfailed, testpassed
                FROM build WHERE id = $buildid");
        $row = $stmt->fetch();

        $answer_key = [
            'builderrors' => 0,
            'buildwarnings' => 1,
            'testfailed' => 1,
            'testpassed' => 18
        ];
        foreach ($answer_key as $key => $expected) {
            $found = $row[$key];
            if ($found != $expected) {
                $this->fail("Expected $expected for $key but found $found");
            }
        }

        // Lookup specific test ID
        $test_stmt = $this->PDO->prepare(
            'SELECT t.id FROM test t
            JOIN build2test b2t on b2t.testid = t.id
            JOIN build b on b.id = b2t.buildid
            WHERE b.id = ? AND t.name = ?');
        pdo_execute($test_stmt, [$buildid, '//drake/bindings:pydrake_common_install_test']);
        $testid = $test_stmt->fetchColumn();

        // Use the API to verify that the 'TIMEOUT' message is displayed
        $this->get($this->url . "/api/v1/testDetails.php?test=$testid&build=$buildid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $output = $jsonobj['test']['output'];

        $expected = "TIMEOUT";
        if (strpos($output, $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }

        // Cleanup.
        remove_build($buildid);
    }

    public function testBazelConfigure()
    {
        // Submit testing data.
        $buildid = $this->submit_data('InsightExample', 'BazelJSON',
            '7fccba5b31e7d681fa9f82632cb19a06',
            dirname(__FILE__) . '/data/Bazel/bazel_configure.json');
        if (!$buildid) {
            return false;
        }

        // Validate the build.
        $stmt = $this->PDO->query(
                "SELECT builderrors, buildwarnings, testfailed, testpassed,
                configureerrors, configurewarnings
                FROM build WHERE id = $buildid");
        $row = $stmt->fetch();

        $answer_key = [
            'builderrors' => 0,
            'buildwarnings' => 0,
            'testfailed' => 0,
            'testpassed' => 0,
            'configureerrors' => 1,
            'configurewarnings' => 700
        ];
        foreach ($answer_key as $key => $expected) {
            $found = $row[$key];
            if ($found != $expected) {
                $this->fail("Expected $expected for $key but found $found");
            }
        }

        // Cleanup.
        remove_build($buildid);
    }

    private function submit_data($project_name, $upload_type, $md5, $file_path)
    {
        $fields = [
            'project' => $project_name,
            'build' => 'bazel_json',
            'site' => 'localhost',
            'stamp' => '20170823-1835-Experimental',
            'starttime' => '1503513355',
            'endtime' => '1503513355',
            'track' => 'Experimental',
            'type' => $upload_type,
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
            $this->fail('POST submit failed: ' . $e->getMessage());
            return false;
        }

        // Parse buildid for subsequent PUT request.
        $response_array = json_decode($response->getBody(), true);
        $buildid = $response_array['buildid'];

        // Do the PUT request.
        $file_name = basename($file_path);
        $puturl = $this->url . "/submit.php?type=$upload_type&md5=$md5&filename=$file_name&buildid=$buildid";
        if ($this->uploadfile($puturl, $file_path) === false) {
            $this->fail("Upload failed for $file_name");
            return false;
        }
        return $buildid;
    }
}
