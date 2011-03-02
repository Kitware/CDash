<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class UploadFileTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  // Submit an upload XML
  function testSubmitUploadXML()
    {
    $this->deleteLog($this->logfilename);
    $rep  = dirname(__FILE__)."/data/EmailProjectExample";
    $file = "$rep/1_upload.xml";
    if(!$this->submission('EmailProjectExample',$file))
      {
      return;
      }
    if(!$this->checkLog($this->logfilename))
      {
      return;
      }
    $this->pass("Submission of $file has succeeded");
    }

  // Make sure the uploaded files are present
  function verifyFileSubmission()
    {
    $query = $this->db->query("SELECT buildid FROM build2uploadfile");
    if(count($query) == 0)
      {
      $this->fail('No upload files were added to the database');
      return;
      }
    $buildid = $query[0]['buildid'];
    $content = $this->connect($this->url."/viewFiles.php?buildid=$buildid");
    if(!$content)
      {
      return;
      }
    $this->clickLink('CMakeCache.txt');
    if(!$this->checkLog($this->logfilename))
      {
      return;
      }
    }
}

?>
