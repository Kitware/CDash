<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use CDash\Model\Project;

require_once dirname(__FILE__) . '/cdash_test_case.php';

class ManageCoverageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testManageCoverageTest()
    {
        $project = new Project();
        $project->Name = 'InsightExample';
        $projectid = $project->GetIdByName();

        // Get projectid for InsightExample.
        if ($projectid <= 0) {
            $this->fail('Unable to find projectid for InsightExamples');
            return 1;
        }

        //make sure we can't visit the manageCoverage page while logged out
        $this->logout();
        if (!$this->expectsPageRequiresLogin('/manageCoverage.php')) {
            return 1;
        }

        //get a valid coverage buildid
        $db = Database::getInstance();
        $db->getPdo();
        $stmt = $db->prepare("SELECT id FROM build WHERE name LIKE '%simple' AND projectid = :projectid");
        $buildid = false;
        $db->execute($stmt, [':projectid' => $projectid]);
        $buildid = $stmt->fetchColumn();
        $retries = 0;
        while ($buildid === false) {
            $retries += 1;
            if ($retries > 10) {
                $this->fail('Too many attempts to find buildid');
                return 1;
            }
            sleep(1);
            $db->execute($stmt, [':projectid' => $projectid]);
            $buildid = $stmt->fetchColumn();
        }

        $content = $this->connect($this->url . "/manageCoverage.php?buildid=$buildid&projectid=$projectid");
        if (strpos($content, 'simple.cxx') === false) {
            $this->fail("'simple.cxx' not found when expected for buildid=" . $buildid);
            return 1;
        }

        //test the "Add author" button
        if (!$this->setFieldByName('prioritySelection', 2)) {
            $this->fail('SetFieldByName #1 returned false');
            return 1;
        }
        if (!$this->setFieldByName('userSelection', 1)) {
            $this->fail('SetFieldByName #2 returned false');
            return 1;
        }
        $this->clickSubmitByName('addAuthor');
        if (strpos($this->getBrowser()->getContentAsText(), 'administrator [x]') === false) {
            $this->fail("'administrator [x]' not found when expected");
            return 1;
        }

        //test 'remove author' capability
        //need removeuserid & removefileid, find them now.
        $content = $this->getBrowser()->getContent();
        $lines = explode("\n", $content);
        $removeuserid = -1;
        $removefileid = -1;
        foreach ($lines as $line) {
            if (strpos($line, 'removeuserid') !== false) {
                preg_match('#removeuserid=([0-9]+)&amp;removefileid=([0-9]+)"#', $line, $matches);
                $removeuserid = $matches[1];
                $removefileid = $matches[2];
                break;
            }
        }
        if ($removeuserid === -1 || $removefileid === -1) {
            $this->fail("Couldn't find removeuserid or removefileid.");
            return 1;
        }
        $removeAuthorUrl = $this->url . "/manageCoverage.php?buildid=$buildid&projectid=$projectid&removeuserid=$removeuserid&removefileid=$removefileid";
        $content = $this->connect($removeAuthorUrl);
        if (strpos($content, '<td>administrator') !== false) {
            $this->fail("'<td>administrator' found when unexpected");
            return 1;
        }

        //test the "Upload authors file" button
        $authorsFile = dirname(__FILE__) . '/data/authors.txt';
        if (!$this->setFieldByName('authorsFile', @$authorsFile)) {
            $this->fail('SetFieldByName on authorsFile returned false');
            return 1;
        }
        if (!$this->clickSubmitByName('uploadAuthorsFile')) {
            $this->fail('clicking uploadAuthorsFile returned false');
            return 1;
        }

        //test the "Assign last author" button
        if (!$this->clickSubmitByName('assignLastAuthor')) {
            $this->fail('clicking assignLastAuthor returned false');
            return 1;
        }

        //test the "Assign all authors" button
        if (!$this->clickSubmitByName('assignAllAuthors')) {
            $this->fail('clicking assignAllAuthors returned false');
            return 1;
        }

        $this->get("{$this->url}/manageCoverage.php?buildid={$buildid}&projectid={$projectid}");
        $this->clickSubmitByName('sendEmail');
        if (strpos($this->getBrowser()->getContentAsText(),
                'email has been sent successfully') === false
        ) {
            $this->fail(
                "'email has been sent successfully' not found when expected");
            return 1;
        }

        $this->pass('Passed');
        return 0;
    }
}
