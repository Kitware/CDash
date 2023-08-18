<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class UserStatisticsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testUserStatistics()
    {
        $this->login();
        $this->get($this->url . '/userStatistics.php?projectid=1');

        // No project selected
        $this->get($this->url . '/userStatistics.php');

        // Cover all date ranges
        $this->post($this->url . '/userStatistics.php?projectid=1', ['range' => 'lastweek']);
        $this->post($this->url . '/userStatistics.php?projectid=1', ['range' => 'thismonth']);
        $this->post($this->url . '/userStatistics.php?projectid=1', ['range' => 'lastmonth']);
        $this->post($this->url . '/userStatistics.php?projectid=1', ['range' => 'thisyear']);

        // Cover no user id case
        $this->logout();
        $this->get($this->url . '/userStatistics.php');
    }
}
