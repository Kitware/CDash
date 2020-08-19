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

        if (!$pdo->exec($sql)) {
            $this->fail("Query failed: $sql");
        }

        $sql = "
            UPDATE project
            SET emailmaxchars = {$chars}
            WHERE id = {$this->projectId}
        ";

        if (!$pdo->exec($sql)) {
            $this->fail("Query failed: $sql");
        }
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
                $this->fail('Failed to restore email summary');
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
                $this->fail('Failed to restore project\'s emailmaxchars');
            }
            $this->emailMaxChars = null;
        }
    }

    private function verifyBuild($expected, $actual, $name)
    {
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual)) {
                $this->fail("$name is missing $key");
            } else {
                if (is_array($expected[$key])) {
                    $this->verifyBuild($expected[$key], $actual[$key], $name);
                } elseif ($actual[$key] != $value) {
                    $this->fail("Expected $value but found " . $actual[$key] . " for $name::$key");
                }
            }
        }
    }

    public function testMultipleSubprojects()
    {
        // Get the buildids that we just created so we can delete it later.
        $this->submitBuild();

        $pdo = get_link_identifier()->getPdo();
        $parentid = null;

        $subprojects = array("MyThirdPartyDependency", "MyExperimentalFeature", "MyProductionCode", "EmptySubproject");

        // Check index.php for this date.
        $this->get($this->url . "/api/v1/index.php?project=SubProjectExample&date=2016-07-28");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        $buildgroup = array_pop($jsonobj['buildgroups']);
        $builds = $buildgroup['builds'];

        // Check number of parent builds
        $num_builds = count($builds);
        if ($num_builds !== 1) {
            $this->fail("Expected 1 parent build, found {$num_builds}");
        }

        // Get parent id
        $parentid = $builds[0]['id'];
        if (empty($parentid) || $parentid < 1) {
            $this->fail("No parentid found when expected");
        }

        // Check number of children builds
        $num_children = $builds[0]['numchildren'];
        if ($num_children != 4) {
            $this->fail("Expected 4 children, found {$num_children}");
        }

        // Check configure
        $numconfigureerror = $buildgroup['numconfigureerror'];
        if ($numconfigureerror != 1) {
            $this->fail("Expected 1 configure error, found {$numconfigureerror}");
        }

        $numconfigurewarning = $buildgroup['numconfigurewarning'];
        if ($numconfigurewarning != 1) {
            $this->fail("Expected 1 configure warnings, found {$numconfigurewarning}");
        }

        // Check builds
        $numbuilderror = $buildgroup['numbuilderror'];
        if ($numbuilderror != 2) {
            $this->fail("Expected 2 build errors, found {$numbuilderror}");
        }

        $numbuildwarning = $buildgroup['numbuildwarning'];
        if ($numbuildwarning != 2) {
            $this->fail("Expected 2 build warnings, found {$numbuildwarning}");
        }

        // Check tests
        $numtestpass = $buildgroup['numtestpass'];
        if ($numtestpass != 1) {
            $this->fail("Expected 1 test to pass, found {$numtestpass}");
        }

        $numtestfail = $buildgroup['numtestfail'];
        if ($numtestfail != 5) {
            $this->fail("Expected 5 tests to fail, found {$numtestfail}");
        }

        $numtestnotrun = $buildgroup['numtestnotrun'];
        if ($numtestnotrun != 1) {
            $this->fail("Expected 1 test not run, found {$numtestnotrun}");
        }

        // Check coverage
        $numcoverages = count($jsonobj['coverages']);
        if ($numcoverages != 1) {
            $this->fail("Expected 1 coverage build, found {$numcoverages}");
        }
        $cov = $jsonobj['coverages'][0];
        $percent = $cov['percentage'];
        if ($percent != 70) {
            $this->fail("Expected 70% coverage, found {$percent}");
        }
        $loctested = $cov['loctested'];
        if ($loctested != 14) {
            $this->fail("Expected 14 LOC tested, found {$loctested}");
        }
        $locuntested = $cov['locuntested'];
        if ($locuntested != 6) {
            $this->fail("Expected 6 LOC untested, found {$locuntested}");
        }

        // Check dynamic analysis.
        $numdynamicanalyses = count($jsonobj['dynamicanalyses']);
        if ($numdynamicanalyses != 1) {
            $this->fail("Expected 1 DA build, found {$numdynamicanalyses}");
        }
        $DA = $jsonobj['dynamicanalyses'][0];
        $defectcount = $DA['defectcount'];
        if ($defectcount != 3) {
            $this->fail("Expected 3 DA defects, found {$defectcount}");
        }

        // Verify filtered parent build.
        // ...exclude
        $this->get("{$this->url}/api/v1/index.php?project=SubProjectExample&&date=2016-07-28&filtercount=1&showfilters=1&field1=subprojects&compare1=92&value1=MyThirdPartyDependency");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $build = $buildgroup['builds'][0];
        $expected_parent_build = [
            'label' => '(3 labels)',
            'timefull' => 6,
            'compilation' => [
                'error' => 0,
                'warning' => 2,
                'timefull' => 5,
            ],
            'configure' => [
                'error' => 1,
                'warning' => 1,
                'timefull' => 1,
            ],
            'test' => [
                'notrun' => 0,
                'fail' => 5,
                'pass' => 1,
                'timefull' => 4,
            ],
        ];
        $this->verifyBuild($expected_parent_build, $build, 'parent exclude');

        // ...include
        $this->get("{$this->url}/api/v1/index.php?project=SubProjectExample&date=2016-07-28&filtercount=2&showfilters=1&filtercombine=and&field1=subprojects&compare1=93&value1=MyProductionCode&field2=subprojects&compare2=93&value2=MyThirdPartyDependency");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $build = $buildgroup['builds'][0];
        $expected_parent_build = [
            'label' => '(2 labels)',
            'timefull' => 6,
            'compilation' => [
                'error' => 2,
                'warning' => 1,
                'timefull' => 5,
            ],
            'configure' => [
                'error' => 1,
                'warning' => 1,
                'timefull' => 1,
            ],
            'test' => [
                'notrun' => 1,
                'fail' => 0,
                'pass' => 1,
                'timefull' => 4,
            ],
        ];
        $this->verifyBuild($expected_parent_build, $build, 'parent include');

        // View parent build
        $this->get("{$this->url}/api/v1/index.php?project=SubProjectExample&parentid={$parentid}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        $num_children = $jsonobj['numchildren'];
        if ($num_children != 4) {
            $this->fail("Expected 4 subprojects, found {$num_children}");
        }

        if ($jsonobj['parenthasnotes'] !== true) {
            $this->fail("parenthasnotes not set to true when expected");
        }

        $num_uploaded_files = $jsonobj['uploadfilecount'];
        if ($num_uploaded_files  !== 1) {
            $this->fail("Expected 1 uploaded file, found $num_uploaded_files");
        }

        $numcoverages = count($jsonobj['coverages']);
        if ($numcoverages != 2) {
            $this->fail("Expected 2 subproject coverages, found {$numcoverages}");
        }

        $numdynamicanalyses = count($jsonobj['dynamicanalyses']);
        if ($numdynamicanalyses != 3) {
            $this->fail("Expected 3 subproject dynamic analyses, found {$numdynamicanalyses}");
        }

        if ($jsonobj['updateduration'] !== false) {
            $this->fail("Expected updateduration to be false, found {$jsonobj['updateduration']}");
        }
        if ($jsonobj['configureduration'] != '1s') {
            $this->fail("Expected configureduration to be 1s, found {$jsonobj['configureduration']}");
        }
        if ($jsonobj['buildduration'] != '5s') {
            $this->fail("Expected buildduration to be 5s, found {$jsonobj['buildduration']}");
        }
        if ($jsonobj['testduration'] != '4s') {
            $this->fail("Expected testduration to be 4s, found {$jsonobj['testduration']}");
        }

        $expected_builds = [
            'MyExperimentalFeature' => [
                'timefull' => 6,
                'compilation' => [
                    'error' => 0,
                    'warning' => 1,
                    'timefull' => 5,
                ],
                'configure' => [
                    'error' => 1,
                    'warning' => 1,
                    'timefull' => 1,
                ],
                'test' => [
                    'notrun' => 0,
                    'fail' => 5,
                    'pass' => 0,
                    'timefull' => 4,
                ],
            ],
            'MyProductionCode' => [
                'timefull' => 6,
                'compilation' => [
                    'error' => 0,
                    'warning' => 1,
                    'timefull' => 5,
                ],
                'configure' => [
                    'error' => 1,
                    'warning' => 1,
                    'timefull' => 1,
                ],
                'test' => [
                    'notrun' => 0,
                    'fail' => 0,
                    'pass' => 1,
                    'timefull' => 4,
                ],
            ],
            'MyThirdPartyDependency' => [
                'timefull' => 6,
                'compilation' => [
                    'error' => 2,
                    'warning' => 0,
                    'timefull' => 5,
                ],
                'configure' => [
                    'error' => 1,
                    'warning' => 1,
                    'timefull' => 1,
                ],
                'test' => [
                    'notrun' => 1,
                    'fail' => 0,
                    'pass' => 0,
                    'timefull' => 4,
                ],
            ],
            'EmptySubproject' => [
                'timefull' => 6,
                'compilation' => [
                    'error' => 0,
                    'warning' => 0,
                    'timefull' => 5,
                ],
                'configure' => [
                    'error' => 1,
                    'warning' => 1,
                    'timefull' => 1,
                ],
                'test' => [
                    'notrun' => 0,
                    'fail' => 0,
                    'pass' => 0,
                    'timefull' => 4,
                ],
            ],
        ];

        $buildgroup = array_pop($jsonobj['buildgroups']);
        $child_builds = $buildgroup['builds'];
        $this->assertEqual(count($child_builds), 4);
        foreach ($child_builds as $build) {
            $label = $build['label'];
            if (!array_key_exists($label, $expected_builds)) {
                $this->fail("Unexpected label $label");
            }
            $index = array_search($label, $subprojects);
            if ($index === false) {
                $this->fail("Invalid label ($label)!");
            }
            $index += 1;
            if ($build['position'] !== $index) {
                $this->fail("Expected {$index} but found ${build['position']} for {$label} position");
            }

            $this->verifyBuild($expected_builds[$label], $build, $label);

            // Adding tests to ensure that labels associated with subprojects and tests were saved
            $sql = "
                SELECT text
                FROM label2test
                     JOIN label
                ON
                  id=labelid
                WHERE buildid=:buildid;
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':buildid', $build['id'], PDO::PARAM_INT);
            $stmt->execute();
            $rows = array_unique($stmt->fetchAll(PDO::FETCH_COLUMN, 'text'));

            $count = count($rows);

            $success = false;
            switch ($label) {
                case 'MyExperimentalFeature':
                    $success = $count === 1 && in_array('MyExperimentalFeature', $rows);
                    break;
                case 'MyProductionCode':
                    $success = $count === 1 && in_array('MyProductionCode', $rows);
                    break;
                case 'MyThirdPartyDependency':
                    $success = $count === 1 && in_array('MyThirdPartyDependency', $rows);
                    break;
                case 'EmptySubproject':
                    $success = $count === 0;
                    break;
                default:
                    $success = false;
            }

            if (!$success) {
                $error_message = 'Unexpected label associations';
                $error_message .= "\n{$build['label']}: count: {$count}: rows: " . implode(',', $rows);
                $this->fail($error_message);
            }
        }

        // Test include subprojects filter.
        $this->get("{$this->url}/api/v1/index.php?project=SubProjectExample&parentid={$parentid}&filtercount=1&showfilters=1&field1=subprojects&compare1=93&value1=MyExperimentalFeature");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $num_children = $jsonobj['numchildren'];
        if ($num_children != 1) {
            $this->fail("Expected 1 subproject, found {$num_children}");
        }
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $builds = $buildgroup['builds'];
        $build = $builds[0];
        $this->verifyBuild($expected_builds['MyExperimentalFeature'], $build, 'MyExperimentalFeature');

        // Test exclude subprojects filter.
        $this->get("{$this->url}/api/v1/index.php?project=SubProjectExample&parentid={$parentid}&filtercount=3&showfilters=1&filtercombine=and&field1=subprojects&compare1=92&value1=MyExperimentalFeature&field2=subprojects&compare2=92&value2=MyProductionCode&field3=subprojects&compare3=92&value3=EmptySubproject");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $num_children = $jsonobj['numchildren'];
        if ($num_children != 1) {
            $this->fail("Expected 1 subproject, found {$num_children}");
        }
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $builds = $buildgroup['builds'];
        $build = $builds[0];
        $this->verifyBuild($expected_builds['MyThirdPartyDependency'], $build, 'MyThirdPartyDependency');

        // viewConfigure
        $this->get("{$this->url}/build/{$parentid}/configure");

        $content = $this->getBrowser()->getContent();
        if ($content == false) {
            $this->fail("Error retrieving content from viewConfigure.php");
        }

        foreach ($subprojects as $subproject) {
            $pattern = "#td style=\"vertical-align:top\">$subproject</td>#";
            if (preg_match($pattern, $content) == 1) {
                $this->fail('Subprojects should not be displayed on viewConfigure');
            }
        }

        // viewSubProjects
        $this->get("{$this->url}/api/v1/viewSubProjects.php?project=SubProjectExample&date=2016-07-28");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        if (!array_key_exists('project', $jsonobj)) {
            $this->fail('No project found on viewSubProjects.php');
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
                $this->fail("Expected $key to be $expected, found $found");
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
                    $this->fail("Expected {$key} to be {$expected} for {$subproj_name}, found {$found}");
                }
            }
        }

        foreach ($child_builds as $build) {
            // Verify that dynamic analysis data was correctly split across SubProjects.
            $stmt = $pdo->query("SELECT numdefects FROM dynamicanalysissummary WHERE buildid = {$build['id']}");
            $summary_total = $stmt->fetchColumn();
            $this->get($this->url . "/api/v1/viewDynamicAnalysis.php?buildid={$build['id']}");
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);
            $expected_defect_type = null;
            $expected_log = '';
            $log_stmt = $pdo->prepare('SELECT log FROM dynamicanalysis WHERE buildid = :buildid');
            switch ($build['label']) {
                case 'MyExperimentalFeature':
                    $expected_num_analyses = 1;
                    $expected_num_defect_types = 1;
                    $expected_num_defects = 1;
                    $expected_defect_type = 'Invalid Pointer Write';
                    $expected_proc_time = 0.01;
                    $expected_log = 'heap block overrun time!';
                    break;
                case 'MyProductionCode':
                    $expected_num_analyses = 1;
                    $expected_num_defect_types = 0;
                    $expected_num_defects = 0;
                    $expected_proc_time = 0.0;
                    break;
                case 'MyThirdPartyDependency':
                    $expected_num_analyses = 1;
                    $expected_num_defect_types = 1;
                    $expected_num_defects = 2;
                    $expected_defect_type = 'Memory Leak';
                    $expected_proc_time = 0.0;
                    $expected_log = 'This function is third party code.  It leaks memory.';
                    break;
                case 'EmptySubproject':
                    $expected_num_analyses = 0;
                    $expected_num_defect_types = 0;
                    $expected_num_defects = 0;
                    $expected_proc_time = 0.0;
                    break;
            }
            $num_analyses = count($jsonobj['dynamicanalyses']);
            if ($num_analyses != $expected_num_analyses) {
                $this->fail("Expected {$expected_num_analyses} analyses for {$build['label']}, found {$num_analyses}");
            }
            if ($expected_num_analyses > 0) {
                if ($summary_total != $expected_num_defects) {
                    $this->fail("Expected {$expected_num_defects} defects for {$build['label']} but summary reports {$summary_total}");
                }
            }
            $num_defect_types = count($jsonobj['defecttypes']);
            if ($num_defect_types != $expected_num_defect_types) {
                $this->fail("Expected {$expected_num_defect_types} type of defect for {$build['label']}, found {$num_defect_types}");
            }
            if ($expected_num_defects > 0) {
                $num_defects = $jsonobj['dynamicanalyses'][0]['defects'][0];
                if ($num_defects != $expected_num_defects) {
                    $this->fail("Expected {$expected_num_defects} defects for {$build['label']}, found {$num_defects}");
                }
                $defect_type = $jsonobj['defecttypes'][0]['type'];
                if ($expected_defect_type != $defect_type) {
                    $this->fail("Expected type {$expected_defect_type} for {$build['label']}, found {$defect_type}");
                }
            }
            if ($expected_log) {
                $log_stmt->bindParam(':buildid', $build['id'], PDO::PARAM_INT);
                $log_stmt->execute();
                $found_log = $log_stmt->fetchColumn();
                if (strpos($found_log, $expected_log) === false) {
                    $this->fail("Expected log {$expected_log} for {$build['label']}, found {$found_log}");
                }
            }

            // Verify that test duration is calculated correctly.
            $stmt = $pdo->query("SELECT time FROM buildtesttime WHERE buildid = {$build['id']}");
            $found = $stmt->fetchColumn();
            if ($found !== false && $found != $expected_proc_time) {
                $this->fail("Expected $expected_proc_time but found $found for {$build['id']}'s test duration");
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
                $this->fail("{$expected} not found in back link for {$page} ({$found})");
            }
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
                $this->fail("Invalid label ({$label})!");
            }
            $index += 1;
            if ($build['position'] !== $index) {
                $this->fail("Expected {$index} but found ${build['position']} for {$label} position");
            }
        }
    }
}
