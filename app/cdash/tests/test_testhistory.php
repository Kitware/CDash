<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class TestHistoryTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testTestHistory()
    {
        // Submit our testing data.
        $rep = dirname(__FILE__) . '/data/TestHistory';
        if (!$this->submission('InsightExample', "$rep/Test_1.xml")) {
            $this->fail('Failed to submit Test_1.xml');
            return 1;
        }
        if (!$this->submission('InsightExample', "$rep/Test_2.xml")) {
            $this->fail('Failed to submit Test_1.xml');
            return 1;
        }

        // Get the IDs for the two builds that we just created.
        $result = pdo_query("SELECT id FROM build WHERE name='TestHistory' ORDER BY starttime");
        $num_builds = pdo_num_rows($result);
        if ($num_builds != 2) {
            $this->fail("Expected 2 builds, found $num_builds");
            return 1;
        }

        $buildids = [];
        while ($row = pdo_fetch_array($result)) {
            $buildids[] = $row['id'];
        }

        // Verify that testing history matches what we expect.
        $url = "{$this->url}/api/v1/viewTest.php?buildid={$buildids[1]}&groupid=15&previous_builds={$buildids[1]},+{$buildids[0]}&projectid=5&tests%5B%5D=fails&tests%5B%5D=notrun&tests%5B%5D=flaky&tests%5B%5D=passes&time_begin=2015-11-16T01:00:00&time_end=2015-11-17T01:00:00";
        $client = $this->getGuzzleClient();
        $response = $client->request('GET',
                $url,
                ['http_errors' => false]);
        $jsonobj = json_decode($response->getBody(), true);
        foreach ($jsonobj['tests'] as $test) {
            $history = $test['history'];
            if ($test['name'] == 'fails' && $history != 'Broken') {
                $this->fail("Expected history for test 'fails' to be 'Broken', instead found '$history'");
            }
            if ($test['name'] == 'notrun' && $history != 'Inactive') {
                $this->fail("Expected history for test 'notrun' to be 'Inactive', instead found '$history'");
            }
            if ($test['name'] == 'flaky' && $history != 'Unstable') {
                $this->fail("Expected history for test 'flaky' to be 'Unstable', instead found '$history'");
            }
            if ($test['name'] == 'passes' && $history != 'Stable') {
                $this->fail("Expected history for test 'passes' to be 'Stable', instead found '$history'");
            }
        }

        // Verify test graphs for our 'flaky' test.
        $test_result = \DB::select(
            "SELECT id FROM test WHERE name = 'flaky' AND projectid = 5");
        $testid = $test_result[0]->id;
        $flaky_id_1 = \DB::table('build2test')
            ->where('buildid', '=', $buildids[0])
            ->where('testid', '=', $testid)
            ->value('id');
        $flaky_id_2 = \DB::table('build2test')
            ->where('buildid', '=', $buildids[1])
            ->where('testid', '=', $testid)
            ->value('id');

        $this->get("{$this->url}/api/v1/testGraph.php?testid={$testid}&buildid={$buildids[1]}&type=time");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(0, $jsonobj[0]['data'][0]['y']);
        $this->assertEqual(1, $jsonobj[0]['data'][1]['y']);

        $this->get("{$this->url}/api/v1/testGraph.php?testid={$testid}&buildid={$buildids[1]}&type=status");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(1, $jsonobj[0]['data'][0]['y']);
        $this->assertEqual($flaky_id_2, $jsonobj[0]['data'][0]['buildtestid']);
        $this->assertEqual(-1, $jsonobj[1]['data'][0]['y']);
        $this->assertEqual($flaky_id_1, $jsonobj[1]['data'][0]['buildtestid']);

        // Delete the builds that we created during this test.
        remove_build($buildids[0]);
        remove_build($buildids[1]);
    }
}
