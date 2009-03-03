<?php
/**
     *   XML reporter specific to use CDash
     *   Allows :
     *            *   a user to test his php code and
     *            send to the dashboard.
     *            *   update his working repository before
     *            to send his test to cdash
     */
require_once(dirname(__FILE__) . '/simpletest/xml.php');
class CDashXmlReporter extends XmlReporter
{
  // Configuration parameters
  var $_configure         = null;
  // The file resource that is returned by the system
  // when we create/open/write into a file
  var $_testfile          = null;
  // The file resource that is returned by the system
  // when we create/open/write into a file
  var $_buildfile         = null;
  // The file resource that is returned by the system
  // when we create/open/write into a file
  var $_updatefile        = null;
  // The file resource that is returned by the system
  // when we create/open/write into a file
  var $_configurefile     = null; 
  // The test name (required for CDash)
  var $_testname          = null;
  // The path (to the directory uppon the test file)
  var $_testpath          = null;
  // The number of the testfile associated to the file
  // which iscurrently running
  var $_testN             = null;
  // The number of the method test
  var $_methodN           = null;
  // The time in sec at the begining of a test method
  // (required by CDash to compute the execution time)
  var $_start_method_time = null;
  // The execution time associate to a test method (required by CDash)
  var $_execution_time    = null;
  // The cumulative execution time in sec (required by CDash in minute)
  var $_elapsedminutes    = null;
  // The status of a test (passed or failed <=> Completed //
  //                       error or exception <=> Interrupted == Uncompleted)
  var $_teststatus        = null;
  // The status of a test (passed or failed <=> Completed //
  //                       error or exception <=> Interrupted == Uncompleted)
  var $_configurestatus   = null;
  // Flag to control if the data has already sent to cdash or not
  var $_fileclosed       = null;

  /**
     * the constructor : initialize the variables that needs to be initialized
     *                   create/open the xml test file
     */
  function __construct($configure)
    {
    parent::__construct();
    $this->_configure      = $configure;
    $filename              = $this->_configure['outputdirectory'].'/Test.xml';
    $this->_testfile       = fopen($filename,'w+');
    $filename              = $this->_configure['outputdirectory'].'/Build.xml';
    $this->_buildfile      = fopen($filename,'w+');
    $filename              = $this->_configure['outputdirectory'].'/Update.xml';
    $this->_updatefile     = fopen($filename,'w+');
    $filename              = $this->_configure['outputdirectory'].'/Configure.xml';
    $this->_configurefile  = fopen($filename,'w+');
    $this->_testpath       = dirname(dirname(__FILE__));
    $this->_testN          = 0;
    $this->_methodN        = 0;
    $this->_elapsedminutes = 0;
    $this->paintStartCDashTest();
    $this->paintGetDateTime();
    }

  /**
     * the destructor : close the xml file
     */
  function __destruct()
    {
    if(!$this->_fileclosed)
      {
      $this->close();
      }
    }
  
  
  /**
   * Finish to paint the build and testing file.
   * Then close all the file
   */
  function close()
    {
    $this->_fileclosed = true;
    $this->paintEndCDashTest();
    fclose($this->_testfile);
    fclose($this->_buildfile);
    fclose($this->_updatefile);
    fclose($this->_configurefile);
    }

  /**
     * paintStartCDashTest: print into the file the common begining of
     *                      all xml cdash file
     */
  function paintStartCDashTest()
    {
    $this->paintHeader();
    $this->paintSiteTag();
    fwrite($this->_testfile, "<".$this->_namespace."Testing>\n");
    fwrite($this->_buildfile, "<".$this->_namespace."Build>\n");
    fwrite($this->_configurefile, "<".$this->_namespace."Configure>\n");
    }

  function paintSiteTag()
    {
    $this->_paintSiteTag($this->_testfile);
    $this->_paintSiteTag($this->_buildfile);
    $this->_paintSiteTag($this->_configurefile);
    }

  function _paintSiteTag($resourcefile)
    {
    $buildname    = 'BuildName="'.$this->_configure['buildname'].'"';
    if(!strcmp($this->_configure['type'],'Nightly'))
      {
      $tag = '0100';
      }
    else
      {
      $tag = date("Hi");
      }
    $builtstamp   = 'BuildStamp="'.date("Ymd").'-'.$tag.'-';
    $builtstamp  .= $this->_configure['type'].'"';
    $name         = 'Name="'.$this->_configure['site'].'"';
    $generator    = 'Generator="simpletest1.0.1"';
    fwrite($resourcefile,"<".$this->_namespace."Site $buildname $builtstamp ");
    fwrite($resourcefile,"$name $generator>\n");
    }

