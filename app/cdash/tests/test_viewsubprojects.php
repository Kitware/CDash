<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ViewSubProjectsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testViewSubProjects()
    {
        $this->get($this->url . '/api/v1/viewSubProjects.php?project=Trilinos&date=2011-07-22');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        if (is_null($jsonobj)) {
            $this->fail("API response could be not be decoded to JSON:\n$content");
            return 1;
        }

        // Verify a SubProject that submitted recently and one that did not.
        $found_trilinos_framework = false;
        $found_zoltan = false;
        foreach ($jsonobj['subprojects'] as $subproject) {
            if ($subproject['name'] === 'TrilinosFramework') {
                $found_trilinos_framework = true;
                $expected_values = array(
                        'nbuilderror' => 0,
                        'nbuildwarning' => 0,
                        'nbuildpass' => 1,
                        'nconfigureerror' => 0,
                        'nconfigurewarning' => 2,
                        'nconfigurepass' => 0,
                        'ntestpass' => 90,
                        'ntestfail' => 30,
                        'ntestnotrun' => 0,
                        'starttime' => '2011-07-22 11:15:59');
                foreach ($expected_values as $k => $v) {
                    if ($subproject[$k] !== $v) {
                        $this->fail("Expected $v for TrilinosFramework $k, found " . $subproject[$k]);
                    }
                }
            } elseif ($subproject['name'] === 'Zoltan') {
                $found_zoltan = true;
                if ($subproject['starttime'] !== 'NA') {
                    $this->fail("Expected NA for Zoltan starttime, found " . $subproject['starttime']);
                }
            }
        }

        if (!$found_trilinos_framework) {
            $this->fail("Did not find TrilinosFramework");
        }
        if (!$found_zoltan) {
            $this->fail("Did not find Zoltan");
        }
    }
}
