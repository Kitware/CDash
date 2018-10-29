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
    const EMAIL_NORMAL = 0;
    const EMAIL_SUMMARY = 1;

    private $buildIds;
    private $dataDir;
    private $projectId;
    private $summaryEmail;
    private $emailMaxChars;
    private $tearDown = true;

    public function __construct()
    {
        parent::__construct();
        $this->OriginalConfigSettings = '';
        $this->dataDir = dirname(__FILE__) . '/data/MultipleSubprojects';
    }

    public function setUp()
    {
        parent::setUp();
        if (!$this->tearDown) {
            $this->restoreState();
        }

        $pdo = get_link_identifier()->getPdo();
        $sql = "SELECT id FROM project WHERE name='SubProjectExample'";
        $stmt = $pdo->query($sql, PDO::FETCH_COLUMN, 0);
        $this->projectId = $stmt->fetchColumn();
    }

    public function tearDown()
    {
        if ($this->tearDown) {
            $this->restoreState();
        }

        parent::tearDown();
    }

    private function submitBuild()
    {
        if (!$this->tearDown) {
            $this->restoreState();
        }

        $this->deleteLog($this->logfilename);

        $this->buildIds = [];

        if (!$this->submission('SubProjectExample', "{$this->dataDir}/Project.xml")) {
            return 1;
        }

        if (!$this->submission('SubProjectExample', "{$this->dataDir}/Configure.xml")) {
            $this->fail('failed to submit Configure.xml');
            return 1;
        }

        if (!$this->submission('SubProjectExample', "{$this->dataDir}/Build.xml")) {
            $this->fail('failed to submit Build.xml');
            return 1;
        }

        if (!$this->submission('SubProjectExample', "{$this->dataDir}/Coverage.xml")) {
            $this->fail('failed to submit Coverage.xml');
            return 1;
        }

        if (!$this->submission('SubProjectExample', "{$this->dataDir}/CoverageLog.xml")) {
            $this->fail('failed to submit CoverageLog.xml');
            return 1;
        }

        if (!$this->submission('SubProjectExample', "{$this->dataDir}/DynamicAnalysis.xml")) {
            $this->fail('failed to submit DynamicAnalysis.xml');
            return 1;
        }

        if (!$this->submission('SubProjectExample', "{$this->dataDir}/Test.xml")) {
            $this->fail('failed to submit Test.xml');
            return 1;
        }

        if (!$this->submission('SubProjectExample', "{$this->dataDir}/Notes.xml")) {
            $this->fail('failed to submit Notes.xml');
            return 1;
        }

        if (!$this->submission('SubProjectExample', "{$this->dataDir}/Upload.xml")) {
            $this->fail('failed to submit Upload.xml');
            return 1;
        }

        // Get the buildids that we just created so we can delete it later.
        $pdo = get_link_identifier()->getPdo();
        $build_results = $pdo->query(
            "SELECT id, buildduration, configureduration FROM build
            WHERE name='CTestTest-Linux-c++-Subprojects'");
        while ($build_array = $build_results->fetch()) {
            $this->buildIds[] = $build_array['id'];
            $build_duration = $build_array['buildduration'];
            if ($build_duration != 5) {
                $this->fail("Expected 5 but found $build_duration for {$build_array['id']}'s build duration");
            }
            $configure_duration = $build_array['configureduration'];
            if ($configure_duration != 1) {
                $this->fail("Expected 5 but found $configure_duration for {$build_array['id']}'s configure duration");
            }
        }

        $total_builds = count($this->buildIds);

        if ($total_builds != 5) {    // parent + 4 subprojects
            foreach ($this->buildIds as $id) {
                remove_build($id);
            }
            $this->fail("Expected 5 Builds found {$total_builds}");
            return 1;
        }
    }

    private function restoreState()
    {
        remove_build($this->buildIds);

        $this->restoreEmailPreference();

        // Remove extra subprojects
        $rep = dirname(__FILE__) . '/data/SubProjectExample';
        $file = "$rep/Project_1.xml";
        if (!$this->submission('SubProjectExample', $file)) {
            $this->fail('failed to submit Project_1.xml');
            return 1;
        }
    }

    private function setEmailPreference($status, $chars)
    {
        $pdo = get_link_identifier()->getPdo();
        $success = false;

        $sql = "
            SELECT
              a.summaryemail,
              b.emailmaxchars
            FROM buildgroup a
              JOIN  project b ON a.projectid = b.id
            WHERE
            a.name = 'Experimental'
            AND a.projectid={$this->projectId}
        ";

        $stmt = $pdo->query($sql);
        list($this->summaryEmail, $this->emailMaxChars) = $stmt->fetch();

        $sql = "
            UPDATE buildgroup
            SET summaryemail = {$status}
            WHERE
                name = 'Experimental'
            AND projectid = {$this->projectId}
        ";

        $success = (bool) $pdo->exec($sql);

        $sql = "
            UPDATE project
            SET emailmaxchars = {$chars}
            WHERE id = {$this->projectId}
        ";

        return (bool) $pdo->exec($sql) && $success;
    }

    private function restoreEmailPreference()
    {
        $pdo = get_link_identifier()->getPdo();

        if ($this->summaryEmail) {
            $sql = "
                UPDATE buildgroup
                SET summaryemail = {$this->summaryEmail}
                WHERE
                    name = 'Experimental'
                AND projectid = {$this->projectId}
            ";

            // the non-type testing here is intentional, throw exception if return value is
            // either false or zero (0).
            if ($pdo->exec($sql) == false) {
                throw new Exception('Failed to restore email summary');
            }
            $this->summaryEmail = null;
        }

        if ($this->emailMaxChars) {
            $sql = "
                UPDATE project
                SET emailmaxchars = {$this->emailMaxChars}
                WHERE id = {$this->projectId}
            ";

            // the non-type testing here is intentional, throw exception if return value is
            // either false or zero (0).
            if ($pdo->exec($sql) == false) {
                throw new Exception('Failed to restore project\'s emailmaxchars');
            }
            $this->emailMaxChars = null;
        }
    }

    public function testMultipleSubprojects()
    {
        // Get the buildids that we just created so we can delete it later.
        $this->submitBuild();

        $pdo = get_link_identifier()->getPdo();
        $parentid = null;

        try {
            $success = true;
            $subprojects = array("MyThirdPartyDependency", "MyExperimentalFeature", "MyProductionCode", "EmptySubproject");

            //
            $this->get($this->url . "/api/v1/index.php?project=SubProjectExample&date=2016-07-28");
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);

            $buildgroup = array_pop($jsonobj['buildgroups']);
            $builds = $buildgroup['builds'];

            // Check number of parent builds
            $num_builds = count($builds);
            if ($num_builds !== 1) {
                throw new Exception("Expected 1 parent build, found {$num_builds}");
            }

            // Get parent id
            $parentid = $builds[0]['id'];
            if (empty($parentid) || $parentid < 1) {
                throw new Exception("No parentid found when expected");
            }

            // Check number of children builds
            $num_children = $builds[0]['numchildren'];
            if ($num_children != 4) {
                throw new Exception("Expected 4 children, found {$num_children}");
            }

            // Check configure
            $numconfigureerror = $buildgroup['numconfigureerror'];
            if ($numconfigureerror != 1) {
                throw new Exception("Expected 1 configure error, found {$numconfigureerror}");
            }

            $numconfigurewarning = $buildgroup['numconfigurewarning'];
            if ($numconfigurewarning != 1) {
                throw new Exception("Expected 1 configure warnings, found {$numconfigurewarning}");
            }

            // Check builds
            $numbuilderror = $buildgroup['numbuilderror'];
            if ($numbuilderror != 2) {
                throw new Exception("Expected 2 build errors, found {$numbuilderror}");
            }

            $numbuildwarning = $buildgroup['numbuildwarning'];
            if ($numbuildwarning != 2) {
                throw new Exception("Expected 2 build warnings, found {$numbuildwarning}");
            }

            // Check tests
            $numtestpass = $buildgroup['numtestpass'];
            if ($numtestpass != 1) {
                throw new Exception("Expected 1 test to pass, found {$numtestpass}");
            }

            $numtestfail = $buildgroup['numtestfail'];
            if ($numtestfail != 5) {
                throw new Exception("Expected 5 tests to fail, found {$numtestfail}");
            }

            $numtestnotrun = $buildgroup['numtestnotrun'];
            if ($numtestnotrun != 1) {
                throw new Exception("Expected 1 test not run, found {$numtestnotrun}");
            }

            // Check coverage
            $numcoverages = count($jsonobj['coverages']);
            if ($numcoverages != 1) {
                throw new Exception("Expected 1 coverage build, found {$numcoverages}");
            }
            $cov = $jsonobj['coverages'][0];
            $percent = $cov['percentage'];
            if ($percent != 70) {
                throw new Exception("Expected 70% coverage, found {$percent}");
            }
            $loctested = $cov['loctested'];
            if ($loctested != 14) {
                throw new Exception("Expected 14 LOC tested, found {$loctested}");
            }
            $locuntested = $cov['locuntested'];
            if ($locuntested != 6) {
                throw new Exception("Expected 6 LOC untested, found {$locuntested}");
            }

            // Check dynamic analysis.
            $numdynamicanalyses = count($jsonobj['dynamicanalyses']);
            if ($numdynamicanalyses != 1) {
                throw new Exception("Expected 1 DA build, found {$numdynamicanalyses}");
            }
            $DA = $jsonobj['dynamicanalyses'][0];
            $defectcount = $DA['defectcount'];
            if ($defectcount != 3) {
                throw new Exception("Expected 3 DA defects, found {$defectcount}");
            }

            // View parent build
            $this->get("{$this->url}/api/v1/index.php?project=SubProjectExample&parentid={$parentid}");
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);

            $num_children = $jsonobj['numchildren'];
            if ($num_children != 4) {
                throw new Exception("Expected 4 subprojects, found {$num_children}");
            }

            if ($jsonobj['parenthasnotes'] !== true) {
                throw new Exception("parenthasnotes not set to true when expected");
            }

            $num_uploaded_files = $jsonobj['uploadfilecount'];
            if ($num_uploaded_files  !== 1) {
                throw new Exception("Expected 1 uploaded file, found $num_uploaded_files");
            }

            $numcoverages = count($jsonobj['coverages']);
            if ($numcoverages != 2) {
                throw new Exception("Expected 2 subproject coverages, found {$numcoverages}");
            }

            $numdynamicanalyses = count($jsonobj['dynamicanalyses']);
            if ($numdynamicanalyses != 3) {
                throw new Exception("Expected 3 subproject dynamic analyses, found {$numdynamicanalyses}");
            }

            if ($jsonobj['updateduration'] !== false) {
                throw new Exception("Expected updateduration to be false, found {$jsonobj['updateduration']}");
            }
            if ($jsonobj['configureduration'] != '1s') {
                throw new Exception("Expected configureduration to be 1s, found {$jsonobj['configureduration']}");
            }
            if ($jsonobj['buildduration'] != '5s') {
                throw new Exception("Expected buildduration to be 5s, found {$jsonobj['buildduration']}");
            }
            if ($jsonobj['testduration'] != '4s') {
                throw new Exception("Expected testduration to be 4s, found {$jsonobj['testduration']}");
            }

            $buildgroup = array_pop($jsonobj['buildgroups']);
            $builds = $buildgroup['builds'];

            foreach ($builds as $build) {
                $label = $build['label'];
                $index = array_search($label, $subprojects);
                if ($index === false) {
                    throw new Exception("Invalid label ($label)!");
                }
                $index += 1;
                if ($build['position'] !== $index) {
                    throw new Exception("Expected {$index} but found ${build['position']} for {$label} position");
                }
            }

            // viewConfigure
            $this->get("{$this->url}/viewConfigure.php?buildid={$parentid}");

            $content = $this->getBrowser()->getContent();
            if ($content == false) {
                throw new Exception("Error retrieving content from viewConfigure.php");
            }

            foreach ($subprojects as $subproject) {
                $pattern = "#td style=\"vertical-align:top\">$subproject</td>#";
                if (preg_match($pattern, $content) == 1) {
                    throw new Exception('Subprojects should not be displayed on viewConfigure');
                }
            }

            // viewSubProjects
            $this->get("{$this->url}/api/v1/viewSubProjects.php?project=SubProjectExample&date=2016-07-28");
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);

            if (!array_key_exists('project', $jsonobj)) {
                throw new Exception('No project found on viewSubProjects.php');
            }

            // Check top-level project results.
            $project = $jsonobj['project'];
            $project_expected = array(
                'nbuilderror' => 1,
                'nbuildwarning' => 1,
                'nbuildpass' => 0,
                'nconfigureerror' => 1,
                'nconfigurewarning' => 1,
                'nconfigurepass' => 0,
                'ntestpass' => 1,
                'ntestfail' => 5,       // Total number of tests failed
                'ntestnotrun' => 1);
            foreach ($project_expected as $key => $expected) {
                $found = $project[$key];
                if ($found !== $expected) {
                    throw new Exception("Expected $key to be $expected, found $found");
                }
            }

            // Check results for each individual SubProject.
            $subprojects_expected = array(
                'MyThirdPartyDependency' => array(
                    'nbuilderror' => 1,
                    'nbuildwarning' => 0,
                    'nbuildpass' => 0,
                    'nconfigureerror' => 1,
                    'nconfigurewarning' => 1,
                    'nconfigurepass' => 0,
                    'ntestpass' => 0,
                    'ntestfail' => 0,
                    'ntestnotrun' => 1),
                'MyExperimentalFeature' => array(
                    'nbuilderror' => 0,
                    'nbuildwarning' => 1,
                    'nbuildpass' => 0,
                    'nconfigureerror' => 1,
                    'nconfigurewarning' => 1,
                    'nconfigurepass' => 0,
                    'ntestpass' => 0,
                    'ntestfail' => 5,
                    'ntestnotrun' => 0),
                'MyProductionCode' => array(
                    'nbuilderror' => 0,
                    'nbuildwarning' => 1,
                    'nbuildpass' => 0,
                    'nconfigureerror' => 1,
                    'nconfigurewarning' => 1,
                    'nconfigurepass' => 0,
                    'ntestpass' => 1,
                    'ntestfail' => 0,
                    'ntestnotrun' => 0));
            foreach ($jsonobj['subprojects'] as $subproj) {
                $subproj_name = $subproj['name'];
                if (!array_key_exists($subproj_name, $subprojects_expected)) {
                    continue;
                }
                foreach ($subprojects_expected[$subproj_name] as $key => $expected) {
                    $found = $subproj[$key];
                    if ($found !== $expected) {
                        throw new Exception("Expected {$key} to be {$expected} for {$subproj_name}, found {$found}");
                    }
                }
            }

            foreach ($builds as $build) {
                // Verify that dynamic analysis data was correctly split across SubProjects.
                $stmt = $pdo->query("SELECT numdefects FROM dynamicanalysissummary WHERE buildid = {$build['id']}");
                $summary_total = $stmt->fetchColumn();
                $this->get($this->url . "/api/v1/viewDynamicAnalysis.php?buildid={$build['id']}");
                $content = $this->getBrowser()->getContent();
                $jsonobj = json_decode($content, true);
                $expected_defect_type = null;
                switch ($build['label']) {
                    case 'MyExperimentalFeature':
                        $expected_num_analyses = 1;
                        $expected_num_defect_types = 1;
                        $expected_num_defects = 1;
                        $expected_defect_type = 'Invalid Pointer Write';
                        break;
                    case 'MyProductionCode':
                        $expected_num_analyses = 1;
                        $expected_num_defect_types = 0;
                        $expected_num_defects = 0;
                        break;
                    case 'MyThirdPartyDependency':
                        $expected_num_analyses = 1;
                        $expected_num_defect_types = 1;
                        $expected_num_defects = 2;
                        $expected_defect_type = 'Memory Leak';
                        break;
                    case 'EmptySubproject':
                        $expected_num_analyses = 0;
                        $expected_num_defect_types = 0;
                        $expected_num_defects = 0;
                        break;
                }
                $num_analyses = count($jsonobj['dynamicanalyses']);
                if ($num_analyses != $expected_num_analyses) {
                    throw new Exception("Expected {$expected_num_analyses} analyses for {$build['label']}, found {$num_analyses}");
                }
                if ($expected_num_analyses > 0) {
                    if ($summary_total != $expected_num_defects) {
                        throw new Exception("Expected {$expected_num_defects} defects for {$build['label']} but summary reports {$summary_total}");
                    }
                }
                $num_defect_types = count($jsonobj['defecttypes']);
                if ($num_defect_types != $expected_num_defect_types) {
                    throw new Exception("Expected {$expected_num_defect_types} type of defect for {$build['label']}, found {$num_defect_types}");
                }
                if ($expected_num_defects > 0) {
                    $num_defects = $jsonobj['dynamicanalyses'][0]['defects'][0];
                    if ($num_defects != $expected_num_defects) {
                        throw new Exception("Expected {$expected_num_defects} defects for {$build['label']}, found {$num_defects}");
                    }
                    $defect_type = $jsonobj['defecttypes'][0]['type'];
                    if ($expected_defect_type != $defect_type) {
                        throw new Exception("Expected type {$expected_defect_type} for {$build['label']}, found {$defect_type}");
                    }
                }

                // Verify that test duration is calculated correctly.
                $stmt = $pdo->query("SELECT time FROM buildtesttime WHERE buildid = {$build['id']}");
                $found = $stmt->fetchColumn();
                if ($found !== false && $found != 4) {
                    throw new Exception("Expected 4 but found $found for {$build['id']}'s test duration");
                }
            }

            // Verify that 'Back' links to the parent build.
            $pages = [
                'buildSummary.php',
                'viewBuildError.php',
                'viewDynamicAnalysis.php',
                'viewNotes.php',
                'viewTest.php'
            ];
            $child_buildid = $builds[0]['id'];

            foreach ($pages as $page) {
                $this->get("{$this->url}/api/v1/{$page}?buildid={$child_buildid}");
                $content = $this->getBrowser()->getContent();
                $jsonobj = json_decode($content, true);
                $expected = "index.php?project=SubProjectExample&parentid={$parentid}";
                $found = $jsonobj['menu']['back'];
                if (strpos($found, $expected) === false) {
                    throw new Exception("{$expected} not found in back link for {$page} ({$found})");
                }
            }
        } catch (Exception $e) {
            $success = false;
            $error_message = $e->getMessage();
        }

        // Test changing subproject order.
        if (!$this->submission('SubProjectExample', "{$this->dataDir}/Project_2.xml")) {
            $this->fail("failed to submit Project_2.xml");
            return;
        }
        $new_order = ["MyProductionCode", "MyExperimentalFeature", "EmptySubproject", "MyThirdPartyDependency"];
        $this->get("{$this->url}/api/v1/index.php?project=SubProjectExample&parentid={$parentid}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $builds = $buildgroup['builds'];
        foreach ($builds as $build) {
            $label = $build['label'];
            $index = array_search($label, $new_order);
            if ($index === false) {
                $success = false;
                $error_message = "Invalid label ({$label})!";
            }
            $index += 1;
            if ($build['position'] !== $index) {
                $success = false;
                $error_message = "Expected {$index} but found ${build['position']} for {$label} position";
            }
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