  /**
     * paintHeader: print the xml header for xml cdash file
     */
  function paintHeader($test_name = NULL)
    {
    $this->_paintHeader($this->_testfile);
    $this->_paintHeader($this->_buildfile);
    $this->_paintHeader($this->_updatefile);
    $this->_paintHeader($this->_configurefile);
    }

  /**
     * _paintHeader: print the xml header
     */
  function _paintHeader($resourcefile)
    {
    fwrite($resourcefile,'<?xml version="1.0" encoding="UTF-8"?>'."\n");
    }

  /**
     * paintGetDateTime: print the time when the test is starting
     */
  function paintGetDateTime()
    {
    $this->_paintGetDateTime($this->_testfile);
    $this->_paintGetDateTime($this->_buildfile);
    $this->_paintGetDateTime($this->_configurefile);
    $this->_paintBuildInformation($this->_buildfile);
    }

  /**
     * paintGetDateTime: print the time when the test is starting
     */
  function _paintGetDateTime($resourcefile)
    {
    fwrite($resourcefile, $this->_getIndent(1));
    fwrite($resourcefile, "<".$this->_namespace."StartDateTime>");
    fwrite($resourcefile,date("M d G:i T"));
    fwrite($resourcefile, "</StartDateTime>\n");
    }


  /**
     *    paint all the information related to the build condition
     *    @param string $test_name   Name of test that is starting.
     *    @access protected
     */
  function _paintBuildInformation($resourcefile)
    {
    fwrite($resourcefile, $this->_getIndent(1));
    fwrite($resourcefile,"<".$this->_namespace."StartBuildTime>".time()."</StartBuildTime>\n");
    fwrite($resourcefile, $this->_getIndent(1));
    fwrite($resourcefile,"<".$this->_namespace."BuildCommand>php5 ".$this->_testpath."/alltests.php </BuildCommand>\n");
    }

  /**
     * paintTestCaseList: print the list of the test into the tag <TestList>
     */
   function paintTestCaseList($List)
     {
     fwrite($this->_testfile, $this->_getIndent(1));
     fwrite($this->_testfile, "<".$this->_namespace."TestList>\n");
     $this->_testpath = array();
     foreach($List as $key=>$test)
       {
       $this->paintTestFunction($key);
       $this->_testpath[] = $key;
       }
     fwrite($this->_testfile, $this->_getIndent(1));
     fwrite($this->_testfile, "</TestList>\n");
     }

  /**
     * paintTestCasefunction: print each name test into <Test> tag
     */
   function paintTestFunction($path_to_test)
     {
       $methods = $this->getTestFunction($path_to_test);
       foreach($methods as $method)
         {
         fwrite($this->_testfile, $this->_getIndent(2));
         fwrite($this->_testfile, "<".$this->_namespace."Test>$method</Test>\n");
         }
     }

  function paintConfigureStart(){
     fwrite($this->_configurefile, $this->_getIndent(1));
     fwrite($this->_configurefile, "<".$this->_namespace."StartConfigureTime>".time()."</StartConfigureTime>\n");
     fwrite($this->_configurefile, $this->_getIndent(1));
     fwrite($this->_configurefile, "<".$this->_namespace."ConfigureCommand>");
     fwrite($this->_configurefile, "php5 ".$this->_testpath."/alltests.php </ConfigureCommand>\n");
     fwrite($this->_configurefile, $this->_getIndent(1));
     fwrite($this->_configurefile, "<".$this->_namespace."Log>\n");
  }
  
  function paintConfigureUninstallResult($result)
    {
    $this->__paintConfigureResult($result,'drop');
    return;
    }
  
  function paintConfigureInstallResult($result)
    {
    $this->__paintConfigureResult($result,'create');
    return;
    }
  
  function __paintConfigureResult($result,$action)
    {
    fwrite($this->_configurefile, $this->_getIndent(2));
    if(!$result)
      {
      $this->_configurestatus = -1;
      fwrite($this->_configurefile, "Process to $action database has failed\n");
      return;
      }
    if($this->_configurestatus == -1)
      {
      fwrite($this->_configurefile, "Process to $action database has succeeded\n");
      return;
      }
    $this->_configurestatus = 0;
    fwrite($this->_configurefile, "Process to $action database has succeeded\n");
    return;
    }
  
