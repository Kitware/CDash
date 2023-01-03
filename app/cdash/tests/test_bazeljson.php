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
            'buildwarnings' => 1,
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
            'SELECT b2t.id FROM test t
            JOIN build2test b2t on b2t.testid = t.id
            JOIN build b on b.id = b2t.buildid
            WHERE b.id = ? AND t.name = ?');
        pdo_execute($test_stmt, [$buildid, '//main:hello-good']);
        $buildtestid = $test_stmt->fetchColumn();

        // Use the API to verify that only output for the specified test is displayed
        $this->get($this->url . "/api/v1/testDetails.php?buildtestid=$buildtestid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $output = $jsonobj['test']['output'];

        $not_expected = "Executed 2 out of 2 tests";
        if (strpos($output, $not_expected) !== false) {
            $this->fail("Unexpected output! Should not include test summary");
        }

        // Submit the same data again to verify that no testdiff is recorded.
        $buildid2 = $this->submit_data('InsightExample', 'BazelJSON',
            '0a9b0aeeb73618cd10d6e1bee221fd71',
            dirname(__FILE__) . '/data/Bazel/bazel_BEP.json',
            '20170824-1835-Experimental', '1503599755');
        $this->assertTrue($buildid2 > 1);
        $testdiff_stmt = $this->PDO->prepare(
            'SELECT buildid FROM testdiff WHERE buildid = :buildid');
        pdo_execute($testdiff_stmt, [':buildid' => $buildid2]);
        $this->assertTrue($testdiff_stmt->fetchColumn() === false);

        // Cleanup.
        remove_build($buildid);
        remove_build($buildid2);
    }

    public function testFilterBazelJSON()
    {
        // Create a new project.
        $settings = [
            'Name' => 'Bazel',
            'Public' => 1,
            'WarningsFilter' => 'unused variable',
            'ErrorsFilter' => 'use of undeclared identifier'
        ];
        $projectid = $this->createProject($settings);
        if ($projectid < 1) {
            $this->fail('Failed to create project');
        }
        $project = new Project();
        $project->Id = $projectid;

        // Submit testing data.
        $buildid = $this->submit_data('Bazel', 'BazelJSON',
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

        // Warnings and errors are filtered out
        $answer_key = [
            'builderrors' => 0,
            'buildwarnings' => 0,
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

        // Cleanup.
        remove_build($buildid);
        $project->Delete();
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
            "SELECT builderrors, buildwarnings, testfailed, testpassed,
                        configureerrors, configurewarnings
                FROM build WHERE id = $parentid");
        $row = $stmt->fetch();
        $answer_key = [
            'builderrors' => 0,
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


        // Validate the children builds.
        $stmt = $this->PDO->query(
            "SELECT builderrors, buildwarnings, testfailed, testpassed,
                        configureerrors, configurewarnings,
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
                        'testpassed' => 1,
                        'configureerrors' => 0,
                        'configurewarnings' => 0
                    ];
                    break;
                case 'subproj2':
                    $answer_key = [
                        'builderrors' => 0,
                        'buildwarnings' => 1,
                        'testfailed' => 1,
                        'testpassed' => 0,
                        'configureerrors' => 0,
                        'configurewarnings' => 0
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
            'abc62be615c3f2a469fe6b6ada556b75',
            dirname(__FILE__) . '/data/Bazel/bazel_testFailed.json');
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
            'SELECT b2t.id FROM test t
            JOIN build2test b2t on b2t.testid = t.id
            JOIN build b on b.id = b2t.buildid
            WHERE b.id = ? AND t.name = ?');
        pdo_execute($test_stmt, [$buildid, '//drake/bindings:pydrake_common_install_test']);
        $buildtestid = $test_stmt->fetchColumn();

        // Use the API to verify that all of the build output is displayed.
        $this->get($this->url . "/api/v1/testDetails.php?buildtestid=$buildtestid");
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
            '73817c7f786ff65ce14af7c34a6850ba',
            dirname(__FILE__) . '/data/Bazel/bazel_timeout.json');
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
            'buildwarnings' => 1,
            'testfailed' => 1,
            'testpassed' => 18,
            'configureerrors' => 0,
            'configurewarnings' => 1
        ];
        foreach ($answer_key as $key => $expected) {
            $found = $row[$key];
            if ($found != $expected) {
                $this->fail("Expected $expected for $key but found $found");
            }
        }

        // Lookup specific test ID
        $test_stmt = $this->PDO->prepare(
            'SELECT b2t.id FROM test t
            JOIN build2test b2t on b2t.testid = t.id
            JOIN build b on b.id = b2t.buildid
            WHERE b.id = ? AND t.name = ?');
        pdo_execute($test_stmt, [$buildid, '//drake/bindings:pydrake_common_install_test']);
        $buildtestid = $test_stmt->fetchColumn();

        // Use the API to verify that the 'TIMEOUT' message is displayed
        $this->get($this->url . "/api/v1/testDetails.php?buildtestid=$buildtestid");
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
            'e2b864cc0c5308148aea0d143df6d2ba',
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
            'builderrors' => 8,
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

    public function testBazelDuplicateTests()
    {
        // Submit testing data.
        $buildid = $this->submit_data('InsightExample', 'BazelJSON',
            '4a42c09a1751848dc5b806c4bc701fd6',
            dirname(__FILE__) . '/data/Bazel/automotive_test.json');
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
            'testfailed' => 1,
            'testpassed' => 0,
            'configureerrors' => 0,
            'configurewarnings' => 0
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

    public function testMultipleLineError()
    {
        // Submit testing data.
        $buildid = $this->submit_data('InsightExample', 'BazelJSON',
            '131eabf91e183359d4b3e1ef24482741',
            dirname(__FILE__) . '/data/Bazel/bazel_multiple_line_error.json');
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
            'buildwarnings' => 0,
            'testfailed' => 0,
            'testpassed' => 0,
            'configureerrors' => 0,
            'configurewarnings' => 0
        ];
        foreach ($answer_key as $key => $expected) {
            $found = $row[$key];
            if ($found != $expected) {
                $this->fail("Expected $expected for $key but found $found");
            }
        }

        // Use the API to verify that the line numbers were parsed correctly
        $this->get($this->url . "/api/v1/viewBuildError.php?buildid=$buildid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $errors = $jsonobj['errors'];
        if ($errors[0]["logline"] != 1) {
            $this->fail("Expected error at line 1, found at line ".$errors[0]["logline"]);
        }

        // Cleanup.
        remove_build($buildid);
    }

    public function testShardTest()
    {
        // Submit testing data.
        $buildid = $this->submit_data('InsightExample', 'BazelJSON',
            'cb99fa74feaf55a6777f3087becbce04',
            dirname(__FILE__) . '/data/Bazel/bazel_shard_test.json');
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
            'SELECT b2t.id FROM test t
            JOIN build2test b2t on b2t.testid = t.id
            JOIN build b on b.id = b2t.buildid
            WHERE b.id = ? AND t.name = ?');
        pdo_execute($test_stmt, [$buildid, '//automotive/maliput/multilane:multilane_lanes_test']);
        $buildtestid = $test_stmt->fetchColumn();

        $this->get($this->url . "/api/v1/testDetails.php?buildtestid=$buildtestid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        // Check Details
        $expected = "Completed";
        if (strpos($jsonobj['test']['details'], $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }

        // Cleanup.
        remove_build($buildid);
    }

    public function testShardTestFailures()
    {
        // Submit testing data.
        $buildid = $this->submit_data('InsightExample', 'BazelJSON',
            '74df5d4af1b22097caf2304b301fe4d0',
            dirname(__FILE__) . '/data/Bazel/bazel_shard_test_failures.json');
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
            'testfailed' => 2,
            'testpassed' => 36,
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
            'SELECT b2t.id FROM test t
            JOIN build2test b2t on b2t.testid = t.id
            JOIN build b on b.id = b2t.buildid
            WHERE b.id = ? AND t.name = ?');
        pdo_execute($test_stmt, [$buildid, '//automotive/maliput/multilane:multilane_builder_test']);
        $buildtestid = $test_stmt->fetchColumn();

        $this->get($this->url . "/api/v1/testDetails.php?buildtestid=$buildtestid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        // Use the API to verify that the expected output is displayed
        $output = $jsonobj['test']['output'];

        $expected = "//automotive/maliput/multilane:multilane_builder_test";
        if (strpos($output, $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }

        $expected = "multilane/multilane_builder_test/test.log";
        if (strpos($output, $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }

        $not_expected = "automotive/maliput/multilane:multilane_lanes_test";
        $result = strpos($output, $not_expected);
        if ($result != false) {
            $this->fail("Expected output to NOT include '$not_expected'");
        }

        // Lookup another specific test ID
        $test_stmt = $this->PDO->prepare(
            'SELECT b2t.id FROM test t
            JOIN build2test b2t on b2t.testid = t.id
            JOIN build b on b.id = b2t.buildid
            WHERE b.id = ? AND t.name = ?');
        pdo_execute($test_stmt, [$buildid, '//automotive/maliput/multilane:multilane_lanes_test']);
        $buildtestid = $test_stmt->fetchColumn();

        $this->get($this->url . "/api/v1/testDetails.php?buildtestid=$buildtestid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        // Use the API to verify that the expected output is displayed
        $output = $jsonobj['test']['output'];

        $expected = "automotive/maliput/multilane:multilane_lanes_test";
        if (strpos($output, $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }

        // Check Details
        $expected = "Completed (Failed)";
        if (strpos($jsonobj['test']['details'], $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }

        // Cleanup.
        remove_build($buildid);
    }

    public function testShardTestTimeout()
    {
        // Submit testing data.
        $buildid = $this->submit_data('InsightExample', 'BazelJSON',
            'aa689b38ea361e5ccdc6b59755fbec64',
            dirname(__FILE__) . '/data/Bazel/bazel_shard_test_timeout.json');
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
            'testfailed' => 1,
            'testpassed' => 0,
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
            'SELECT b2t.id FROM test t
            JOIN build2test b2t on b2t.testid = t.id
            JOIN build b on b.id = b2t.buildid
            WHERE b.id = ? AND t.name = ?');
        pdo_execute($test_stmt, [$buildid, '//automotive/maliput/multilane:multilane_lanes_test']);
        $buildtestid = $test_stmt->fetchColumn();

        $this->get($this->url . "/api/v1/testDetails.php?buildtestid=$buildtestid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        // Use the API to verify that the expected output is displayed
        $output = $jsonobj['test']['output'];

        $expected = "Note: This is test shard 8 of 10.";
        if (strpos($output, $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }
        $expected = "Note: This is test shard 9 of 10.";
        if (strpos($output, $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }
        $expected = "Note: This is test shard 10 of 10.";
        if (strpos($output, $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }
        // 'TIMEOUT' has markup, skip looking for that to make test simpler
        $expected = "in 3 out of 10 in 60.1s";
        if (strpos($output, $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }
        $expected = "Stats over 10 runs";
        if (strpos($output, $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }

        // Check Details
        $expected = "Completed (Timeout)";
        if (strpos($jsonobj['test']['details'], $expected) === false) {
            $this->fail("Expected output to include '$expected'");
        }

        // Check time - should be sum of all shards
        $expected = "3m 5s 750ms";
        if (strpos($jsonobj['test']['time'], $expected) === false) {
            $this->fail("Expected time to be $expected, found {$jsonobj['test']['time']}");
        }

        // Cleanup.
        remove_build($buildid);
    }

    private function submit_data($project_name, $upload_type, $md5, $file_path,
                                 $buildstamp = '20170823-1835-Experimental',
                                 $timestamp = '1503513355')
    {
        $fields = [
            'project' => $project_name,
            'build' => 'bazel_json',
            'site' => 'localhost',
            'stamp' => $buildstamp,
            'starttime' => $timestamp,
            'endtime' => $timestamp,
            'track' => 'Experimental',
            'type' => $upload_type,
            'datafilesmd5[0]=' => $md5];
        $client = new GuzzleHttp\Client();
        try {
            $response = $client->request(
                'POST',
                config('app.url') . '/submit.php',
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
            $this->fail("Upload failed for url {$puturl}");
            return false;
        }
        return $buildid;
    }
}
