<?php


require_once 'include/repository.php';

use App\Models\User;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\UserProject;

class IssueCreationTestCase extends KWWebTestCase
{
    protected $PDO;
    protected $Builds;
    protected $Projects;

    public function __construct()
    {
        parent::__construct();
        $this->Builds = [];
        $this->Projects = [];
        $this->PDO = get_link_identifier()->getPdo();
    }

    public function __destruct()
    {
        foreach ($this->Projects as $name => $project) {
            remove_project_builds($project->Id);
            $project->Delete();
        }
    }

    public function testIssueCreation()
    {
        // Login as admin.
        $this->login();

        // Create projects.
        // The SubProject case is named 'CDash' so we can reuse existing test data.
        $project_names = ['IssueCreationProject', 'CDash'];
        foreach ($project_names as $project_name) {
            $settings = [
                'Name' => $project_name,
                'Public' => 1,
            ];
            $projectid = $this->createProject($settings);
            if ($projectid < 1) {
                $this->fail('Failed to create project');
            }
            $project = new Project();
            $project->Id = $projectid;
            $this->Projects[$project_name] = $project;
        }

        // Submit non-subproject XML files.
        $filenames = [
            'Insight_Experimental_Build.xml',
            'Insight_Experimental_Configure.xml',
            'Insight_Experimental_DynamicAnalysis.xml',
            'Insight_Experimental_Test.xml'];
        foreach ($filenames as $filename) {
            $file = dirname(__FILE__) . "/data/InsightExperimentalExample/$filename";
            if (!$this->submission('IssueCreationProject', $file)) {
                $this->fail("Failed to submit $file");
            }
        }
        // Get the build we just created.
        $build = new Build();
        $query =
            "SELECT b.id FROM build b
            JOIN project p on p.id = b.projectid
            WHERE p.name = 'IssueCreationProject'";
        $buildid1 = $this->PDO->query($query)->fetch()['id'];
        $build->Id = $buildid1;
        $build->FillFromId($build->Id);
        $this->Builds['standalone'] = $build;

        // Add a clean build to the standalone project.
        $clean_build = new Build();
        $clean_build->Name = 'clean build';
        $clean_build->ProjectId = $this->Projects['IssueCreationProject']->Id;
        $clean_build->SetStamp('20090223-0100-Nightly');
        $clean_build->StartTime = gmdate(FMT_DATETIME);
        $clean_build->SubmitTime = $clean_build->StartTime;
        $clean_build->SiteId = 1;
        $clean_build->AddBuild(0, 0);
        $this->Builds['clean'] = $clean_build;

        // Add user1@kw as a project administrator.
        $user = User::where('email', '=', 'user1@kw')->first();
        $userid = $user->id;
        $userproject = new UserProject();
        $userproject->UserId = $userid;
        $userproject->ProjectId = $this->Projects['CDash']->Id;
        $userproject->Role = 2;
        $userproject->Save();

        // Setup subprojects.
        $file = dirname(__FILE__) . '/data/GithubPR/Project.xml';
        if (!$this->submission('CDash', $file)) {
            $this->fail("Failed to submit $file");
        }

        // Verify that administrative access to this project was not overwritten
        // by the Project.xml handler.
        $userproject = new UserProject();
        $userproject->UserId = $userid;
        $userproject->ProjectId = $this->Projects['CDash']->Id;
        $userproject->FillFromUserId();
        if ($userproject->Role != 2) {
            $this->fail("Expected userproject role to be 2, found $userproject->Role");
        }

        // Submit subproject XML file.
        $file = dirname(__FILE__) . '/data/GithubPR/Test.xml';
        if (!$this->submission('CDash', $file)) {
            $this->fail("Failed to submit $file");
        }

        // Get the build we just created.
        $query =
            "SELECT b.id FROM build b
            JOIN project p on p.id = b.projectid
            WHERE p.name = 'CDash' AND b.parentid != -1";
        $buildid2 = $this->PDO->query($query)->fetch()['id'];
        $build = new Build();
        $build->Id = $buildid2;
        $build->FillFromId($build->Id);
        $this->Builds['subproject'] = $build;

        // Validate issue creation URI for our various supported types.
        $trackers = [
            [
                'name' => 'Buganizer',
                'url' => 'https://buganizer.com/issues/new?component=123&template=456',
            ],
            [
                'name' => 'GitHub',
                'url' => 'https://github.com/Kitware/CDash/issues/new?',
            ],
            [
                'name' => 'JIRA',
                'url' => 'http://jira.atlassian.com/secure/CreateIssueDetails!init.jspa?pid=123&issuetype=1',
            ],
        ];

        // Lookup some IDs that we will need in the answer key below.
        $test_stmt = $this->PDO->prepare(
            'SELECT b2t.id FROM test t
            JOIN build2test b2t on b2t.testid = t.id
            JOIN build b on b.id = b2t.buildid
            WHERE b.id = ? AND t.name = ?');
        pdo_execute($test_stmt, [$buildid1, 'itkVectorSegmentationLevelSetFunctionTest1']);
        $build1failedtestid = $test_stmt->fetchColumn();
        pdo_execute($test_stmt, [$buildid1, 'itkVectorFiniteDifferenceFunctionTest1']);
        $build1notruntestid = $test_stmt->fetchColumn();
        pdo_execute($test_stmt, [$buildid2, 'foo']);
        $build2failedtestid = $test_stmt->fetchColumn();
        $da_stmt = $this->PDO->prepare(
            'SELECT id FROM dynamicanalysis WHERE buildid = ? AND name = ?');
        pdo_execute($da_stmt, [$buildid1, 'itkGeodesicActiveContourLevelSetSegmentationModuleTest1']);
        $da_id = $da_stmt->fetchColumn();

        $encoded_base_url = urlencode(config('app.url'));

        $answer_key = [
            'Buganizer' => [
                'Standalone' => "https://buganizer.com/issues/new?component=123&template=456&type=BUG&priority=P0&severity=S0&title=FAILED+%28w%3D3%2C+t%3D6%2C+d%3D10%29%3A+IssueCreationProject+-+Linux-g%2B%2B-4.1-LesionSizingSandbox_Debug+-+Experimental&description=Details+on+the+submission+can+be+found+at+$encoded_base_url%2Fbuild%2F{$buildid1}%0A%0AProject%3A+IssueCreationProject%0ASite%3A+camelot.kitware%0ABuild+Name%3A+Linux-g%2B%2B-4.1-LesionSizingSandbox_Debug%0ABuild+Time%3A+2009-02-23T07%3A10%3A38+UTC%0AType%3A+Experimental%0AWarnings%3A+3%0ATests+not+passing%3A+6%0ADynamic+analysis+tests+failing%3A+10%0A%0A%0A%2AWarnings%2A+%28first+1%29%0ATesting%5CitkDescoteauxSheetnessImageFilterTest2.cxx+line+187+%28$encoded_base_url%2FviewBuildError.php%3Ftype%3D1%26buildid%3D{$buildid1}%29%0A%5C...%5CSandbox%5CTesting%5CitkDescoteauxSheetnessImageFilterTest2.cxx%3A187%3A+warning%3A+converting+to+%3C-30%3E%3C-128%3E%3C-104%3Emain%3A%3AInputPixelType%3C-30%3E%3C-128%3E%3C-103%3E+from+%3C-30%3E%3C-128%3E%3C-104%3Edouble%3C-30%3E%3C-128%3E%3C-103%3E%0A%0A%0A%0A%0A%2ATests+failing%2A+%28first+1%29%0AitkVectorSegmentationLevelSetFunctionTest1+%7C+Completed+%28OTHER_FAULT%29+%7C+%28$encoded_base_url%2Ftest%2F{$build1failedtestid}%29%0A%0A%0A%0A%2ATests+not+run%2A+%28first+1%29%0AitkVectorFiniteDifferenceFunctionTest1+%7C++%7C+%28$encoded_base_url%2Ftest%2F{$build1notruntestid}%29%0A%0A%0A%0A%2ADynamic+analysis+tests+failing+or+not+run%2A+%28first+1%29%0AitkGeodesicActiveContourLevelSetSegmentationModuleTest1+%28$encoded_base_url%2FviewDynamicAnalysisFile.php%3Fid%3D{$da_id}%29%0A%0A",
                'SubProject' => "https://buganizer.com/issues/new?component=123&template=456&type=BUG&priority=P0&severity=S0&title=FAILED+%28t%3D1%29%3A+CDash%2FSubProject1+-+test_PR_comment+-+Experimental&description=Details+on+the+submission+can+be+found+at+$encoded_base_url%2Fbuild%2F{$buildid2}%0A%0AProject%3A+CDash%0ASubProject%3A+SubProject1%0ASite%3A+elysium%0ABuild+Name%3A+test_PR_comment%0ABuild+Time%3A+2015-08-11T20%3A45%3A30+UTC%0AType%3A+Experimental%0ATests+not+passing%3A+1%0A%0A%0A%2ATests+failing%2A+%28first+1%29%0Afoo+%7C+Completed+%28Failed%29+%7C+%28$encoded_base_url%2Ftest%2F{$build2failedtestid}%29%0A%0A&cc=simpletest@localhost,user1@kw",
            ],
            'GitHub' => [
                'Standalone' => "https://github.com/Kitware/CDash/issues/new?title=FAILED+%28w%3D3%2C+t%3D6%2C+d%3D10%29%3A+IssueCreationProject+-+Linux-g%2B%2B-4.1-LesionSizingSandbox_Debug+-+Experimental&body=Details+on+the+submission+can+be+found+at+$encoded_base_url%2Fbuild%2F{$buildid1}%0A%0AProject%3A+IssueCreationProject%0ASite%3A+camelot.kitware%0ABuild+Name%3A+Linux-g%2B%2B-4.1-LesionSizingSandbox_Debug%0ABuild+Time%3A+2009-02-23T07%3A10%3A38+UTC%0AType%3A+Experimental%0AWarnings%3A+3%0ATests+not+passing%3A+6%0ADynamic+analysis+tests+failing%3A+10%0A%0A%0A%2AWarnings%2A+%28first+1%29%0ATesting%5CitkDescoteauxSheetnessImageFilterTest2.cxx+line+187+%28$encoded_base_url%2FviewBuildError.php%3Ftype%3D1%26buildid%3D{$buildid1}%29%0A%5C...%5CSandbox%5CTesting%5CitkDescoteauxSheetnessImageFilterTest2.cxx%3A187%3A+warning%3A+converting+to+%3C-30%3E%3C-128%3E%3C-104%3Emain%3A%3AInputPixelType%3C-30%3E%3C-128%3E%3C-103%3E+from+%3C-30%3E%3C-128%3E%3C-104%3Edouble%3C-30%3E%3C-128%3E%3C-103%3E%0A%0A%0A%0A%0A%2ATests+failing%2A+%28first+1%29%0AitkVectorSegmentationLevelSetFunctionTest1+%7C+Completed+%28OTHER_FAULT%29+%7C+%28$encoded_base_url%2Ftest%2F{$build1failedtestid}%29%0A%0A%0A%0A%2ATests+not+run%2A+%28first+1%29%0AitkVectorFiniteDifferenceFunctionTest1+%7C++%7C+%28$encoded_base_url%2Ftest%2F{$build1notruntestid}%29%0A%0A%0A%0A%2ADynamic+analysis+tests+failing+or+not+run%2A+%28first+1%29%0AitkGeodesicActiveContourLevelSetSegmentationModuleTest1+%28$encoded_base_url%2FviewDynamicAnalysisFile.php%3Fid%3D{$da_id}%29%0A%0A",
                'SubProject' => "https://github.com/Kitware/CDash/issues/new?title=FAILED+%28t%3D1%29%3A+CDash%2FSubProject1+-+test_PR_comment+-+Experimental&body=Details+on+the+submission+can+be+found+at+$encoded_base_url%2Fbuild%2F{$buildid2}%0A%0AProject%3A+CDash%0ASubProject%3A+SubProject1%0ASite%3A+elysium%0ABuild+Name%3A+test_PR_comment%0ABuild+Time%3A+2015-08-11T20%3A45%3A30+UTC%0AType%3A+Experimental%0ATests+not+passing%3A+1%0A%0A%0A%2ATests+failing%2A+%28first+1%29%0Afoo+%7C+Completed+%28Failed%29+%7C+%28$encoded_base_url%2Ftest%2F{$build2failedtestid}%29%0A%0A%40simpletest+%40user1+",
            ],
            'JIRA' => [
                'Standalone' => "http://jira.atlassian.com/secure/CreateIssueDetails!init.jspa?pid=123&issuetype=1&summary=FAILED+%28w%3D3%2C+t%3D6%2C+d%3D10%29%3A+IssueCreationProject+-+Linux-g%2B%2B-4.1-LesionSizingSandbox_Debug+-+Experimental&description=Details+on+the+submission+can+be+found+at+$encoded_base_url%2Fbuild%2F{$buildid1}%0A%0AProject%3A+IssueCreationProject%0ASite%3A+camelot.kitware%0ABuild+Name%3A+Linux-g%2B%2B-4.1-LesionSizingSandbox_Debug%0ABuild+Time%3A+2009-02-23T07%3A10%3A38+UTC%0AType%3A+Experimental%0AWarnings%3A+3%0ATests+not+passing%3A+6%0ADynamic+analysis+tests+failing%3A+10%0A%0A%0A%2AWarnings%2A+%28first+1%29%0ATesting%5CitkDescoteauxSheetnessImageFilterTest2.cxx+line+187+%28$encoded_base_url%2FviewBuildError.php%3Ftype%3D1%26buildid%3D{$buildid1}%29%0A%5C...%5CSandbox%5CTesting%5CitkDescoteauxSheetnessImageFilterTest2.cxx%3A187%3A+warning%3A+converting+to+%3C-30%3E%3C-128%3E%3C-104%3Emain%3A%3AInputPixelType%3C-30%3E%3C-128%3E%3C-103%3E+from+%3C-30%3E%3C-128%3E%3C-104%3Edouble%3C-30%3E%3C-128%3E%3C-103%3E%0A%0A%0A%0A%0A%2ATests+failing%2A+%28first+1%29%0AitkVectorSegmentationLevelSetFunctionTest1+%7C+Completed+%28OTHER_FAULT%29+%7C+%28$encoded_base_url%2Ftest%2F{$build1failedtestid}%29%0A%0A%0A%0A%2ATests+not+run%2A+%28first+1%29%0AitkVectorFiniteDifferenceFunctionTest1+%7C++%7C+%28$encoded_base_url%2Ftest%2F{$build1notruntestid}%29%0A%0A%0A%0A%2ADynamic+analysis+tests+failing+or+not+run%2A+%28first+1%29%0AitkGeodesicActiveContourLevelSetSegmentationModuleTest1+%28$encoded_base_url%2FviewDynamicAnalysisFile.php%3Fid%3D{$da_id}%29%0A%0A",
                'SubProject' => "http://jira.atlassian.com/secure/CreateIssueDetails!init.jspa?pid=123&issuetype=1&summary=FAILED+%28t%3D1%29%3A+CDash%2FSubProject1+-+test_PR_comment+-+Experimental&description=Details+on+the+submission+can+be+found+at+$encoded_base_url%2Fbuild%2F{$buildid2}%0A%0AProject%3A+CDash%0ASubProject%3A+SubProject1%0ASite%3A+elysium%0ABuild+Name%3A+test_PR_comment%0ABuild+Time%3A+2015-08-11T20%3A45%3A30+UTC%0AType%3A+Experimental%0ATests+not+passing%3A+1%0A%0A%0A%2ATests+failing%2A+%28first+1%29%0Afoo+%7C+Completed+%28Failed%29+%7C+%28$encoded_base_url%2Ftest%2F{$build2failedtestid}%29%0A%0A%5B%7Esimpletest%5D+%5B%7Euser1%5D+",
            ],
        ];

        foreach ($trackers as $tracker) {
            // Set standalone project to use this tracker.
            $this->Projects['IssueCreationProject']->Filled = false;
            $settings = [
                'Id' => $this->Projects['IssueCreationProject']->Id,
                'BugTrackerType' => $tracker['name'],
                'BugTrackerNewIssueUrl' => $tracker['url'],
            ];
            $this->createProject($settings, true);

            // Validate issue creation link.
            $found = generate_bugtracker_new_issue_link(
                $this->Builds['standalone'], $this->Projects['IssueCreationProject']);
            $expected = $answer_key[$tracker['name']]['Standalone'];
            if ($found !== $expected) {
                $pos = strspn($found ^ $expected, "\0");
                $error_msg = sprintf(
                    "Mismatch for Standalone %s.  First difference at position %d: '%s' vs '%s'\nFound:\n%s\nExpected:\n%s\n",
                    $tracker['name'], $pos, $found[$pos], $expected[$pos], $found, $expected);
                $this->fail($error_msg);
            }

            // Set subproject project to use this tracker.
            $this->Projects['CDash']->Filled = false;
            $settings['Id'] = $this->Projects['CDash']->Id;
            $this->createProject($settings, true);

            // Validate issue creation link.
            $found = generate_bugtracker_new_issue_link($this->Builds['subproject'],
                $this->Projects['CDash']);
            $expected = $answer_key[$tracker['name']]['SubProject'];
            if ($found !== $expected) {
                $pos = strspn($found ^ $expected, "\0");
                $error_msg = sprintf(
                    "Mismatch for SubProject %s.  First difference at position %d: '%s' vs '%s'\nFound:\n%s\nExpected:\n%s\n",
                    $tracker['name'], $pos, $found[$pos], $expected[$pos], $found, $expected);
                $this->fail($error_msg);
            }
        }

        // Verify that the issue creation link is not shown for clean builds.
        $content = $this->get($this->url . "/api/v1/buildSummary.php?buildid={$this->Builds['clean']->Id}");
        $response = json_decode($content, true);
        $this->assertFalse($response['newissueurl']);
    }
}