  function paintConfigureConnection($result)
  {
    fwrite($this->_configurefile, $this->_getIndent(2));
    if(!$result)
      {
      fwrite($this->_configurefile, "Connection attempt to database has failed\n");
      }
    else
      {
      fwrite($this->_configurefile, "Connection attempt to database has succeded\n");
      }
    return;
  }
    
  function paintConfigureEnd($elapsedMinutes)
   {
   fwrite($this->_configurefile, $this->_getIndent(1));
   fwrite($this->_configurefile, "<".$this->_namespace."/Log>\n");
   fwrite($this->_configurefile, $this->_getIndent(1));
   fwrite($this->_configurefile, "<".$this->_namespace."ConfigureStatus>".$this->_configurestatus."</ConfigureStatus>\n");
   fwrite($this->_configurefile, $this->_getIndent(1));
   fwrite($this->_configurefile, "<EndDateTime>".date("M d G:i T")."</EndDateTime>\n");
   fwrite($this->_configurefile, $this->_getIndent(1));
   fwrite($this->_configurefile, "<".$this->_namespace."EndConfigureTime>".time()."</EndConfigureTime>\n");
   fwrite($this->_configurefile, $this->_getIndent(1));
   fwrite($this->_configurefile, "<".$this->_namespace."ElapsedMinutes>".$elapsedMinutes."</ElapsedMinutes>\n");
   fwrite($this->_configurefile, "<".$this->_namespace."/Configure>\n");
   fwrite($this->_configurefile, "<".$this->_namespace."/Site>\n");
   }
  
  
     /**
     *     paint the start of the update.xml for CDash 
     */
    function paintUpdateStart(){
      fwrite($this->_updatefile,"<".$this->_namespace.'Update mode="Client" Generator="simpletest1.0.1"'.">\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."Site>".$this->_configure['site']."</Site>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."BuildName>".$this->_configure['buildname']."</BuildName>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."BuildStamp>".date("Ymd").'-0100-'.$this->_configure['type']."</BuildStamp>\n");
      $this->_paintGetDateTime($this->_updatefile);
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."StartTime>".time()."</StartTime>\n");
      // We get the command line
      $commandline = `which svn`;
      $commandline = explode("\n", $commandline);
      $commandline = '"'.$commandline[0].'"';
      $commandline .= ' info';
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."UpdateCommand>".$commandline."</UpdateCommand>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."UpdateType>SVN</UpdateType>\n");
    }
    
    function paintUpdateFile($xmlstr){
      $xml = new SimpleXMLElement($xmlstr);
      $sum = array();
      foreach($xml->logentry as $entry)
        {
        foreach($entry->paths->path as $file)
          {
          $pathinfo = pathinfo($file);
          $pathdir  = str_replace('/trunk/','',$pathinfo['dirname']);
          $filename = $pathinfo['basename'];
          $date = strtok($entry->date,'.');
          $author = $entry->author;
          $revision = $entry['revision']; 
          $this->__paintUpdateDirectory($pathdir,$filename,$date,$author,$revision);
          $sum[(string)$entry->author][] = $pathdir.'/'.$filename ;
          }
        }
      foreach($sum as $key => $author)
        {
        $this->__paintUpdateAuthor($key,$author);
        }
    }
    
    /**
    *     paint the end of the update.xml for CDash 
    */
    function paintUpdateEnd($elapsedMinutes){
      $updatestatus = '';
      if(!is_numeric($elapsedMinutes))
        {
        $updatestatus = -1;
        $elapsedMinutes = 0;
        // TODO: write the log error and update
        }
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."EndDateTime>".date("M d G:i T")."</EndDateTime>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."EndTime>".time()."</EndTime>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."ElapsedMinutes>".$elapsedMinutes."</ElapsedMinutes>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."UpdateReturnStatus>$updatestatus</UpdateReturnStatus>\n");
      fwrite($this->_updatefile,"<".$this->_namespace."/Update>\n");
    }
    
