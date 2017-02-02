<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class TestOverviewTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testTestOverview()
    {
        $this->login();

        $this->get($this->url . '/api/v1/testOverview.php');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        if ($jsonobj['error'] !== 'Project not specified.') {
            $this->fail('Missing project error not encountered when expected');
        }

        $this->get($this->url . '/api/v1/testOverview.php?project=FakeProject');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        if ($jsonobj['error'] !== 'Project does not exist.') {
            $this->fail('Nonexistent project error not encountered when expected');
        }

        $content = $this->connect($this->url . '/api/v1/testOverview.php?project=InsightExample');
        $jsonobj = json_decode($content, true);
        if ($jsonobj['tests'] !== array()) {
            $this->fail("Empty list of tests not found when expected");
        }

        $content = $this->connect($this->url . '/api/v1/testOverview.php?project=EmailProjectExample&date=2009-02-23');
        $jsonobj = json_decode($content, true);
        $num_tests = count($jsonobj['tests']);
        if ($num_tests !== 8) {
            $this->fail("Expected 6 failing tests, found $num_tests");
        }

        $this->pass('Passed');
    }
}
