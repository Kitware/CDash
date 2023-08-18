<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');



class OpenCoverCoverageTestCase extends KWWebTestCase
{
    protected $buildId;

    public function __construct()
    {
        parent::__construct();
    }
    public function tearDown()
    {
        remove_build($this->buildId);
    }
    public function testOpenCoverCoverage()
    {
        // Do the POST submission to get a pending buildid.
        $post_result = $this->post($this->url."/submit.php", [
            "project" => "SubProjectExample",
            "build" => "opencover_coverage",
            "site" => "localhost",
            "stamp" => "20150128-1436-Experimental",
            "starttime" => "1422455768",
            "endtime" => "1422455768",
            "track" => "Experimental",
            "type" => "OpenCoverTar",
            "datafilesmd5[0]=" => "c0eeaf6be9838eacc75e652d6c85f925"]);

        $post_json = json_decode($post_result, true);
        if ($post_json["status"] != 0) {
            $this->fail(
                "POST returned " . $post_json["status"] . ":\n" .
                $post_json["description"]. "\n");
            return 1;
        }

        $buildid = $post_json["buildid"];
        $this->buildId= $buildid;
        if (!is_numeric($buildid) || $buildid < 1) {
            $this->fail(
                "Expected positive integer for buildid, instead got $buildid");
            return 1;
        }

        // Do the PUT submission to actually upload our data.
        $puturl = $this->url."/submit.php?type=OpenCoverTar&md5=c0eeaf6be9838eacc75e652d6c85f925&filename=OpenCoverTest.tar&buildid=$buildid";
        $filename  = dirname(__FILE__)."/data/OpenCoverTest.tar";
        $put_result = $this->uploadfile($puturl, $filename);
        if (strpos($put_result, '{"status":0}') === false) {
            $this->fail(
                "status:0 not found in PUT results:\n$put_result\n");
            return 1;
        }

        // Verify that the coverage data was successfully parsed.
        $content = $this->get(
            $this->url."/viewCoverage.php?buildid=$buildid&status=6");
        if (strpos($content, '47.37') === false) {
            $this->fail('\"47.37\" not found when expected');
            return 1;
        }

        return 0;
    }
    public function testOpenCoverCoverageWithDataJson()
    {
        // Do the POST submission to get a pending buildid.
        $post_result = $this->post($this->url."/submit.php", [
            "project" => "SubProjectExample",
            "build" => "opencover_coverage",
            "site" => "localhost",
            "stamp" => "20150128-1436-Experimental",
            "starttime" => "1422455768",
            "endtime" => "1422455768",
            "track" => "Experimental",
            "type" => "OpenCoverTar",
            "datafilesmd5[0]=" => "21eb5dff198d703652f8a7c93a290140"]);

        $post_json = json_decode($post_result, true);
        if ($post_json["status"] != 0) {
            $this->fail(
                "POST returned " . $post_json["status"] . ":\n" .
                $post_json["description"]. "\n");
            return 1;
        }

        $buildid = $post_json["buildid"];
        $this->buildId= $buildid;
        if (!is_numeric($buildid) || $buildid < 1) {
            $this->fail(
                "Expected positive integer for buildid, instead got $buildid");
            return 1;
        }

        // Do the PUT submission to actually upload our data.
        $puturl = $this->url."/submit.php?type=OpenCoverTar&md5=21eb5dff198d703652f8a7c93a290140&filename=OpenCoverTestWithDataJson.tar&buildid=$buildid";
        $filename  = dirname(__FILE__)."/data/OpenCoverTestWithDataJson.tar";

        $put_result = $this->uploadfile($puturl, $filename);
        if (strpos($put_result, '{"status":0}') === false) {
            $this->fail(
                "status:0 not found in PUT results:\n$put_result\n");
            return 1;
        }

        // Verify that the coverage data was successfully parsed.
        $content = $this->get(
            $this->url."/viewCoverage.php?buildid=$buildid&status=6");
        if (strpos($content, '69.23') === false) {
            $this->fail('\"69.23\" not found when expected');
            return 1;
        }

        return 0;
    }
}
