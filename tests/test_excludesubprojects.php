<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ExcludeSubProjectsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testExcludeSubProjects()
    {
        // Load filtered data from our API.
        $this->get($this->url . "/api/v1/index.php?date=2011-07-22&project=Trilinos&filtercount=4&showfilters=1&filtercombine=and&field1=subprojects&compare1=92&value1=Teuchos&field2=subprojects&compare2=92&value2=Sacado&field3=subprojects&compare3=92&value3=Kokkos&field4=subprojects&compare4=92&value4=AztecOO");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $build = $buildgroup['builds'][0];

        // Verify 21 configure errors (normally 22).
        if ($build['configure']['error'] !== 21) {
            $this->fail("Expected 21 configure errors, found " . $build['configure']['error']);
            return 1;
        }

        // Verify 65 configure warnings (normally 73).
        if ($build['configure']['warning'] !== 65) {
            $this->fail("Expected 65 configure warnings, found " . $build['configure']['warning']);
            return 1;
        }

        // Verify 5 build errors (normally 8).
        if ($build['compilation']['error'] !== 5) {
            $this->fail("Expected 5 build errors, found " . $build['compilation']['error']);
            return 1;
        }

        // Verify 15 build warnings (normally 296).
        if ($build['compilation']['warning'] !== 15) {
            $this->fail("Expected 15 build warnings, found " . $build['compilation']['warning']);
            return 1;
        }

        // Verify 88 tests not run (normally 95).
        if ($build['test']['notrun'] !== 88) {
            $this->fail("Expected 88 tests not run, found " . $build['compilation']['notrun']);
            return 1;
        }

        // Verify 10 tests failed (normally 11).
        if ($build['test']['fail'] !== 10) {
            $this->fail("Expected 10 tests failed, found " . $build['compilation']['fail']);
            return 1;
        }

        // Verify 33 tests passed (normally 303).
        if ($build['test']['pass'] !== 33) {
            $this->pass("Expected 33 tests passed, found " . $build['compilation']['pass']);
            return 1;
        }

        // Verify 32 labels (normally 36).
        if ($build['label'] !== '(32 labels)') {
            $this->pass("Expected (32 labels), found " . $build['label']);
            return 1;
        }

        $this->pass('Tests passed');
        return 0;
    }

    public function testIncludeSubProjects()
    {
        // Load filtered data from our API.
        $this->get($this->url . "/api/v1/index.php?date=2011-07-22&project=Trilinos&filtercount=4&showfilters=1&filtercombine=and&field1=subprojects&compare1=93&value1=Teuchos&field2=subprojects&compare2=93&value2=Sacado&field3=subprojects&compare3=93&value3=Kokkos&field4=subprojects&compare4=93&value4=AztecOO");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $build = $buildgroup['builds'][0];

        // Verify 1 configure error (normally 22).
        if ($build['configure']['error'] !== 1) {
            $this->fail("Expected 1 configure error, found " . $build['configure']['error']);
            return 1;
        }

        // Verify 8 configure warnings (normally 73).
        if ($build['configure']['warning'] !== 8) {
            $this->fail("Expected 8 configure warnings, found " . $build['configure']['warning']);
            return 1;
        }

        // Verify 3 build errors (normally 8).
        if ($build['compilation']['error'] !== 3) {
            $this->fail("Expected 3 build errors, found " . $build['compilation']['error']);
            return 1;
        }

        // Verify 281 build warnings (normally 296).
        if ($build['compilation']['warning'] !== 281) {
            $this->fail("Expected 281 build warnings, found " . $build['compilation']['warning']);
            return 1;
        }

        // Verify 7 tests not run (normally 95).
        if ($build['test']['notrun'] !== 7) {
            $this->fail("Expected 7 tests not run, found " . $build['compilation']['notrun']);
            return 1;
        }

        // Verify 1 test failed (normally 11).
        if ($build['test']['fail'] !== 1) {
            $this->fail("Expected 1 test failed, found " . $build['compilation']['fail']);
            return 1;
        }

        // Verify 270 tests passed (normally 303).
        if ($build['test']['pass'] !== 270) {
            $this->pass("Expected 270 tests passed, found " . $build['compilation']['pass']);
            return 1;
        }

        // Verify 4 labels (normally 36).
        if ($build['label'] !== '(4 labels)') {
            $this->pass("Expected (4 labels), found " . $build['label']);
            return 1;
        }

        $this->pass('Tests passed');
        return 0;
    }

}
