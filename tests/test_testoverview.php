<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/pdo.php';

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
        if ($num_tests !== 6) {
            $this->fail("Expected 6 failing tests, found $num_tests");
        }

        $content = $this->connect($this->url . '/api/v1/testOverview.php?project=EmailProjectExample&date=2009-02-23&showpassed=1');
        $jsonobj = json_decode($content, true);
        $num_tests = count($jsonobj['tests']);
        if ($num_tests !== 8) {
            $this->fail("Expected 8 failing tests, found $num_tests");
        }

        // Find the buildgroup id for Trilinos Experimental builds.
        $PDO = get_link_identifier()->getPdo();
        $stmt = $PDO->query(
            "SELECT id FROM buildgroup WHERE name = 'Experimental' AND projectid = (SELECT id FROM project WHERE name = 'Trilinos')");
        $groupid = $stmt->fetchColumn();
        if (!$groupid) {
            $this->fail('Failed to fetch groupid for Trilinos Experimental');
        }

        $content = $this->connect($this->url . "/api/v1/testOverview.php?project=Trilinos&date=2011-07-22&group=$groupid");
        $jsonobj = json_decode($content, true);
        $num_tests = count($jsonobj['tests']);
        if ($num_tests !== 11) {
            $this->fail("Expected 11 failing tests for Trilinos, found $num_tests");
        }

        // Verify filters.
        $content = $this->connect($this->url . "/api/v1/testOverview.php?project=Trilinos&date=2011-07-22&group=$groupid&filtercount=1&showfilters=1&field1=buildname&compare1=63&value1=TEST_BUILD");
        $jsonobj = json_decode($content, true);
        $num_tests = count($jsonobj['tests']);
        if ($num_tests !== 10) {
            $this->fail("Expected 10 failing tests for TEST_BUILD, found $num_tests");
        }

        $content = $this->connect($this->url . "/api/v1/testOverview.php?project=Trilinos&date=2011-07-22&group=$groupid&filtercount=1&showfilters=1&field1=subproject&compare1=63&value1=Sacado");
        $jsonobj = json_decode($content, true);
        $num_tests = count($jsonobj['tests']);
        if ($num_tests !== 1) {
            $this->fail("Expected 1 failing test for Sacado, found $num_tests");
        }

        $content = $this->connect($this->url . "/api/v1/testOverview.php?project=Trilinos&date=2011-07-22&group=$groupid&filtercount=1&showfilters=1&field1=testname&compare1=63&value1=DepTests");
        $jsonobj = json_decode($content, true);
        $num_tests = count($jsonobj['tests']);
        if ($num_tests !== 4) {
            $this->fail("Expected 4 failing tests for DepTests, found $num_tests");
        }

        $this->pass('Passed');
    }
}
