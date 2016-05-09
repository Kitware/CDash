<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
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
            return 1;
        }
        $next_url = $menu['next'];
        $expected_next_url = 'queryTests.php?project=Trilinos&date=2011-07-23&limit=0';
        if ($next_url != $expected_next_url) {
            $this->fail("Expected next url to be $expected_next_url, found $next_url");
            return 1;
        }

        $this->pass('Passed');
    }
}
