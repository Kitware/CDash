<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ExcludeSubProjectsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testExcludeSubProjects()
    {
        // Load filtered data from our API.
        $this->get($this->url . '/api/v1/index.php?date=2011-07-22&project=Trilinos&filtercount=4&showfilters=1&filtercombine=and&field1=subprojects&compare1=92&value1=Teuchos&field2=subprojects&compare2=92&value2=Sacado&field3=subprojects&compare3=92&value3=Kokkos&field4=subprojects&compare4=92&value4=AztecOO');
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

        // Verify 21 configure errors (normally 22).
        if ($build['configure']['error'] !== 21) {
            $this->fail('Expected 21 configure errors, found ' . $build['configure']['error']);
            return 1;
        }

        // Verify 32 configure warnings (normally 36).
        if ($build['configure']['warning'] !== 32) {
            $this->fail('Expected 32 configure warnings, found ' . $build['configure']['warning']);
            return 1;
        }

        // Verify configure duration of 261 seconds (normally 309).
        if ($build['configure']['timefull'] !== 261) {
            $this->fail('Expected configure duration to be 261, found ' . $build['configure']['timefull']);
            return 1;
        }

        // Verify 5 build errors (normally 8).
        if ($build['compilation']['error'] !== 5) {
            $this->fail('Expected 5 build errors, found ' . $build['compilation']['error']);
            return 1;
        }

        // Verify 15 build warnings (normally 296).
        if ($build['compilation']['warning'] !== 15) {
            $this->fail('Expected 15 build warnings, found ' . $build['compilation']['warning']);
            return 1;
        }

        // Verify build duration of 4m 41s (normally 10m 46s).
        if ($build['compilation']['time'] !== '4m 41s') {
            $this->fail('Expected build duration to be 4m 41s, found ' . $build['compilation']['time']);
            return 1;
        }

        // Verify 88 tests not run (normally 95).
        if ($build['test']['notrun'] !== 88) {
            $this->fail('Expected 88 tests not run, found ' . $build['compilation']['notrun']);
            return 1;
        }

        // Verify 10 tests failed (normally 11).
        if ($build['test']['fail'] !== 10) {
            $this->fail('Expected 10 tests failed, found ' . $build['compilation']['fail']);
            return 1;
        }

        // Verify 33 tests passed (normally 303).
        if ($build['test']['pass'] !== 33) {
            $this->fail('Expected 33 tests passed, found ' . $build['compilation']['pass']);
            return 1;
        }

        // Verify test duration of 44 seconds (normally 48).
        if ($build['test']['timefull'] !== 44) {
            $this->fail('Expected test duration to be 44, found ' . $build['test']['timefull']);
            return 1;
        }

        // Verify 32 labels (normally 36).
        if ($build['label'] !== '(32 labels)') {
            $this->fail('Expected (32 labels), found ' . $build['label']);
            return 1;
        }

        $this->pass('Tests passed');
        return 0;
    }

    public function testExcludeAllButOneSubProject()
    {
        // Show only the results from hut12.kitware,
        // excluding Teuchos and TrilinosFramework.
        $this->get($this->url . '/api/v1/index.php?project=Trilinos&date=2011-07-22&filtercount=3&showfilters=1&filtercombine=and&field1=subprojects&compare1=92&value1=TrilinosFramework&field2=subprojects&compare2=92&value2=Teuchos&field3=site&compare3=61&value3=hut12.kitware');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $build = array_pop($buildgroup['builds']);

        // Verify that the only label is 'ThreadPool'.
        if ($build['label'] !== 'ThreadPool') {
            $this->fail('Expected ThreadPool, found ' . $build['label']);
            return 1;
        }

        $this->pass('Tests passed');
        return 0;
    }

    public function testIncludeSubProjects()
    {
        // Load filtered data from our API.
        $this->get($this->url . '/api/v1/index.php?date=2011-07-22&project=Trilinos&filtercount=4&showfilters=1&filtercombine=and&field1=subprojects&compare1=93&value1=Teuchos&field2=subprojects&compare2=93&value2=Sacado&field3=subprojects&compare3=93&value3=Kokkos&field4=subprojects&compare4=93&value4=AztecOO');
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

        // Verify 1 configure error (normally 22).
        if ($build['configure']['error'] !== 1) {
            $this->fail('Expected 1 configure error, found ' . $build['configure']['error']);
            return 1;
        }

        // Verify 4 configure warnings (normally 36).
        if ($build['configure']['warning'] !== 4) {
            $this->fail('Expected 4 configure warnings, found ' . $build['configure']['warning']);
            return 1;
        }

        // Verify configure duration of 48 seconds (normally 309).
        if ($build['configure']['timefull'] !== 48) {
            $this->fail('Expected configure duration to be 48, found ' . $build['configure']['timefull']);
            return 1;
        }

        // Verify 3 build errors (normally 8).
        if ($build['compilation']['error'] !== 3) {
            $this->fail('Expected 3 build errors, found ' . $build['compilation']['error']);
            return 1;
        }

        // Verify 281 build warnings (normally 296).
        if ($build['compilation']['warning'] !== 281) {
            $this->fail('Expected 281 build warnings, found ' . $build['compilation']['warning']);
            return 1;
        }

        // Verify build duration of 6m 5s (normally 10m 46s).
        if ($build['compilation']['time'] !== '6m 5s') {
            $this->fail('Expected build duration to be 6m 5s, found ' . $build['compilation']['time']);
            return 1;
        }

        // Verify 7 tests not run (normally 95).
        if ($build['test']['notrun'] !== 7) {
            $this->fail('Expected 7 tests not run, found ' . $build['compilation']['notrun']);
            return 1;
        }

        // Verify 1 test failed (normally 11).
        if ($build['test']['fail'] !== 1) {
            $this->fail('Expected 1 test failed, found ' . $build['compilation']['fail']);
            return 1;
        }

        // Verify 270 tests passed (normally 303).
        if ($build['test']['pass'] !== 270) {
            $this->pass('Expected 270 tests passed, found ' . $build['compilation']['pass']);
            return 1;
        }

        // Verify test duration of 4 seconds (normally 48).
        if ($build['test']['timefull'] !== 4) {
            $this->fail('Expected test duration to be 4, found ' . $build['test']['timefull']);
            return 1;
        }

        // Verify 4 labels (normally 36).
        if ($build['label'] !== '(4 labels)') {
            $this->pass('Expected (4 labels), found ' . $build['label']);
            return 1;
        }

        $this->pass('Tests passed');
        return 0;
    }

    public function testIncludeOneSubProject()
    {
        // Show only the results from hut12.kitware,
        // including ony the TrilinosFramework SubProject.
        $this->get($this->url . '/api/v1/index.php?project=Trilinos&date=2011-07-22&filtercount=2&showfilters=1&filtercombine=and&field1=subprojects&compare1=93&value1=TrilinosFramework&field2=site&compare2=61&value2=hut12.kitware');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $build = array_pop($buildgroup['builds']);

        // Verify that the only label is 'TrilinosFramework'.
        if ($build['label'] !== 'TrilinosFramework') {
            $this->fail('Expected TrilinosFramework, found ' . $build['label']);
            return 1;
        }

        $this->pass('Tests passed');
        return 0;
    }

    public function testExcludeHonorsOtherFilters()
    {
        $baseurl = "$this->url/api/v1/index.php?project=Trilinos&date=2011-07-22&filtercombine=and&field1=site&compare1=61&value1=hut11.kitware&showfilters=1";

        $test_cases = array(
            array(
                'filter' => 'buildduration',
                'compare' => 41,
                'value' => '10m 46s',
                'exclude' => 'Teuchos'
            ),
            array(
                'filter' => 'builderrors',
                'compare' => 43,
                'value' => 5,
                'exclude' => 'Mesquite'
            ),
            array(
                'filter' => 'buildwarnings',
                'compare' => 42,
                'value' => 29,
                'exclude' => 'Sacado'
            ),
            array(
                'filter' => 'configureduration',
                'compare' => 41,
                'value' => 309,
                'exclude' => 'Teuchos'
            ),
            array(
                'filter' => 'configureerrors',
                'compare' => 43,
                'value' => 21,
                'exclude' => 'Kokkos'
            ),
            array(
                'filter' => 'configurewarnings',
                'compare' => 42,
                'value' => 35,
                'exclude' => 'Sacado'
            ),
            array(
                'filter' => 'testsduration',
                'compare' => 41,
                'value' => 48,
                'exclude' => 'TrilinosFramework'
            ),
            array(
                'filter' => 'testsfailed',
                'compare' => 43,
                'value' => 10,
                'exclude' => 'TrilinosFramework'
            ),
            array(
                'filter' => 'testsnotrun',
                'compare' => 42,
                'value' => 65,
                'exclude' => 'Didasko'
            ),
            array(
                'filter' => 'testspassed',
                'compare' => 42,
                'value' => 33,
                'exclude' => 'Sacado'
            )
        );

        foreach ($test_cases as $test_case) {
            $filter = $test_case['filter'];
            $compare = $test_case['compare'];
            $value = $test_case['value'];
            $exclude = $test_case['exclude'];

            $exclude_filter = "&field3=subprojects&compare3=92&value3=$exclude";
            $filter_to_test = "&field2=$filter&compare2=$compare&value2=$value";

            // Verify that the build is shown without the exclude clause.
            $this->get($baseurl . $filter_to_test . '&filtercount=2');
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);
            $buildgroup = array_pop($jsonobj['buildgroups']);
            $numbuilds = count($buildgroup['builds']);
            if ($numbuilds != 1) {
                $this->fail("Expected 1 build, found $numbuilds for $filter");
            }

            // Verify that the build is not shown when the exclude clause
            // is added.
            $this->get($baseurl . $filter_to_test . $exclude_filter .
                    '&filtercount=3');
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);
            if (!empty($jsonobj['buildgroups'])) {
                $this->fail("buildgroups not empty when expected for $filter");
            }
        }
    }
}
