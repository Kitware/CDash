<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class IndexFiltersTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->InsightUrl = $this->url . '/api/v1/index.php?project=InsightExample&date=2010-07-07';
        $this->EmailUrl = $this->url . '/api/v1/index.php?project=EmailProjectExample&date=2009-02-23';
    }

    public function testIndexFilters()
    {
        $this->filter('buildduration', 43, 293, 'Win32');
        $this->filter('buildduration', 43, '4m 53s', 'Win32');
        $this->filter('builderrors', 43, 1, 'Win32');
        $this->filter('buildwarnings', 43, 2, 'Win32');
        $this->filter('buildname', 63, 'Darwin', 'Darwin');
        $this->filter('configureduration', 43, 11, 'Win32');
        $this->filter('configureduration', 43, '11s', 'Win32');
        $this->filter('configureerrors', 43, 1, 'Win32');
        $this->filter('configurewarnings', 43, 1, 'Win32');
        $this->filter('site', 63, 'thurmite', 'Darwin');
        $this->filter('testsduration', 43, 17, 'Win32');
        $this->filter('testsduration', 43, '17s', 'Win32');
        $this->filter('testsfailed', 43, 2, 'Win32');
        $this->filter('testsnotrun', 43, 2, 'Win32');
        $this->filter('testspassed', 43, 2, 'Win32');
        $this->filter('updateduration', 43, 0.2, 'Win32');
        $this->filter('updateduration', 43, '12s', 'Win32');
        $this->filter('updatedfiles', 43, 3, 'Win32');
        $this->filter('revision', 63, '23a4125', 'Win32', $this->EmailUrl);
    }

    public function filter($field, $compare, $value, $expected, $url=null)
    {
        if (is_null($url)) {
            $url = $this->InsightUrl;
        }
        $filter_string = "filtercount=1&showfilters=1&field1=$field&compare1=$compare&value1=$value";
        $this->get($url . "&$filter_string");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);

        // Verify that there's only one build.
        $num_builds = count($buildgroup['builds']);
        if ($num_builds != 1) {
            $this->fail("Expected 1 build, found $num_builds for $field");
            return false;
        }

        // ...and that it's the right build.
        $buildname = $buildgroup['builds'][0]['buildname'];
        if (strpos($buildname, $expected) === false) {
            $this->fail("Expected $expected to survive $field filter, instead got $buildname");
            return false;
        }
    }
}
