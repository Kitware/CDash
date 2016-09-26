<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

class UploadFileTestCase extends KWWebTestCase
{
    public $BuildId;
    public $FileId;
    public $Sha1Sum;

    public function __construct()
    {
        parent::__construct();
    }

    // Submit an upload XML
    public function testSubmitUploadXML()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $file = "$rep/1_upload.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }
        if (!$this->checkLog($this->logfilename)) {
            return;
        }
        $this->pass("Submission of $file has succeeded");
    }

    // Make sure the uploaded files are present
    public function testVerifyFileSubmission()
    {
        $this->deleteLog($this->logfilename);
        //Verify file exists in the database
        $query = $this->db->query('SELECT buildid, fileid FROM build2uploadfile');
        if (count($query) == 0) {
            $this->fail('No build2upload records were added to the database');
            return;
        }
        $this->BuildId = $query[0]['buildid'];

        $content = $this->connect($this->url . "/viewFiles.php?buildid=$this->BuildId");
        if (!$content) {
            return;
        }

        $this->assertClickable('http://www.kitware.com/company/about.html');

        //Verify symlink and content exist on disk
        $query = $this->db->query("SELECT id, sha1sum FROM uploadfile WHERE filename='CMakeCache.txt'");
        if (count($query) == 0) {
            $this->fail('CMakeCache.txt was not added to the uploadfile table');
            return;
        }
        $this->FileId = $query[0]['id'];
        $this->Sha1Sum = $query[0]['sha1sum'];

        global $CDASH_UPLOAD_DIRECTORY, $CDASH_DOWNLOAD_RELATIVE_URL;
        $dirName = $CDASH_UPLOAD_DIRECTORY . '/' . $this->Sha1Sum;
        if (!is_dir($dirName)) {
            $this->fail("Directory $dirName was not created");
            return;
        }
        if (!file_exists($dirName . '/' . $this->Sha1Sum)) {
            $this->fail("File contents were not written to $dirName/$this->Sha1Sum");
            return;
        }
        if (!file_exists($dirName . '/CMakeCache.txt')) {
            $this->fail("File symlink was not written to $dirName/CMakeCache.txt");
            return;
        }

        // Make sure the file is downloadable.
        $content = $this->connect("$this->url/$CDASH_DOWNLOAD_RELATIVE_URL/$this->Sha1Sum/CMakeCache.txt");
        if (!$content) {
            $this->fail('No content returned when trying to download CMakeCache.txt');
            return;
        }

        $pos = strpos($content, 'This is the CMakeCache file');
        if ($pos === false) {
            $this->fail('Expected content not found in CMakeCache.txt');
            return;
        }

        $this->pass('Uploaded file exists in database, on disk, and is downloadable.');
    }
}
