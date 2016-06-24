<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class UppdateAppendTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->OriginalConfigSettings = '';
    }

    public function testMultipleSubprojects()
    {
        // Submit our test data.
        $rep = dirname(__FILE__) . '/data/MultipleSubprojects';
        if (!$this->submission('SubProjectExample', "$rep/Project.xml")) {
            return;
        }

        //if (!$this->submission('SubProjectExample', "$rep/Configure.xml")) {
        //    $this->fail('failed to submit Configure.xml');
        //    return 1;
        //}

        if (!$this->submission('SubProjectExample', "$rep/Build.xml")) {
            $this->fail('failed to submit Build.xml');
            return 1;
        }

        if (!$this->submission('SubProjectExample', "$rep/Test.xml")) {
            $this->fail('failed to submit Test.xml');
            return 1;
        }

        // Get the buildids that we just created so we can delete it later.
        $buildids = array();
        $buildid_results = pdo_query(
            "SELECT id FROM build WHERE name='multiple_subproject_example'");
        while ($buildid_array = pdo_fetch_array($buildid_results)) {
            $buildids[] = $buildid_array['id'];
        }

        if (count($buildids) != 4) {
            foreach ($buildids as $id) {
                remove_build($id);
            }
            $this->fail('Expected 4 builds, found ' . count($buildids));
            return 1;
        }

        try {
            $success = true;

            $parentid = $buildids[0];
            $this->get($this->url . "/api/v1/index.php?project=SubProjectExample&parentid=$parentid");
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);
            $buildgroup = array_pop($jsonobj['buildgroups']);

            $numchildren = $jsonobj['numchildren'];
            if ($numchildren != 3) {
                throw new Exception('Expected 3 children, found ' . $numchildren);
            }

            $builds = $buildgroup['builds'];

            $numtestpass = $buildgroup['numtestpass'];
            if ($numtestpass != 2) {
                throw new Exception('Expected 2 tests to pass, found ' . $numtestpass);
            }

            $numtestfail = $buildgroup['numtestfail'];
            if ($numtestfail != 5) {
                throw new Exception('Expected 5 tests to fail, found ' . $numtestfail);
            }
        } catch (Exception $e) {
            $success = false;
            $error_message = $e->getMessage();
        }

        // Delete the builds
        foreach ($buildids as $buildid) {
            remove_build($buildid);
        }

        // Remove extra subprojects
        $rep = dirname(__FILE__) . '/data/SubProjectExample';
        $file = "$rep/Project_1.xml";
        if (!$this->submission('SubProjectExample', $file)) {
            $this->fail('failed to submit Project_1.xml');
            return 1;
        }

        if ($success) {
            $this->pass('Test passed');
            return 0;
        } else {
            $this->fail($error_message);
            return 1;
        }
    }
}
