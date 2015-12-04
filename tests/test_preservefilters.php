<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');
require_once('include/common.php');
require_once('include/pdo.php');

class PreserveFiltersTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testPreserveFilters()
    {
      // Load filtered data from our API.
      $this->get($this->url . "/api/v1/index.php?project=Trilinos&date=20110722&parentid=12&limit=200&filtercount=1&showfilters=1&field1=label&compare1=63&value1=ra");
      $content = $this->getBrowser()->getContent();
      $jsonobj = json_decode($content, true);
      $buildgroup = array_pop($jsonobj['buildgroups']);
      $builds = $buildgroup['builds'];

      // Verify 5 builds
      $numbuilds = sizeof($builds);
      if ($numbuilds !== 5) {
        $this->fail("Expected 5 builds, found " . $numbuilds);
        return 1;
      }

      // Next, go to 'viewTest'
      $this->get($this->url . "/api/v1/viewTest.php?onlyfailed&buildid=13&filtercount=1&showfilters=1&field1=label&compare1=63&value1=ra");
      $content = $this->getBrowser()->getContent();
      $jsonobj = json_decode($content, true);

      // Verify 10 tests
      $tests = $jsonobj['tests'];
      $numtests = sizeof($tests);
      if ($numtests !== 10) {
        $this->fail("Expected 10 tests, found " . $numtests);
        return 1;
      }
      
      // And 10 failures
      $numFailed = $jsonobj['numFailed'];
      if ($numFailed !== 10) {
        $this->fail("Expected 10 failed tests, found " . $numFailed);
        return 1;
      }
      
      // Finally, try 'queryTests'
      $this->get($this->url . "/api/v1/queryTests.php?project=Trilinos&date=2011-07-22&limit=200&filtercount=1&showfilters=1&field1=label&compare1=63&value1=ra");
      $content = $this->getBrowser()->getContent();
      $jsonobj = json_decode($content, true);
      
      // Verify 69 builds
      $builds = $jsonobj['builds'];
      $numbuilds = sizeof($builds);
      if ($numbuilds !== 69) {
        $this->fail("Expected 69 $builds, found " . $numbuilds);
        return 1;
      }

      $this->pass('Tests passed');
      return 0;
    }
}
