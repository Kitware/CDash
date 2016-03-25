<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class BranchCoverageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBranchCoverage()
    {
        global $CDASH_TESTING_RENAME_LOGS;
        $CDASH_TESTING_RENAME_LOGS = true;

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
            'datafilesmd5[0]=' => '2e5860e5be9682f1ced11ccac93b945b'));

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
        $puturl = $this->url . "/submit.php?type=GcovTar&md5=2e5860e5be9682f1ced11ccac93b945b&filename=gcov.tar&buildid=$buildid";
        $filename = dirname(__FILE__) . '/data/gcov.tar';

        $put_result = $this->uploadfile($puturl, $filename);
        $put_json = json_decode($put_result, true);

        if ($put_json['status'] != 0) {
            $this->fail(
                'PUT returned ' . $put_json['status'] . ":\n" .
                $put_json['description'] . "\n");
            return 1;
        }

        // Make sure that it recorded the source file's label in our submission.
        $content = $this->get($this->url . "/viewCoverage.php?buildid=$buildid");
        if (strpos($content, '<td align="right">Foo</td>') === false) {
            $this->fail('\"<td align="right">Foo</td>\" not found when expected');
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
        return 0;
    }
}
