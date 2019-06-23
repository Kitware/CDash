<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class TimeSummaryTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testTimeSummary()
    {
        // Load data from our API.
        $this->get($this->url . '/api/v1/index.php?date=2011-07-22&project=Trilinos');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);

        // Find the build for the 'hut11.kitware' site
        $builds = $buildgroup['builds'];
        foreach ($builds as $build) {
            if ($build['site'] === 'hut11.kitware') {
                break;
            }
        }

        // Verify configure duration
        if ($build['configure']['timefull'] !== 309) {
            $this->fail('Expected configure duration of 309 seconds, found ' . $build['configure']['timefull']);
            return 1;
        }

        // Verify test duration
        if ($build['test']['timefull'] !== 48) {
            $this->fail('Expected test duration of 48 seconds, found ' . $build['test']['timefull']);
            return 1;
        }

        $this->pass('Tests passed');
        return 0;
    }
}
