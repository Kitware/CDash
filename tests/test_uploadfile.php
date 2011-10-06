<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

require_once('cdash/common.php');
require_once('cdash/pdo.php');

class UploadFileTestCase extends KWWebTestCase
{
  var $BuildId;
  var $FileId;
  var $Sha1Sum;

  function __construct()
    {
    parent::__construct();
    }

  // Set a suitable upload quota on EmailProjectExample
  function testSetUploadQuota()
    {
    $this->deleteLog($this->logfilename);
    $this->login();
    $query = $this->db->query("SELECT id FROM project WHERE name = 'EmailProjectExample'");
    $projectid = $query[0]['id'];
    $content = $this->connect($this->url.'/createProject.php?projectid='.$projectid);

    if($content == false)
      {
      return;
      }

    // set the upload quota to 1 GB
    $this->setField('uploadQuota','1');
    $this->clickSubmitByName('Update');
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
  function testVerifyFileSubmission()
    {
    $this->deleteLog($this->logfilename);
    //Verify file exists in the database
    $query = $this->db->query("SELECT buildid, fileid FROM build2uploadfile");
    if(count($query) == 0)
      {
      $this->fail('No build2upload records were added to the database');
      return;
      }
    $this->BuildId = $query[0]['buildid'];

    $content = $this->connect($this->url."/viewFiles.php?buildid=$this->BuildId");
    if(!$content)
      {
      return;
      }
    
    $this->assertClickable('http://www.kitware.com/company/about.html');
      
    $this->clickLink('CMakeCache.txt');
    if(!$this->checkLog($this->logfilename))
      {
      return;
      }

    //Verify symlink and content exist on disk
    $query = $this->db->query("SELECT id, sha1sum FROM uploadfile WHERE filename='CMakeCache.txt'");
    if(count($query) == 0)
      {
      $this->fail('CMakeCache.txt was not added to the uploadfile table');
      return;
      }
    $this->FileId = $query[0]['id'];
    $this->Sha1Sum = $query[0]['sha1sum'];

    global $CDASH_UPLOAD_DIRECTORY;
    $dirName = dirname(__FILE__).'/../'.$CDASH_UPLOAD_DIRECTORY.'/'.$this->Sha1Sum;
    if(!is_dir($dirName))
      {
      $this->fail("Directory $dirName was not created");
      return;
      }
    if(!file_exists($dirName.'/'.$this->Sha1Sum))
      {
      $this->fail("File contents were not written to $dirName/$this->Sha1Sum");
      return;
      }
    if(!file_exists($dirName.'/CMakeCache.txt'))
      {
      $this->fail("File symlink was not written to $dirName/CMakeCache.txt");
      return;
      }
    $this->pass('Uploaded file exists in database and on disk');
    }
}

?>
