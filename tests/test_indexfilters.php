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
        $this->win32 = 'zApps-Win32-vs60';
        $this->win64 = 'zApp-Win64-Vista-vs9-Release';
        $this->mac = 'zApps-Darwin-g++-4.0.1';
    }

    public function testIndexFilters()
    {
        $this->filter('buildduration', 43, 14.7, $this->win64);
        $this->filter('builderrors', 43, 1, $this->win32);
        $this->filter('buildwarnings', 43, 2, $this->win32);
        $this->filter('buildname', 63, 'Darwin', $this->mac);
        $this->filter('configureduration', 43, 11, $this->win32);
        $this->filter('configureerrors', 43, 1, $this->win32);
        $this->filter('configurewarnings', 43, 1, $this->win32);
        $this->filter('site', 63, 'thurmite', $this->mac);
        $this->filter('testsduration', 43, 17, $this->win32);
        $this->filter('testsfailed', 43, 2, $this->win32);
        $this->filter('testsnotrun', 43, 2, $this->win32);
        $this->filter('testspassed', 43, 2, $this->win32);
        $this->filter('updateduration', 43, 0.2, $this->win32);
        $this->filter('updatedfiles', 43, 3, $this->win32);
    }

    public function filter($field, $compare, $value, $expected)
    {
        $filter_string = "filtercount=1&showfilters=1&field1=$field&compare1=$compare&value1=$value";
        $this->get($this->url . "/api/v1/index.php?project=InsightExample&date=2010-07-07&$filter_string");
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
        if ($buildname !== $expected) {
            $this->fail("Expected $expected to survive $field filter, instead got $buildname");
            return false;
        }
    }
}
