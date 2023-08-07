<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/pdo.php';

use CDash\Model\Build;
use CDash\Model\PendingSubmissions;

class BranchCoverageTestCase extends KWWebTestCase
{
    protected $projectname;
    protected $buildid;
    protected $client;

    public function __construct()
    {
        parent::__construct();
        // We submit to the TrilinosDriver project just because it
        // already has labels enabled.
        $this->projectname = 'TrilinosDriver';
        $this->buildid = 0;
        $this->client = $this->getGuzzleClient();
    }

    protected function clearPriorBranchCoverageResults()
    {
        // Remove the build created by this test if it ran previously.
        $pdo = \CDash\Database::getInstance()->getPdo();
        $stmt = $pdo->query("
            SELECT id FROM build WHERE name = 'branch_coverage'");
        $existing_buildid = $stmt->fetchColumn();
        if ($existing_buildid !== false) {
            remove_build($existing_buildid);
        }

        $files = Storage::allFiles('inbox');
        Storage::delete($files);
        $files = Storage::allFiles('parsed');
        Storage::delete($files);
        $files = Storage::allFiles('inprogress');
        Storage::delete($files);
        $files = Storage::allFiles('failed');
        Storage::delete($files);

        $this->deleteLog($this->logfilename);
    }

    protected function postSubmit($token=null)
    {
        // Do the POST submission to get a pending buildid.
        $post_params = [
            'project' => $this->projectname,
            'build' => 'branch_coverage',
            'site' => 'localhost',
            'stamp' => '20150128-1436-Experimental',
            'starttime' => '1422455768',
            'endtime' => '1422455768',
            'track' => 'Experimental',
            'type' => 'GcovTar',
            'datafilesmd5[0]=' => '5454e16948a1d58d897e174b75cc5633'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->url}/submit.php");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        $post_json = json_decode($response, true);
        if ($post_json['status'] != 0) {
            $this->fail(
                'POST returned ' . $post_json['status'] . ":\n" .
                $post_json['description'] . "\n");
            return 1;
        }

        $this->buildid = $post_json['buildid'];
        if (!$this->buildid) {
            $this->fail(
                "Expected buildid, instead got $this->buildid");
            return 1;
        }
    }

    protected function putSubmit($token=null)
    {
        // Do the PUT submission to actually upload our data.
        $puturl = $this->url . "/submit.php?type=GcovTar&md5=5454e16948a1d58d897e174b75cc5633&filename=gcov.tar&buildid={$this->buildid}";
        $filename = dirname(__FILE__) . '/data/gcov.tar';
        $headers = [];
        if ($token) {
            $headers = ["Authorization: Bearer {$token}"];
        }
        $put_result = $this->uploadfile($puturl, $filename, $headers);
        if (strpos($put_result, '{"status":0}') === false) {
            $this->fail(
                "status:0 not found in PUT results:\n$put_result\n");
            return 1;
        }
    }

    protected function verifyResults()
    {
        $url = "{$this->url}/viewCoverage.php?buildid={$this->buildid}";

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
            WHERE buildid=$this->buildid
            AND cf.fullpath = './MathFunctions/mysqrt.cxx'");
        $fileid = $fileid_result[0]['fileid'];

        // Make sure branch coverage is being displayed properly.
        $content = $this->get($this->url . "/viewCoverageFile.php?buildid=$this->buildid&fileid=$fileid");
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

        // Verify that this pending submission was recorded for this build.
        $pending = new PendingSubmissions();
        $build = new Build();
        $build->Id = $this->buildid;
        $pending->Build = $build;
        $this->assertEqual($pending->GetNumFiles(), 0);
    }
}
