<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';

class GithubCommentTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testGithubPRComment()
    {
        echo "1. testGithubPRComment\n";
        global $configure;

        $this->login();

        // Create a project named CDash.
        $name = 'CDash';
        $description = 'CDash';
        $svnviewerurl = 'https://github.com/Kitware/CDash';
        $bugtrackerfileurl = 'http://public.kitware.com/Bug/view.php?id=';
        $this->createProject($name, $description, $svnviewerurl, $bugtrackerfileurl);
        $content = $this->connect($this->url . '/index.php?project=CDash');
        if (!$content) {
            return 1;
        }

        // Set its repository information.
        if (!$this->getProjectId()) {
            $this->fail('Could not retrieve projectid');
            return 1;
        }
        $this->get($this->url . "/createProject.php?edit=1&projectid=$this->projectid#fragment-3");
        if (!$this->setFieldByName('cvsviewertype', 'github')) {
            $this->fail('Set Viewer Type returned false');
            return 1;
        }
        if (!$this->setFieldByName('cvsRepository[0]', 'https://github.com/Kitware/CDash')) {
            $this->fail('Set Repository returned false');
            return 1;
        }
        if (!$this->setFieldByName('cvsBranch[0]', 'master')) {
            $this->fail('Set Branch returned false');
            return 1;
        }
        if (!$this->setFieldByName('cvsUsername[0]', $configure['github_username'])) {
            $this->fail('Set Username returned false');
            return 1;
        }
        if (!$this->setFieldByName('cvsPassword[0]', $configure['github_password'])) {
            $this->fail('Set Password returned false');
            return 1;
        }
        $this->clickSubmitByName('Update');

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

        // Delete the project now that we're done with it.
        $this->get($this->url . "/createProject.php?edit=1&projectid=$this->projectid#fragment-8");
        $this->clickSubmitByName('Delete');

        // Verify that it's actually gone.
        if (!$query = pdo_query(
            "SELECT * FROM project WHERE id=$this->projectid")
        ) {
            $this->fail('pdo_query returned false');
            return 1;
        }
        if (pdo_num_rows($query) > 0) {
            $this->fail('Project not deleted');
            return 1;
        }
        $this->pass('Test passed');
    }

    public function getProjectId()
    {
        //get projectid for CDash
        $content = $this->connect($this->url . '/manageProjectRoles.php');
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (strpos($line, 'CDash') !== false) {
                if (preg_match('#<option value="([0-9]+)"#', $line, $matches) == 1) {
                    $this->projectid = $matches[1];
                    break;
                }
            }
        }
        if ($this->projectid === -1) {
            $this->fail('Unable to find projectid for CDash');
            return false;
        }
        return true;
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
