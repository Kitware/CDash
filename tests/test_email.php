<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use CDash\Config;

require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/pdo.php';

class EmailTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testCreateProjectTest()
    {
        $settings = array(
                'Name' => 'EmailProjectExample',
                'Description' => 'Project EmailProjectExample test for cdash testing',
                'EmailBrokenSubmission' => 1,
                'EmailRedundantFailures' => 0);
        $this->createProject($settings);
    }

    public function testRegisterUser()
    {
        $url = $this->url . '/register.php';
        $content = $this->connect($url);
        if ($content == false) {
            return;
        }

        $this->setField('fname', 'Firstname');
        $this->setField('lname', 'Lastname');
        $this->setField('email', 'user1@kw');
        $this->setField('passwd', 'user1');
        $this->setField('passwd2', 'user1');
        $this->setField('institution', 'Kitware Inc');
        $this->clickSubmitByName('sent', array('url' => 'catchbot'));

        // Make sure the user was created successfully.
        if (!$this->userExists('user1@kw')) {
            $this->fail("Failed to register new user");
        }

        // Login as the user.
        $this->login('user1@kw', 'user1');

        // Subscribe to the project.
        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->query("SELECT id FROM project WHERE name = 'EmailProjectExample'");
        $row = $stmt->fetch();
        if (!$row) {
            $this->fail('Could not lookup projectid');
        }
        $projectid = $row['id'];
        $this->connect($this->url . "/subscribeProject.php?projectid=$projectid");
        $this->setField('credentials[0]', 'user1kw');
        $this->setField('emailsuccess', '1');
        $this->clickSubmitByName('subscribe');
        if (!$this->checkLog($this->logfilename)) {
            return;
        }
    }

    public function testSubmissionFirstBuild()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $file = "$rep/1_build.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }
        $file = "$rep/1_configure.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }
        $file = "$rep/1_test.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }
        if (!$this->checkLog($this->logfilename)) {
            return;
        }
        $this->pass("Submission of $file has succeeded");
    }

    public function testSubmissionEmailBuild()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $file = "$rep/2_build.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }

        $file = "$rep/2_update.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }

        $config = Config::getInstance();

        // illuminate/support/helpers/str_contains
        $expected = [
            'cdash.DEBUG: user1@kw',
            'cdash.DEBUG: PASSED (w=6): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'Congratulations, a submission to CDash for the project EmailProjectExample has fixed build warnings.',
            'You have been identified as one of the authors who have checked in changes that are part of this ',
            "{$config->getBaseUrl()}/buildSummary.php?buildid=",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23T05:02:04 EST',
            'Type: Nightly',
            'Warning fixed: 6',
            '-CDash on',
            'function', // *sigh*
        ];
        if ($this->assertLogContains($expected, 17)) {
            $this->pass('Passed');
        }
    }

    public function testSubmissionEmailTest()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $file = "$rep/2_test.xml";

        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }
        $config = Config::getInstance();
        // illuminate/support/helpers/str_contains
        $expected = [
            'cdash.DEBUG: user1@kw',
            'cdash.DEBUG: PASSED (t=2): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'Congratulations, a submission to CDash for the project EmailProjectExample has fixed failing tests.',
            'You have been identified as one of the authors who have checked in changes that are part of this',
            "{$config->getBaseUrl()}/buildSummary.php?buildid=",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23T05:02:04 EST',
            'Type: Nightly',
            'Tests fixed: 2',
            '-CDash on',
            'function'
        ];

        if ($this->assertLogContains($expected, 17)) {
            $this->pass('Passed');
        }
    }

    public function testSubmissionEmailDynamicAnalysis()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $file = "$rep/2_dynamicanalysis.xml";

        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }
        $config = Config::getInstance();
        $url = $config->getBaseUrl();
        $expected = [
            'user1@kw',
            'FAILED (w=3, t=3, d=10): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'A submission to CDash for the project EmailProjectExample has build warnings and failing tests and failing dynamic analysis tests.',
            'You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.',
            "Details on the submission can be found at {$url}/buildSummary.php?buildid=",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23T05:02:04 EST',
            'Type: Nightly',
            'Warnings: 3',
            'Tests not passing: 3',
            'Dynamic analysis tests failing: 10',
            '*Warnings*',
            '3>f:\program files\microsoft sdks\windows\v6.0a\include\servprov.h(79) : warning C4068: unknown pragma',
            '3>F:\Program Files\Microsoft SDKs\Windows\v6.0A\\\include\urlmon.h(352) : warning C4068: unknown pragma',
            '3>XcedeCatalog.cxx',
            '2>bmScriptAddDashboardLabelAction.cxx',
            '3>f:\program files\microsoft sdks\windows\v6.0a\include\servprov.h(79) : warning C4068: unknown pragma',
            '*Tests failing*',
            "DashboardSendTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            "StringActionsTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            "MathActionsTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            '*Dynamic analysis tests failing or not run* (first 5)',
            "itkGeodesicActiveContourLevelSetSegmentationModuleTest1 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkShapeDetectionLevelSetSegmentationModuleTest1 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkShapeDetectionLevelSetSegmentationModuleTest2 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkVectorFiniteDifferenceFunctionTest1 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkVectorLevelSetFunctionTest2 ({$url}/viewDynamicAnalysisFile.php?id=",
            '-CDash on',
            'function',
            'simpletest@localhost',
            'FAILED (w=3, t=3, d=10): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'A submission to CDash for the project EmailProjectExample has build warnings and failing tests and failing dynamic analysis tests.',
            'You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.',
            "Details on the submission can be found at {$url}/buildSummary.php?buildid=",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23T05:02:04 EST',
            'Type: Nightly',
            'Warnings: 3',
            'Tests not passing: 3',
            'Dynamic analysis tests failing: 10',
            '*Warnings*',
            '3>f:\program files\microsoft sdks\windows\v6.0a\include\servprov.h(79) : warning C4068: unknown pragma',
            '3>F:\Program Files\Microsoft SDKs\Windows\v6.0A\\\include\urlmon.h(352) : warning C4068: unknown pragma',
            '3>XcedeCatalog.cxx',
            '2>bmScriptAddDashboardLabelAction.cxx',
            '3>f:\program files\microsoft sdks\windows\v6.0a\include\servprov.h(79) : warning C4068: unknown pragma',
            '*Tests failing*',
            "DashboardSendTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            "StringActionsTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            "MathActionsTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            '*Dynamic analysis tests failing or not run* (first 5)',
            "itkGeodesicActiveContourLevelSetSegmentationModuleTest1 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkShapeDetectionLevelSetSegmentationModuleTest1 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkShapeDetectionLevelSetSegmentationModuleTest2 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkVectorFiniteDifferenceFunctionTest1 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkVectorLevelSetFunctionTest2 ({$url}/viewDynamicAnalysisFile.php?id=",
            '-CDash on',
            'function',
        ];

        if ($this->assertLogContains($expected, 99)) {
            $this->pass('Passed');
        }
    }

    public function testEmailSentToGitCommitter()
    {
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $file = "$rep/3_update.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }

        $this->deleteLog($this->logfilename);
        $file = "$rep/3_test.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            //return;
        }

        $config = Config::getInstance();
        $url = $config->getBaseUrl();
        $expected = [
            'simpletest@localhost',
            'FAILED (t=4): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'A submission to CDash for the project EmailProjectExample has failing tests.',
            'You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.',
            "Details on the submission can be found at {$url}/buildSummary.php?buildid=",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23T05:02:05 EST',
            'Type: Nightly',
            'Tests not passing: 4',
            '*Tests failing*',
            "curl | Completed | ({$url}/testDetails.php?test=",
            "DashboardSendTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            "StringActionsTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            "MathActionsTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            '-CDash on',
            'function',
            'user1@kw',
            'FAILED (t=4): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'A submission to CDash for the project EmailProjectExample has failing tests.',
            'You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.',
            "Details on the submission can be found at {$url}/buildSummary.php?buildid=",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23T05:02:05 EST',
            'Type: Nightly',
            'Tests not passing: 4',
            '*Tests failing*',
            "curl | Completed | ({$url}/testDetails.php?test=",
            "DashboardSendTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            "StringActionsTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            "MathActionsTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            '-CDash on',
            'function',
        ];

        if ($this->assertLogContains($expected, 49)) {
            $this->pass('Passed');
        }
    }
}
