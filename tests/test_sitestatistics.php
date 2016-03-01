<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class SiteStatisticsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testSiteStatistics()
    {
        $this->login();
        $content = $this->get($this->url . '/siteStatistics.php');
        if (strpos($content, 'Busy time') === false) {
            $this->fail("'Busy time' not found on siteStatistics.php");
        }
        $this->pass('Passed');
    }
}
