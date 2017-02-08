<?php
/**
 * Created by PhpStorm.
 * User: bryonbean
 * Date: 2/6/17
 * Time: 12:32 PM
 */

require_once dirname(__FILE__) . '/cdash_test_case.php';

class TimeoutsAndMissingTestsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testMissingTestsSummarizedInEmail()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/TimeoutsAndMissingTests';
        $file = "{$rep}/5_test.xml";

        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }

        if (!$this->compareLog($this->logfilename, "{$rep}/cdash_5.log")) {
            return;
        }

        $this->pass('Passed');
    }
}