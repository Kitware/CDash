<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Build;

class GithubCommentTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->ProjectId = -1;
    }

    public function testGithubPRComment()
    {
        echo "1. testGithubPRComment\n";
        global $configure;

        $this->login();

        // Create a project named CDash and set its repository information.
        $settings = [
            'Name' => 'CDash',
            'Description' => 'CDash',
            'CvsUrl' => 'github.com/Kitware/CDash',
            'CvsViewerType' => 'github',
            'BugTrackerFileUrl' => 'http://public.kitware.com/Bug/view.php?id=',
            'repositories' => [[
                'url' => 'https://github.com/Kitware/CDash',
                'branch' => 'master',
                'username' => $configure['github_username'],
                'password' => $configure['github_password']
            ]]
        ];
        $this->ProjectId = $this->createProject($settings);
        if ($this->ProjectId < 1) {
            return 1;
        }

        // Setup subprojects by submitting the Project.xml file.
        global $configure;
        // Submit the file.
        $url = $this->url . '/submit.php?project=CDash';
        $result = $this->uploadfile($url,
            dirname(__FILE__) . '/data/GithubPR/Project.xml');
        $this->deleteLog($this->logfilename);

        // Submit a failing test.
        echo "Submitting Test.xml\n";
        if (!$this->submitPullRequestFile(
            dirname(__FILE__) . '/data/GithubPR/Test.xml')
        ) {
            return 1;
        }

        // Submit a broken build.
        echo "Submitting Build.xml\n";
        if (!$this->submitPullRequestFile(
            dirname(__FILE__) . '/data/GithubPR/Build.xml')
        ) {
            return 1;
        }

        // Submit a failed configure.
        echo "Submitting Configure.xml\n";
        if (!$this->submitPullRequestFile(
            dirname(__FILE__) . '/data/GithubPR/Configure.xml')
        ) {
            return 1;
        }

        // Make sure these builds link back to the GitHub PR.
        $row = pdo_single_row_query(
                "SELECT id, parentid FROM build
                WHERE name = 'test_PR_comment' AND parentid>0 LIMIT 1");
        $build = new Build();
        $build->Id = $row['id'];
        $build->FillFromId($build->Id);
        $date = $build->GetDate();

        // Parent view
        $content = $this->connect($this->url .
                "/api/v1/index.php?project=CDash&date=$date");
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $build_response = $buildgroup['builds'][0];
        if ($build_response['changelink'] !==
                'github.com/Kitware/CDash/pull/80') {
            $this->fail("Expected changelink not found for parent build.  Found: " . $build_response['changelink']);
        }
        if ($build_response['changeicon'] !== 'img/Octocat.png') {
            $this->fail("Expected changeicon not found for parent build.  Found: " . $build_response['changeicon']);
        }

        // Child view
        $parentid = $row['parentid'];
        $content = $this->connect($this->url .
                "/api/v1/index.php?project=CDash&parentid=$parentid");
        $jsonobj = json_decode($content, true);
        if ($jsonobj['changelink'] !==
                'github.com/Kitware/CDash/pull/80') {
            $this->fail("Expected changelink not found for parent build");
        }
        if ($jsonobj['changeicon'] !== 'img/Octocat.png') {
            $this->fail("Expected changeicon not found for parent build");
        }

        // Delete the project now that we're done with it.
        $this->deleteProject($this->ProjectId);
    }

    public function submitPullRequestFile($file)
    {
        global $configure;
        // Submit the file.
        $url = $this->url . '/submit.php?project=CDash';
        $result = $this->uploadfile($url, $file);

        // Get the ID of the comment that was just posted.
        $log_contents = file_get_contents($this->logfilename);
        $matches = array();
        if (preg_match("/Just posted comment #(\d+)/", $log_contents, $matches) != 1) {
            $this->fail('Log does not contain posted comment ID');
            return false;
        }
        $commentID = $matches[1];

        // Delete the comment from Github.
        $delete_url =
            "https://api.github.com/repos/Kitware/CDash/issues/comments/$commentID";
        $userpwd =
            $configure['github_username'] . ':' . $configure['github_password'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $delete_url);
        curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Googlebot/2.1 (+http://www.google.com/bot.html)');
        $result = curl_exec($ch);
        curl_close($ch);

        if ($result === false) {
            $this->fail("Failed to delete comment #$commentID");
            return false;
        }

        // Clear the log.
        $this->deleteLog($this->logfilename);
        return true;
    }
}
