<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class MultipleSubprojectsTestCase extends KWWebTestCase
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

        if (!$this->submission('SubProjectExample', "$rep/Configure.xml")) {
            $this->fail('failed to submit Configure.xml');
            return 1;
        }

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
            "SELECT id FROM build WHERE name='CTestTest-Linux-c++-Subprojects'");
        while ($buildid_array = pdo_fetch_array($buildid_results)) {
            $buildids[] = $buildid_array['id'];
        }

        if (count($buildids) != 5) {
            foreach ($buildids as $id) {
                remove_build($id);
            }
            $this->fail('Expected 5 builds, found ' . count($buildids));
            return 1;
        }

        try {
            $success = true;

            $parentid = $buildids[0];
            // TODO: Add test for this -->
            //$this->get($this->url . "/api/v1/index.php?project=SubProjectExample&parentid=$parentid");
            $this->get($this->url . "/api/v1/index.php?project=SubProjectExample&date=2016-07-28");
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);

            $buildgroup = array_pop($jsonobj['buildgroups']);

            // Check number of subprojects
            //$numchildren = $buildgroup['numchildren'];
            //if ($numchildren != 4) {
            //    throw new Exception('Expected 4 children, found ' . $numchildren);
            //}

            // Check configure
            $numconfigureerror = $buildgroup['numconfigureerror'];
            if ($numconfigureerror != 1) {
                throw new Exception('Expected 1 configure error, found ' . $numconfigureerror);
            }

            $numconfigurewarning = $buildgroup['numconfigurewarning'];
            if ($numconfigurewarning != 1) {
                throw new Exception('Expected 1 configure warnings, found ' . $numconfigurewarning);
            }

            // Check builds
            $numbuilderror = $buildgroup['numbuilderror'];
            if ($numbuilderror != 2) {
                throw new Exception('Expected 2 build errors, found ' . $numbuilderror);
            }

            $numbuildwarning = $buildgroup['numbuildwarning'];
            if ($numbuildwarning != 2) {
                throw new Exception('Expected 2 build warnings, found ' . $numbuildwarning);
            }

            // Check tests
            $numtestpass = $buildgroup['numtestpass'];
            if ($numtestpass != 1) {
                throw new Exception('Expected 1 test to pass, found ' . $numtestpass);
            }

            $numtestfail = $buildgroup['numtestfail'];
            if ($numtestfail != 5) {
                throw new Exception('Expected 5 tests to fail, found ' . $numtestfail);
            }

            $numtestnotrun = $buildgroup['numtestnotrun'];
            if ($numtestnotrun != 1) {
                throw new Exception('Expected 1tests to not run, found ' . $numtestnotrun);
            }

            // viewConfigure
            $this->get($this->url . "/viewConfigure.php?buildid=$parentid");

            $content = $this->getBrowser()->getContent();
            if ($content == false) {
                throw new Exception("Error retrieving content from viewConfigure.php");
            }

            $subprojects = array("MyExperimentalFeature", "MyProductionCode", "MyThirdPartyDependency");
            foreach ($subprojects as $subproject) {
                $pattern = "#td style=\"vertical-align:top\">$subproject</td>#";
                if (preg_match($pattern, $content) == 1) {
                    throw new Exception('Subprojects should not be displayed on viewConfigure');
                }
            }

            // TODO: viewSubprojects
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
