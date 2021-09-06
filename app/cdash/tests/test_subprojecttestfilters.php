<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

class SubProjectTestFiltersTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testSubProjectTestFilters()
    {
        // Exclude two SubProjects (TrilinosFramework and AztecOO) from our hut11 build.
        $this->get($this->url . '/api/v1/index.php?project=Trilinos&date=2011-07-22&filtercount=3&showfilters=1&filtercombine=and&field1=subprojects&compare1=92&value1=AztecOO&field2=subprojects&compare2=92&value2=TrilinosFramework&field3=site&compare3=61&value3=hut11.kitware');
        $content = $this->getBrowser()->getContent();

        // Get (and verify) some info about this filtered build.
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $this->assertEqual(1, count($buildgroup['builds']));
        $build = $buildgroup['builds'][0];
        $this->assertEqual(273, $build['test']['pass']);
        $this->assertEqual(1, $build['test']['fail']);
        $this->assertEqual(88, $build['test']['notrun']);

        // Verify filters to be passed to viewTest.php.
        $expected = '&field1=subproject&compare1=62&value1=AztecOO&field2=subproject&compare2=62&value2=TrilinosFramework&filtercount=2&filtercombine=and&showfilters=1';
        $testfilters = $jsonobj['testfilters'];
        $this->assertEqual($expected, $testfilters);

        // Load viewTest.php with this filters and verify the results.
        $this->get("{$this->url}/api/v1/viewTest.php?buildid={$build['id']}{$testfilters}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(273, $jsonobj['numPassed']);
        $this->assertEqual(1, $jsonobj['numFailed']);
        $this->assertEqual(88, $jsonobj['numNotRun']);
        foreach ($jsonobj['tests'] as $test) {
            $this->assertNotEqual('TrilinosFramework', $test['subprojectname']);
            $this->assertNotEqual('AztecOO', $test['subprojectname']);
        }

        // Verify that SubProject test filters do not get set when viewing
        // the children of a single build.
        $this->get("{$this->url}/api/v1/index.php?project=Trilinos&date=2011-07-22&parentid={$build['id']}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual('', $jsonobj['testfilters']);

        // Verify that the "include SubProjects" filters works as expected.
        $this->get($this->url . '/api/v1/index.php?project=Trilinos&filtercount=3&field1=subprojects&compare1=93&value1=Sacado&field2=subprojects&compare2=93&value2=TrilinosFramework&field3=site&compare3=61&value3=hut11.kitware');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $this->assertEqual(1, count($buildgroup['builds']));
        $build = $buildgroup['builds'][0];
        $this->assertEqual(300, $build['test']['pass']);
        $this->assertEqual(11, $build['test']['fail']);
        $this->assertEqual(0, $build['test']['notrun']);
        $expected = '&field1=subproject&compare1=61&value1=Sacado&field2=subproject&compare2=61&value2=TrilinosFramework&filtercount=2&filtercombine=or&showfilters=1';
        $testfilters = $jsonobj['testfilters'];
        $this->assertEqual($expected, $testfilters);
        $this->get("{$this->url}/api/v1/viewTest.php?buildid={$build['id']}{$testfilters}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(300, $jsonobj['numPassed']);
        $this->assertEqual(11, $jsonobj['numFailed']);
        $this->assertEqual(0, $jsonobj['numNotRun']);
        foreach ($jsonobj['tests'] as $test) {
            if ($test['subprojectname'] != 'TrilinosFramework' && $test['subprojectname'] != 'Sacado') {
                $this->fail("Unexpected subprojectname on viewTest.php for include case: {$test['subprojectname']}");
            }
        }
    }
}