   function __paintUpdateDirectory($path,$filename,$date,$author,$revision)
   {
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."Directory>\n");
      fwrite($this->_updatefile, $this->_getIndent(2));
      fwrite($this->_updatefile,"<".$this->_namespace."Name>$path</Name>\n");
      fwrite($this->_updatefile, $this->_getIndent(2));
      fwrite($this->_updatefile,"<".$this->_namespace."Updated>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace.'File Directory="'.$path.'">'.$filename."</File>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."Directory>$path</Directory>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."FullName>$path/$filename</FullName>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."CheckinDate>$date</CheckinDate>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."Author>$author</Author>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."Email>Unknown</Email>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."Log></Log>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."Revision>$revision</Revision>\n");
      fwrite($this->_updatefile, $this->_getIndent(3));
      fwrite($this->_updatefile,"<".$this->_namespace."PriorRevision>$revision</PriorRevision>\n");
      fwrite($this->_updatefile, $this->_getIndent(2));
      fwrite($this->_updatefile,"<".$this->_namespace."/Updated>\n");
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."/Directory>\n");
   }
   
   function __paintUpdateAuthor($author,$files)
   {
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."Author>\n");
      fwrite($this->_updatefile, $this->_getIndent(2));
      fwrite($this->_updatefile,"<".$this->_namespace."Name>$author</Name>\n");
      foreach($files as $file)
        {
        fwrite($this->_updatefile, $this->_getIndent(2));
        $directory = substr($file,0,strrpos($file,'/'));
        $filename  = substr($file,strrpos($file,'/')+1);
        fwrite($this->_updatefile,"<".$this->_namespace.'File Directory="'.$directory.'">'.$filename."</File>\n");
        }
      fwrite($this->_updatefile, $this->_getIndent(1));
      fwrite($this->_updatefile,"<".$this->_namespace."/Author>\n");
   }

  
  
  
  /**
     *    Paints nothing for the start of a group test. For CDash, we
     *    do not need this option
     *    @param string $test_name   Name of test that is starting.
     *    @param integer $size       Number of test cases starting.
     *    @access public
     */
  function paintGroupStart($test_name, $size)
    {
    }


  /**
     *    Paints nothing for the end of a group test. For CDash, we
     *    do not need this option
     *    @param string $test_name   Name of test that is ending.
     *    @access public
     */
    function paintGroupEnd($test_name)
      {
      }

  /**
     *    Paints nothing for the end of a test case. For CDash, we
     *    do not need this option
     *    @param string $test_name   Name of test that is starting.
     *    @access public
     */
    function paintCaseStart($test_name)
      {
      }

  /**
     *    Paints nothing for the end of a test case. For CDash, we
     *    do not need this option
     *    @param string $test_name   Name of test that is ending.
     *    @access public
     */
    function paintCaseEnd($test_name)
      {
      ++$this->_testN;
      }



  /**
     *    Paints the start of a test method.
     *    @param string $test_name   Name of test that is starting.
     *    @access public
     */
    function paintMethodStart($test_name) {
      echo $this->_methodN."/ Testing $test_name";
      $this->_testname       = $test_name;
      $this->_start_method_time = (float) array_sum(explode(' ',microtime()));
    }

  /**
     *    Paints the end of a test method.
     *    @param string $test_name   Name of test that is ending.
     *    @param integer $progress   Number of test cases ending.
     *    @access public
     */
    function paintMethodEnd($test_name) {
        $this->_testname = '';
        $this->_methodN++; 
      }
  

  /**
     *    Paints pass as XML.
     *    @param string $message        Message to encode.
     *    @access public
     */
    function paintPass($message) {
      $this->computeTimeExecution();
      $this->_teststatus = "Completed";
      if (!is_null($this->_testfile))
        {
        fwrite($this->_testfile, $this->_getIndent(1));
        fwrite($this->_testfile, "<" . $this->_namespace . "Test Status=".'"passed">'."\n");
        $this->_paintTestInfo($message);
        fwrite($this->_testfile, $this->_getIndent(1));
        fwrite($this->_testfile, "</" . $this->_namespace . "Test>\n");
        }
      echo "... Passed\n";
    }

  /**
     *    Paints failure as XML.
     *    @param string $message        Message to encode.
     *    @access public
     */
    function paintFail($message) {
        $this->computeTimeExecution();
        $this->_teststatus = "Completed";
        if (!is_null($this->_testfile))
          {
          fwrite($this->_testfile, $this->_getIndent(1));
          fwrite($this->_testfile, "<" . $this->_namespace . "Test Status=".'"failed">'."\n");
          if(strpos($message,"Text [error] detected at character") !== false)
            {
            $errormessage = $message;
            $message = "Fatals errors (cf build)";
            $errors = true;
            }
          elseif(strpos($message,"Text [Notice]") !== false)
            {
            $warningmessage = $message;
            $message = "Warnings (cf build)";
            $warnings = true;
            }
          $this->_paintTestInfo($message);
          fwrite($this->_testfile, $this->_getIndent(1));
          fwrite($this->_testfile, "</" . $this->_namespace . "Test>\n");
          }
        if(isset($errors))
          {
          $this->_extractError($errormessage);
          }
        if(isset($warnings))
          {
          $this->_extractWarning($warningmessage);
          }
      echo "... **** Failed\n";
    }


  /**
     *    Extract an error from a warning (useful with web test cases)
     *    @param string $message        Message to encode.
     *    @access protected
     */
  function _extractError($message)
  {
    $errors = explode('[String:',$message);
    for($i=1;$i<sizeof($errors);$i++)
      {
      $this->paintError(strstr($errors[$i],' '));
      }
  }

  /**
     *    Extract an error from a warning (useful with web test cases)
     *    @param string $message        Message to encode.
     *    @access protected
     */
  function _extractWarning($message)
  {
    $warnings = explode('Notice (',$message);
    for($i=1;$i<sizeof($warnings);$i++)
    {
      $this->paintWarning(strstr($warnings[$i],' '));
    }
  }

  /**
     *    Paints error as CDAsh XML format.
     *    @param string $message        Message to encode.
     *    @access public
     */
   function paintError($message) {
        $this->computeTimeExecution();
        $this->_teststatus = "Uncompleted";
        $this->_updateStatus($this->_teststatus);
        $this->_paintErrorInfo($message);
    }


  /**
     *    Paints warnings as CDAsh XML format.
     *    @param string $message        Message to encode.
     *    @access public
     */
   function paintWarning($message) {
      $this->computeTimeExecution();
      $this->_teststatus = "Uncompleted";
      $this->_updateStatus($this->_teststatus);
      $this->_paintWarningInfo($message);
   }

  /**
     *    Paints errors information
     *    @param string $message        Message to encode.
     *    @access protected
     */
  function _paintErrorInfo($message)
    {
    if (!is_null($this->_testfile))
      {
      fwrite($this->_buildfile, $this->_getIndent(1));
      fwrite($this->_buildfile, "<" . $this->_namespace . "Error>\n");
      fwrite($this->_buildfile, $this->_getIndent(2));
      $errorpos = strrpos($message,"in [");
      $errorline = substr($message,$errorpos+strlen("in ["),-1);
      fwrite($this->_buildfile, "<" . $this->_namespace . "BuildLogLine>$errorline</BuildLogLine>\n");
      fwrite($this->_buildfile, $this->_getIndent(2));
      fwrite($this->_buildfile, "<" . $this->_namespace . "Text>".$this->toParsedXml($message)."</Text>\n");
      fwrite($this->_buildfile, $this->_getIndent(2));
      fwrite($this->_buildfile, "<" . $this->_namespace . "PreContext></PreContext>\n");
      fwrite($this->_buildfile, $this->_getIndent(2));
      fwrite($this->_buildfile, "<" . $this->_namespace . "PostContext></PostContext>\n");
      fwrite($this->_buildfile, $this->_getIndent(2));
      fwrite($this->_buildfile, "<" . $this->_namespace . "RepeatCount>0</RepeatCount>\n");
      fwrite($this->_buildfile, $this->_getIndent(1));
      fwrite($this->_buildfile, "</" . $this->_namespace . "Error>\n");
      }
    }


  /**
      *    Paints warnings information
      *    @param string $message        Message to encode.
      *    @access protected
      */
    function _paintWarningInfo($message)
    {
      if (!is_null($this->_testfile))
      {
        fwrite($this->_buildfile, $this->_getIndent(1));
        fwrite($this->_buildfile, "<" . $this->_namespace . "Warning>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        $errorpos = strrpos($message,"at [");
        $errorline = substr($message,$errorpos+strlen("at ["),-1);
        fwrite($this->_buildfile, "<" . $this->_namespace . "BuildLogLine>$errorline</BuildLogLine>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "Text>".$this->toParsedXml($message)."</Text>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "PreContext></PreContext>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "PostContext></PostContext>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "RepeatCount>0</RepeatCount>\n");
        fwrite($this->_buildfile, $this->_getIndent(1));
        fwrite($this->_buildfile, "</" . $this->_namespace . "Warning>\n");
      }
    }

  /**
     *    Paints exception as CDAsh XML format.
     *    @param string $message        Message to encode.
     *    @access public
     */
    function paintException($exception) {
        $this->computeTimeExecution();
        $this->_teststatus = "Uncompleted";
        $this->_updateStatus($this->_teststatus);
        $this->_paintExceptionInfo($exception);
    }

  /**
     *    Paints errors information
     *    @param string $message        Message to encode.
     *    @access protected
     */
    function _paintExceptionInfo()
      {
      if (!is_null($this->_testfile))
        {
        fwrite($this->_buildfile, $this->_getIndent(1));
        fwrite($this->_buildfile, "<" . $this->_namespace . "Warning>");
        $message = 'Unexpected exception of type [' . get_class($exception) .
                '] with message ['. $exception->getMessage() .
                '] in ['. $exception->getFile() .
                ' line ' . $exception->getLine() . ']';
        fwrite($this->_buildfile, "<" . $this->_namespace . "BuildLogLine>".$exception->getLine()."</BuildLogLine>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "Text>".$this->toParsedXml($message)."</Text>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "PreContext></PreContext>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "PostContext></PostContext>\n");
        fwrite($this->_buildfile, $this->_getIndent(2));
        fwrite($this->_buildfile, "<" . $this->_namespace . "RepeatCount>0</RepeatCount>\n");
        fwrite($this->_buildfile, $this->_getIndent(1));
        fwrite($this->_buildfile, "</" . $this->_namespace . "Warning\n");
        }
      }

  /**
     *    print all the information related to the test
     *    @param message $output = output of the test
     *    @access protected
     */
  function _paintTestInfo($output)
    {
    fwrite($this->_testfile, $this->_getIndent(2));
    fwrite($this->_testfile, "<" . $this->_namespace . "Name>".$this->_testname.
            "</Name>\n");
    fwrite($this->_testfile, $this->_getIndent(2));
    $fullPath = $this->_testpath[$this->_testN];
    $path = dirname($fullPath);
    fwrite($this->_testfile, "<" . $this->_namespace . "Path>".$path."</Path>\n");
    fwrite($this->_testfile, $this->_getIndent(2));
    fwrite($this->_testfile, "<" . $this->_namespace . "FullName>".$fullPath."</FullName>\n");
    fwrite($this->_testfile, $this->_getIndent(2));
    fwrite($this->_testfile, "<" . $this->_namespace . "FullCommandLine>php5 ".$this->_testpath[$this->_testN]."</FullCommandLine>\n");
    fwrite($this->_testfile, $this->_getIndent(2));
    fwrite($this->_testfile, "<" . $this->_namespace . "Results>\n");
    fwrite($this->_testfile, $this->_getIndent(3));
    fwrite($this->_testfile, "<" . $this->_namespace . "NamedMeasurement type=".'"numeric/double"'." name=".'"Execution Time"><Value>'.$this->_execution_time."</Value></NamedMeasurement>\n");
    fwrite($this->_testfile, $this->_getIndent(3));
    fwrite($this->_testfile, "<" . $this->_namespace . "NamedMeasurement type=".'"text/string"'." name=".'"Completion Status"><Value>'.$this->_teststatus."</Value></NamedMeasurement>\n");
    fwrite($this->_testfile, $this->_getIndent(3));
    fwrite($this->_testfile, "<" . $this->_namespace . "NamedMeasurement type=".'"text/string"'." name=".'"Command Line"><Value>php5 '.$this->_testpath[$this->_testN]."</Value></NamedMeasurement>\n");
    fwrite($this->_testfile, $this->_getIndent(3));
    fwrite($this->_testfile, "<" . $this->_namespace . "Measurement>\n");
    fwrite($this->_testfile, $this->_getIndent(4));
    fwrite($this->_testfile, "<" . $this->_namespace . "Value>".$this->toParsedXml($output)."</Value>\n");
    fwrite($this->_testfile, $this->_getIndent(3));
    fwrite($this->_testfile, "<" . $this->_namespace . "/Measurement>\n");
    fwrite($this->_testfile, $this->_getIndent(2));
    fwrite($this->_testfile, "</" . $this->_namespace . "Results>\n");
    }

  /**
     *    Update the last status to the new status
     *    @param message $newstatus = status to update
     *    @access protected
     */
  function _updateStatus($newstatus)
    {
    $beforeteststatus = '<Test Status="';
    $afterteststatus  = '<Name>';
    $toappend         = 'NotRun"'.">\n".$this->_getIndent(2);
    $filename         =  $this->_configure['outputdirectory'].'/Test.xml';
    $contents         = '';
    rewind($this->_testfile);
    while(!feof($this->_testfile))
      {
      $contents .= fgets($this->_testfile);
      }
    // We replace the test status
    $contentsbeforestatus = substr($contents,0,strrpos($contents,$beforeteststatus)+strlen($beforeteststatus));
    $contentsafterstatus  = substr($contents,strrpos($contents,$afterteststatus));
    $contents = $contentsbeforestatus.$toappend.$contentsafterstatus;
    rewind($this->_testfile);
    fwrite($this->_testfile,$contents);
    }


  /**
     *    paintEndCDashTest
     *    print the common end of all xml cdash file
     */
  function paintEndCDashTest()
    {
    fwrite($this->_buildfile, $this->_getIndent(1));  
    fwrite($this->_buildfile, "<" . $this->_namespace . 'Log Encoding="'."base64");
    fwrite($this->_buildfile, '" Compression="'."/bin/gzip".'">'."\n");
    fwrite($this->_buildfile, $this->_getIndent(1));
    fwrite($this->_buildfile, "<" . $this->_namespace . "/Log>\n");
    $this->__paintEndCdashTag($this->_testfile,'Test');
    $this->__paintEndCdashTag($this->_buildfile,'Build');
    }
  
  
 /**
    * paint the last tag for cdash test     
    * @param resource $resourcefile
    * @param string $type
    */
 function __paintEndCdashTag($resourcefile,$type)
    {
    fwrite($resourcefile, $this->_getIndent(1));
    fwrite($resourcefile, "<" . $this->_namespace . "EndDateTime>".date("M d G:i T")."</EndDateTime>\n");
    fwrite($resourcefile, $this->_getIndent(1));
    fwrite($resourcefile, "<" . $this->_namespace . "End".$type."Time>".time()."</End".$type."Time>\n");
    fwrite($resourcefile, $this->_getIndent(1));
    $elapsedMinutes = round($this->_elapsedminutes / 60 , 3);
    fwrite($resourcefile, "<" . $this->_namespace ."ElapsedMinutes>".$elapsedMinutes."</ElapsedMinutes>\n");
    if(!strcmp($type,'Test'))
      {
      $type = 'Testing';
      }
    fwrite($resourcefile, "</$type>\n");
    fwrite($resourcefile, "</Site>\n");
    }

  /**
     *    getTestFunction
     *    return all the test funtion contained in the test file name
     */
  function getTestFunction($testFileName)
    {
    $testFileName = realpath($testFileName);
    require_once($testFileName);
    $testFile = fopen($testFileName,'r');
    $content  = fread($testFile,filesize($testFileName));
    fclose($testFile);
    $str = explode("class ",$content);
    $haystack = $str[1];
    $str = explode(" extends",$haystack);
    $obj = $str[0];
    $testcase = new $obj();
    $methods = $testcase->getTests();
    $testFunction = array();
    foreach($methods as $method)
      {
      if(strcmp($method,'start') != 0 &&
         strcmp($method,'startCase') != 0 &&
         strcmp($method,'endCase') != 0 &&
         strcmp($method,'end') != 0)
        {
        $testFunction[] = $method;
        }
      }
    return $testFunction;
    }
  
  /**
     *    Converts character string to parsed XML
     *    entities string.
     *    @param string text        Unparsed character data.
     *    @return string            Parsed character data.
     *    @access public
     */
   function toParsedXml($text) {
        $text = parent::toParsedXml($text);
        return str_replace(array('&nbsp;','&copy;'),array(' ','©'),htmlentities($text,ENT_QUOTES));
    }

  
  
  /**
     *    computeTimeExecution
     *    compute the time execution
     */
  function computeTimeExecution()
  {
    $now = (float) array_sum(explode(' ',microtime()));
    $this->_execution_time = $now - $this->_start_method_time;
    $this->_elapsedminutes += $this->_execution_time;
  }
}
?>
