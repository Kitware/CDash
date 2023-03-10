<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use App\Models\User;
use App\Models\TestDiff;
use CDash\Config;

require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/pdo.php';

class EmailTestCase extends KWWebTestCase
{
    private $project;

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
        $this->project = $this->createProject($settings);
    }

    public function testRegisterUser()
    {
        $this->deleteLog($this->logfilename);

        $user = $this->createUser([
            'firstname' => 'Firstname',
            'lastname' => 'Lastname',
            'email' => 'user1@kw',
            'password' => 'user1',
            'institution' => 'Kitware, Inc',
        ]);

        if (!$user->id) {
            $this->fail("Unable to create user");
            return;
        }

        $this->actingAs(['email' => 'user1@kw', 'password' => 'user1'])
            ->connect($this->url . "/subscribeProject.php?projectid={$this->project}");
        $this->setField('credentials[0]', 'user1kw');
        $this->setField('emailsuccess', '1');
        $this->clickSubmitByName('subscribe');
        if (!$this->checkLog($this->logfilename)) {
            $this->fail("Errors logged");
        }
    }

    public function testRegisterNoEmailUser()
    {
        $user = new User();
        $user->email = 'user2@kw';
        $user->password = password_hash('user2', PASSWORD_DEFAULT);
        $user->firstname = 'user2';
        $user->lastname = 'kw';
        $user->institution = 'Kitware';
        $user->admin = 0;
        $user->save();
        if (!$user->id) {
            $this->fail('Failed to create user2');
        }
        $db = \CDash\Database::getInstance();

        $stmt = $db->prepare('INSERT INTO user2project (userid, projectid, role, emailtype) VALUES (?, ?, ?, ?)');
        $db->insert($stmt, [$user->id, $this->project, 0, 0]);
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

        $expected = [
            'DEBUG: user1@kw',
            'DEBUG: PASSED (w=6): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'Congratulations. A submission to CDash for the project EmailProjectExample has fixed warnings',
            "{$config->getBaseUrl()}/build/",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23 10:02:04',
            'Type: Nightly',
            'Warnings fixed: 6',
            '-CDash on'
        ];
        if ($this->assertLogContains($expected, 15)) {
            $this->pass('Passed');
        }

        // Also check that viewUpdate shows email address for logged in users.
        $db = \CDash\Database::getInstance();
        $stmt = $db->prepare("
                SELECT build.id FROM build
                JOIN project ON (build.projectid = project.id)
                JOIN build2update ON (build.id = build2update.buildid)
                WHERE build.name = 'Win32-MSVC2009' AND
                      project.name = 'EmailProjectExample'");
        $db->execute($stmt);
        $buildid = $stmt->fetchColumn();
        $this->login();
        $this->get($this->url . "/api/v1/viewUpdate.php?buildid=$buildid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $expected = 'user1@kw';
        $actual = $jsonobj['updategroups'][0]['directories'][0]['files'][0]['email'];
        $this->assertEqual($expected, $actual);
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
        $expected = [
            'DEBUG: user1@kw',
            'DEBUG: PASSED (t=2): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'Congratulations. A submission to CDash for the project EmailProjectExample has fixed failing tests',
            "{$config->getBaseUrl()}/build/",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23 10:02:04',
            'Type: Nightly',
            'Test failures fixed: 2',
            '-CDash on'
        ];

        if ($this->assertLogContains($expected, 15)) {
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
            'simpletest@localhost',
            'FAILED (d=10): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'A submission to CDash for the project EmailProjectExample has dynamic analysis tests failing or not run',
            "{$url}/build/",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23T10:04:13',
            'Type: Nightly',
            'Total Dynamic analysis tests failing or not run: 10',
            '*Dynamic analysis tests failing or not run* (first 5 included)',
            "itkVectorFiniteDifferenceFunctionTest1 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkVectorSegmentationLevelSetFunctionTest1 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkVectorLevelSetFunctionTest2 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkVectorSparseFieldLevelSetImageFilterTest1 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkVectorSparseFieldLevelSetImageFilterTest2 ({$url}/viewDynamicAnalysisFile.php?id=",
            "-CDash on",
            'user1@kw',
            'FAILED (d=10): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'A submission to CDash for the project EmailProjectExample has dynamic analysis tests failing or not run',
            "{$url}/build/",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23T10:04:13',
            'Type: Nightly',
            'Total Dynamic analysis tests failing or not run: 10',
            '*Dynamic analysis tests failing or not run* (first 5 included)',
            "itkVectorFiniteDifferenceFunctionTest1 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkVectorSegmentationLevelSetFunctionTest1 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkVectorLevelSetFunctionTest2 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkVectorSparseFieldLevelSetImageFilterTest1 ({$url}/viewDynamicAnalysisFile.php?id=",
            "itkVectorSparseFieldLevelSetImageFilterTest2 ({$url}/viewDynamicAnalysisFile.php?id=",
            "-CDash on",
        ];

        if ($this->assertLogContains($expected, 43)) {
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
            return;
        }

        $config = Config::getInstance();
        $url = $config->getBaseUrl();
        $expected = [
            'simpletest@localhost',
            'FAILED (t=4): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'A submission to CDash for the project EmailProjectExample has failing tests.',
            "Details on the submission can be found at {$url}/build/",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23 10:02:05',
            'Type: Nightly',
            'Total Failing Tests: 4',
            '*Failing Tests*',
            "curl | Completed | ({$url}/testDetails.php?test=",
            "DashboardSendTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            "StringActionsTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            "MathActionsTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            '-CDash on',
            'user1@kw',
            'FAILED (t=4): EmailProjectExample - Win32-MSVC2009 - Nightly',
            'A submission to CDash for the project EmailProjectExample has failing tests.',
            "Details on the submission can be found at {$url}/build/",
            'Project: EmailProjectExample',
            'Site: Dash20.kitware',
            'Build Name: Win32-MSVC2009',
            'Build Time: 2009-02-23 10:02:05',
            'Type: Nightly',
            'Total Failing Tests: 4',
            '*Failing Tests*',
            "curl | Completed | ({$url}/testDetails.php?test=",
            "DashboardSendTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            "StringActionsTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            "MathActionsTest | Completed (OTHER_FAULT) | ({$url}/testDetails.php?test=",
            '-CDash on',
        ];

        if ($this->assertLogContains($expected, 41)) {
            $this->pass('Passed');
        }
    }

    public function testVerifyTestDiffValues() : void
    {
        // Verify that we have three builds for this project.
        $project = \DB::table('project')->where('name', 'EmailProjectExample')->first();
        $builds = \DB::table('build')->where('projectid', $project->id)->get();
        $this->assertTrue(count($builds) === 3);

        // Verify that we have four rows in the testdiff table for these builds.
        $testdiffs = \DB::table('testdiff')
            ->where('buildid', $builds[1]->id)
            ->orWhere('buildid', $builds[2]->id)
            ->get();
        $this->assertTrue(count($testdiffs) === 4);

        $found = [0 => false, 1 => false, 2 => false, 3 => false];
        $expected = [0 => true, 1 => true, 2 => true, 3 => true];
        foreach ($testdiffs as $testdiff) {
            if ($testdiff->buildid === $builds[1]->id &&
                $testdiff->type === TestDiff::TEST_TYPE_FAILED &&
                $testdiff->difference_positive === 0 &&
                $testdiff->difference_negative === 2) {
                $found[0] = true;
            }
            if ($testdiff->buildid === $builds[1]->id &&
                $testdiff->type === TestDiff::TEST_TYPE_PASSED &&
                $testdiff->difference_positive === 2 &&
                $testdiff->difference_negative === 0) {
                $found[1] = true;
            }
            if ($testdiff->buildid === $builds[2]->id &&
                $testdiff->type === TestDiff::TEST_TYPE_FAILED &&
                $testdiff->difference_positive === 1 &&
                $testdiff->difference_negative === 0) {
                $found[2] = true;
            }
            if ($testdiff->buildid === $builds[2]->id &&
                $testdiff->type === TestDiff::TEST_TYPE_PASSED &&
                $testdiff->difference_positive === 0 &&
                $testdiff->difference_negative === 1) {
                $found[3] = true;
            }
        }

        $this->assertTrue($found === $expected);
    }
}
