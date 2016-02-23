<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');
require_once('include/common.php');
require_once('include/pdo.php');

class AggregateCoverageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testAggregateCoverage()
    {
        $files = array('release_case/Coverage.xml', 'release_case/CoverageLog-0.xml',
            'debug_case/Coverage.xml', 'debug_case/CoverageLog-0.xml');

        foreach ($files as $file) {
            if (!$this->submitTestingFile($file)) {
                $this->fail("Failed to submit $file");
                return 1;
            }
        }

        $this->get($this->url . "/api/v1/index.php?project=InsightExample&date=2016-02-16");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        $num_coverages = count($jsonobj['coverages']);
        if ($num_coverages != 3) {
            $this->fail("Expected 3 coverages, found $num_coverages");
            return 1;
        }

        $success = true;
        foreach ($jsonobj['coverages'] as $coverage) {
            $name = $coverage['buildname'];
            switch ($name) {
                case 'Aggregate Coverage':
                    $success &=
                        $this->checkCoverage($coverage, 18, 6, 75);
                    break;
                case 'release_coverage':
                    $success &=
                        $this->checkCoverage($coverage, 12, 12, 50);
                    break;
                case 'debug_coverage':
                    $success &=
                        $this->checkCoverage($coverage, 15, 9, 62.5);
                    break;
                default:
                    $this->fail("Unexpected coverage $name");
                    return 1;
            }
        }

        if ($success) {
            $this->pass('Tests passed');
            return 0;
        } else {
            return 1;
        }
    }

    public function checkCoverage($coverage, $expected_loctested,
        $expected_locuntested, $expected_percentage)
    {
        if ($coverage['loctested'] != $expected_loctested) {
            $this->fail("Expected " . $coverage['buildname'] . " loctested to be $expected_loctested, found " . $coverage['loctested']);
            return false;
        }
        if ($coverage['locuntested'] != $expected_locuntested) {
            $this->fail("Expected " . $coverage['buildname'] . " locuntested to be $expected_locuntested, found " . $coverage['locuntested']);
            return false;
        }
        if ($coverage['percentage'] != $expected_percentage) {
            $this->fail("Expected " . $coverage['buildname'] . " percentage to be $expected_percentage, found " . $coverage['percentage']);
            return false;
        }
        return true;
    }

    public function submitTestingFile($filename)
    {
        $file_to_submit =
          dirname(__FILE__)."/data/AggregateCoverage/".$filename;
        return $this->submission('InsightExample', $file_to_submit);
    }
}
