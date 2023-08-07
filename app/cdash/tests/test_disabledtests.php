<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';



class DisabledTestsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testDisabledTests()
    {
        $this->deleteLog($this->logfilename);

        // Submit testing data.
        $rep = dirname(__FILE__) . '/data/DisabledTests';
        $file = "$rep/Test.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }

        // Find the buildid we just created.
        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->query("SELECT id FROM build WHERE name = 'test_disabled'");
        $row = $stmt->fetch();
        $buildid = $row['id'];
        if ($buildid < 1) {
            $this->fail('No buildid found when expected');
        }

        // Verify one disabled test and one test missing its executable.
        $this->get("$this->url/api/v1/viewTest.php?buildid=$buildid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        if ($jsonobj['numFailed'] !== 1) {
            $this->fail("Did not find 1 'Failed' tests when expected");
        }
        if ($jsonobj['numNotRun'] !== 1) {
            $this->fail("Did not find 1 'NotRun' tests when expected");
        }

        $verified_disabled = false;
        $verified_missingexe = false;
        foreach ($jsonobj['tests'] as $test) {
            if ($test['details'] === 'Disabled') {
                $verified_disabled = true;
            }
            if ($test['details'] === 'Unable to find executable') {
                $verified_missingexe = true;
            }
        }
        if (!$verified_disabled) {
            $this->fail('Did not find Disabled test');
        }
        if (!$verified_missingexe) {
            $this->fail('Did not find test with missing executable');
        }

        // Verify email was sent for the missing exe but not for the disabled test.
        $log_contents = file_get_contents($this->logfilename);
        if (strpos($log_contents, 'ThisTestFails') === false) {
            $this->fail("No email sent for test 'ThisTestFails'");
        }
        if (strpos($log_contents, 'ThisTestIsDisabled') !== false) {
            $this->fail("Erroneous email sent for test 'ThisTestIsDisabled'");
        }

        remove_build($buildid);
    }
}
