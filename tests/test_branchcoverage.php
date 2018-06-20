<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/pdo.php';

class BranchCoverageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBranchCoverage()
    {
        // Do the POST submission to get a pending buildid.
        // We submit to the TrilinosDriver project just because it
        // already has labels enabled.
        $post_result = $this->post($this->url . '/submit.php', array(
            'project' => 'TrilinosDriver',
            'build' => 'branch_coverage',
            'site' => 'localhost',
            'stamp' => '20150128-1436-Experimental',
            'starttime' => '1422455768',
            'endtime' => '1422455768',
            'track' => 'Experimental',
            'type' => 'GcovTar',
            'datafilesmd5[0]=' => '5454e16948a1d58d897e174b75cc5633'));

        $post_json = json_decode($post_result, true);
        if ($post_json['status'] != 0) {
            $this->fail(
                'POST returned ' . $post_json['status'] . ":\n" .
                $post_json['description'] . "\n");
            return 1;
        }

        $buildid = $post_json['buildid'];
        if (!is_numeric($buildid) || $buildid < 1) {
            $this->fail(
                "Expected positive integer for buildid, instead got $buildid");
            return 1;
        }

        // Do the PUT submission to actually upload our data.
        $puturl = $this->url . "/submit.php?type=GcovTar&md5=5454e16948a1d58d897e174b75cc5633&filename=gcov.tar&buildid=$buildid";
        $filename = dirname(__FILE__) . '/data/gcov.tar';

        $put_result = $this->uploadfile($puturl, $filename);
        $put_json = json_decode($put_result, true);

        // TODO: This delivers false positive upon server error
        if ($put_json['status'] != 0) {
            $this->fail(
                'PUT returned ' . $put_json['status'] . ":\n" .
                $put_json['description'] . "\n");
            return 1;
        }

        $url = "{$this->url}/viewCoverage.php?buildid={$buildid}";

        // Make sure that it recorded the source file's label in our submission.
        $content = $this->get($url);
        if (strpos($content, '<td align="right">Foo</td>') === false) {
            $msg = '\"<td align="right">Foo</td>\" not found when expected'
                . PHP_EOL . 'URL: ' . $url;
            $this->fail($msg);
            return 1;
        }
        // Look up the ID of one of the coverage files that we just submitted.
        $fileid_result = $this->db->query("
            SELECT c.fileid FROM coverage AS c
            INNER JOIN coveragefile AS cf ON c.fileid=cf.id
            WHERE buildid=$buildid
            AND cf.fullpath = './MathFunctions/mysqrt.cxx'");
        $fileid = $fileid_result[0]['fileid'];

        // Make sure branch coverage is being displayed properly.
        $content = $this->get($this->url . "/viewCoverageFile.php?buildid=$buildid&fileid=$fileid");
        if (strpos($content, '<span class="error">  1/2</span><span class="normal">    7 |   if (x &lt;= 0)</span>') === false) {
            $this->fail('\"<span class="error">  1/2</span><span class="normal">    7 |   if (x &lt;= 0)</span>\" not found when expected');
            return 1;
        }

        // Make sure our uncovered results also made it into the database.
        $row = pdo_single_row_query(
            "SELECT loctested, locuntested FROM coverage
            INNER JOIN coveragefile ON (coverage.fileid=coveragefile.id)
            WHERE coveragefile.fullpath LIKE '%uncovered1.cxx%'");
        if (!$row || !array_key_exists('loctested', $row)) {
            $this->fail("Expected 1 result for uncovered file, found 0");
            return 1;
        }
        if ($row['loctested'] != 0 || $row['locuntested'] != 1) {
            $this->fail("Uncovered results differ from expectation");
            return 1;
        }

        return 0;
    }
}
