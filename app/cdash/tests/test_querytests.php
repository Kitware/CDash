<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use Illuminate\Support\Facades\DB;

require_once dirname(__FILE__) . '/cdash_test_case.php';

class QueryTestsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testQueryTests()
    {
        $this->get($this->url . '/api/v1/queryTests.php?date=2011-07-22&project=Trilinos');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        // Make sure the correct number of tests were found.
        $numbuilds = count($jsonobj['builds']);
        if ($numbuilds != 489) {
            $this->fail("Expected 489 builds, found $numbuilds");
            return 1;
        }

        // Make sure the next and previous links work as expected.
        $menu = $jsonobj['menu'];
        $previous_url = $menu['previous'];
        $expected_previous_url = 'queryTests.php?project=Trilinos&date=2011-07-21&limit=0';
        if ($previous_url != $expected_previous_url) {
            $this->fail("Expected previous url to be $expected_previous_url, found $previous_url");
        }
        $next_url = $menu['next'];
        $expected_next_url = 'queryTests.php?project=Trilinos&date=2011-07-23&limit=0';
        if ($next_url != $expected_next_url) {
            $this->fail("Expected next url to be $expected_next_url, found $next_url");
        }

        // Make sure the test output filters work as expected.
        $this->get($this->url . '/api/v1/queryTests.php?project=Trilinos&date=2011-07-22&filtercount=2&showfilters=1&filtercombine=and&field1=testoutput&compare1=95&value1=analytic&field2=testoutput&compare2=94&value2=%5E2');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(count($jsonobj['builds']), 6);
        $this->assertTrue($jsonobj['filterontestoutput']);
        $this->assertEqual($jsonobj['builds'][0]['labels'], 'Sacado');
        $idx = strpos($jsonobj['builds'][0]['matchingoutput'], 'analytic');
        $this->assertEqual($idx, 96);

        // Make sure cases with more than one label are handled appropriately.
        $query_result = DB::select("
                            SELECT
                                t.id AS testid,
                                l2t.buildid AS buildid
                            FROM
                                label l,
                                label2test l2t,
                                test t
                            WHERE
                                l.id = l2t.labelid
                                AND l2t.outputid = t.id
                                AND l.text = 'Claps'
                            LIMIT 1
                        ")[0];
        DB::insert("INSERT INTO label (text) VALUES ('TestLabel')");
        $labelid = (int) DB::select("SELECT id FROM label WHERE text = 'TestLabel'")[0]->id;
        DB::insert('INSERT INTO label2test (labelid, buildid, outputid) VALUES (?, ?, ?)', [$labelid, $query_result->buildid, $query_result->testid]);
        $this->get($this->url . '/api/v1/queryTests.php?project=Trilinos&filtercount=1&showfilters=1&field1=label&compare1=63&value1=Claps');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual($jsonobj['builds'][0]['labels'], 'Claps, TestLabel');
        DB::insert('DELETE FROM label2test WHERE labelid = ? AND buildid = ? AND outputid = ?', [$labelid, $query_result->buildid, $query_result->testid]);
        DB::delete("DELETE FROM label WHERE text = 'TestLabel'");


        $this->get($this->url . '/api/v1/queryTests.php?project=Trilinos&date=2011-07-22&filtercount=2&showfilters=1&filtercombine=and&field1=testoutput&compare1=97&value1=der%5Biva%5D%2Btive&field2=testoutput&compare2=96&value2=Tay.*%3F%20series');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(count($jsonobj['builds']), 2);
        $this->assertEqual($jsonobj['builds'][0]['group'], 'Experimental');
    }
}
