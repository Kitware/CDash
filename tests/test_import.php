<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ImportTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testImportTest()
    {
        //make sure we can't visit the import page while logged out
    $this->logout();
        $content = $this->get($this->url."/import.php");
        if (strpos($content, "<title>Login</title>") === false) {
            $this->fail("'<title>Login</title>' not found when expected");
            return 1;
        }

    //make sure we can visit the page while logged in
    $this->login();
        $content = $this->get($this->url."/import.php");
        if (strpos($content, "dartboard") === false) {
            $this->fail("'dartboard' not found when expected");
            return 1;
        }

    //fill out the import form
    if (!$this->SetFieldByName("project", "1")) {
        $this->fail("SetFieldByName on project returned false");
        return 1;
    }
        if (!$this->SetFieldByName("monthFrom", "07")) {
            $this->fail("SetFieldByName on monthFrom returned false");
            return 1;
        }
        if (!$this->SetFieldByName("dayFrom", "19")) {
            $this->fail("SetFieldByName on dayFrom returned false");
            return 1;
        }
        if (!$this->SetFieldByName("yearFrom", "2005")) {
            $this->fail("SetFieldByName on yearFrom returned false");
            return 1;
        }
        if (!$this->SetFieldByName("monthTo", "07")) {
            $this->fail("SetFieldByName on monthTo returned false");
            return 1;
        }
        if (!$this->SetFieldByName("dayTo", "19")) {
            $this->fail("SetFieldByName on dayTo returned false");
            return 1;
        }
        if (!$this->SetFieldByName("yearTo", "2005")) {
            $this->fail("SetFieldByName on yearTo returned false");
            return 1;
        }
        $pathToSites = dirname(__FILE__)."/data/Sites";
        if (!$this->SetFieldByName("directory", $pathToSites)) {
            $this->fail("SetFieldByName on directory returned false");
            return 1;
        }
        $content = $this->clickSubmitByName("Submit");

    //check for expected output
    if (strpos($content, "<status>OK</status>") === false) {
        $this->fail("'<status>OK</status>' not found on import.php\n$content\n");
        return 1;
    }

        echo "content:\n";
        echo $content;
        echo "\n";
        echo "endcontent\n";

        $this->pass("Passed");
        return 0;
    }
}
