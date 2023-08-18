<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';



class MultiCoverageTestCase extends KWWebTestCase
{
    protected $BuildId;

    public function __construct()
    {
        parent::__construct();
        $this->BuildId = 0;
    }

    public function testXMLFirst()
    {
        $this->BuildId = 0;
        $success = true;

        $success &= $this->submitXML();
        $success &= $this->submitTar();
        $success &= $this->verifyResults();

        if ($success) {
            $this->pass("Passed");
        }

        remove_build($this->BuildId);
        return $success;
    }

    public function testTarFirst()
    {
        $this->BuildId = 0;
        $success = true;

        $success &= $this->submitTar();
        $success &= $this->submitXML();
        $success &= $this->verifyResults();

        if ($success) {
            $this->pass("Passed");
        }

        remove_build($this->BuildId);
        return $success;
    }

    public function submitXML()
    {
        $filesToSubmit = ['Coverage.xml', 'CoverageLog-0.xml'];
        $dir = dirname(__FILE__) . '/data/MultiCoverage';
        foreach ($filesToSubmit as $file) {
            if (!$this->submission('TrilinosDriver', "$dir/$file")) {
                $this->fail("Failed to submit $file");
                return 1;
            }
        }
    }

    public function submitTar()
    {
        $post_result = $this->post($this->url . '/submit.php', [
            'project' => 'TrilinosDriver',
            'build' => 'multi_coverage_example',
            'site' => 'localhost',
            'stamp' => '20160505-1541-Experimental',
            'starttime' => '1462462884',
            'endtime' => '1462462884',
            'track' => 'Experimental',
            'type' => 'GcovTar',
            'datafilesmd5[0]=' => '65f385dd8d360e78a35453144c0919ab']);

        $post_json = json_decode($post_result, true);
        if ($post_json['status'] != 0) {
            $this->fail(
                'POST returned ' . $post_json['status'] . ":\n" .
                $post_json['description'] . "\n");
            return false;
        }

        $this->BuildId = $post_json['buildid'];
        if (!is_numeric($this->BuildId) || $this->BuildId < 1) {
            $this->fail(
                "Expected positive integer for buildid, instead got $this->BuildId");
            return false;
        }

        // Do the PUT submission to actually upload our data.
        $puturl = $this->url . "/submit.php?type=GcovTar&md5=65f385dd8d360e78a35453144c0919ab&filename=gcov.tar&buildid=$this->BuildId";
        $filename = dirname(__FILE__) . '/data/MultiCoverage/gcov.tar';

        $put_result = $this->uploadfile($puturl, $filename);
        if (strpos($put_result, '{"status":0}') === false) {
            $this->fail(
                "status:0 not found in PUT results:\n$put_result\n");
            return false;
        }
        return true;
    }

    public function verifyResults()
    {
        // Make sure that it recorded the source file's label in our submission.
        $content = $this->get($this->url . "/viewCoverage.php?buildid=$this->BuildId");
        if (strpos($content, '<td align="right">aggro</td>') === false) {
            $this->fail('\"<td align="right">aggro</td>\" not found when expected');
            return 1;
        }

        // Verify details about our covered files.
        $result = pdo_query(
            "SELECT c.loctested, c.locuntested, c.fileid, cf.fullpath
                FROM coverage AS c
                INNER JOIN coveragefile AS cf ON c.fileid=cf.id
                WHERE c.buildid=$this->BuildId");
        while ($row = pdo_fetch_array($result)) {
            $loctested = $row['loctested'];
            $locuntested = $row['locuntested'];
            $fileid = $row['fileid'];
            $filename = $row['fullpath'];
            switch ($filename) {
                case './foo.cxx':
                    if ($loctested != 5) {
                        $this->fail("Expected 5 loctested for $filename, found $loctested");
                    }
                    if ($locuntested != 0) {
                        $this->fail("Expected 0 locuntested for $filename, found $locuntested");
                    }

                    // Make sure branch coverage is being displayed properly.
                    $content = $this->get($this->url . "/viewCoverageFile.php?buildid=$this->BuildId&fileid=$fileid");
                    if (strpos($content, '<span class="error">  1/2</span><span class="normal">    2 |   if (i == 0)</span>') === false) {
                        $this->fail('\"<span class="error">  1/2</span><span class="normal">    2 |   if (i == 0)</span>\" not found when expected');
                        return 1;
                    }
                    break;

                case './bar.py':
                    if ($loctested != 3) {
                        $this->fail("Expected 5 loctested for $filename, found $loctested");
                    }
                    if ($locuntested != 1) {
                        $this->fail("Expected 0 locuntested for $filename, found $locuntested");
                    }
                    break;
                default:
                    $this->fail("Unexpected file $filename");
                    break;
            }
        }
        return true;
    }
}
