<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved. 
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
if (PHP_VERSION >= 5) {
    // Emulate the old xslt library functions
    function xslt_create() {
        return new XsltProcessor();
    }

    function xslt_process($xsltproc,
                          $xml_arg,
                          $xsl_arg,
                          $xslcontainer = null,
                          $args = null,
                          $params = null) {
        // Start with preparing the arguments
        $xml_arg = str_replace('arg:', '', $xml_arg);
        $xsl_arg = str_replace('arg:', '', $xsl_arg);

        // Create instances of the DomDocument class
        $xml = new DomDocument;
        $xsl = new DomDocument;

        // Load the xml document and the xsl template
        $xml->loadXML($args[$xml_arg]);
        $xsl->loadXML(file_get_contents($xsl_arg));

        // Load the xsl template
        $xsltproc->importStyleSheet($xsl);

        // Set parameters when defined
        if ($params) {
            foreach ($params as $param => $value) {
                $xsltproc->setParameter("", $param, $value);
            }
        }

        // Start the transformation
        $processed = $xsltproc->transformToXML($xml);

        // Put the result in a file when specified
        if ($xslcontainer) {
            return @file_put_contents($xslcontainer, $processed);
        } else {
            return $processed;
        }

    }

    function xslt_free($xsltproc) {
        unset($xsltproc);
    }
}
  
/** Do the XSLT translation and look in the local directory if the file
 *  doesn't exist */
function generate_XSLT($xml,$pageName)
{
  $xh = xslt_create();
  
  if(PHP_VERSION < 5)
    { 
    $filebase = 'file://' . getcwd () . '/';
    xslt_set_base($xh,$filebase);
    }

  $arguments = array (
    '/_xml' => $xml
  );

  $xslpage = $pageName.".xsl";
  
  // Check if the page exists in the local directory
  if(file_exists("local/".$xslpage))
    {
    $xslpage = "local/".$xslpage;
    }
 
  $html = xslt_process($xh, 'arg:/_xml', $xslpage, NULL, $arguments);
  
  echo $html;
  
  xslt_free($xh);
}

/**used to escape special XML characters*/
$asc2uni = Array();
for($i=128;$i<256;$i++){
  $asc2uni[chr($i)] = "&#x".dechex($i).";";   
}

/** used to escape special XML characters */
function XMLStrFormat($str){
  global $asc2uni;
  $str = str_replace("&", "&amp;", $str);
  $str = str_replace("<", "&lt;", $str); 
  $str = str_replace(">", "&gt;", $str); 
  $str = str_replace("'", "&apos;", $str);  
  $str = str_replace("\"", "&quot;", $str); 
  $str = str_replace("\r", "", $str);
  $str = strtr($str,$asc2uni);
  return $str;
}

/** Microtime function */
function microtime_float()
  {
  list($usec, $sec) = explode(" ", microtime());
  return ((float)$usec + (float)$sec);
  }

/** Add an XML tag to a string */
function add_XML_value($tag,$value)
{
  return "<".$tag.">".XMLStrFormat($value)."</".$tag.">";
}

/** Add information to the log file */
function add_log($text,$function)
{
  include("config.php");
  $error = "[".date("Y-m-d H:i:s")."] (".$function."): ".$text."\n";  
  error_log($error,3,$CDASH_LOG_FILE);
}

/** Report last my SQL error */
function add_last_sql_error($functionname)
{
  $mysql_error = mysql_error();
 if(strlen($mysql_error)>0)
   {
   add_log("SQL error: ".$mysql_error."\n",$functionname);
    $text = "SQL error in $functionname():".$mysql_error."<br>";
    echo $text;
  }
}

/** Return true if the user is allowed to see the page */
function checkUserPolicy($userid,$projectid,$onlyreturn=0)
{
  if(($userid!= '' && !is_numeric($userid)) || !is_numeric($projectid))
    {
    return;
    }
    
  // If the projectid=0 only admin can access the page
  if($projectid==0 && mysql_num_rows(mysql_query("SELECT admin FROM user WHERE id='$userid' AND admin='1'"))==0)
    {
    if(!$onlyreturn)
      {
      echo "You cannot access this page";
      exit(0);
      }
    else
      {
      return false;
      }
    }
  else if(@$projectid > 0)
    {
    $project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
    $project_array = mysql_fetch_array($project);
    
    // If the project is public we quit
    if($project_array["public"]==1)
      {
      return true;
      }
    
    // If the project is private and the user is not logged in we quit
    if(!$userid && $project_array["public"]!=1)
      {
      if(!$onlyreturn)
        {
        echo "You cannot access this page";
        exit(0);
        }
      else
        {
        return false;
        }
      }
    else if($userid)
      {
      $user2project = mysql_query("SELECT projectid FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
      if(mysql_num_rows($user2project) == 0)
        {
        if(!$onlyreturn)
          {
          echo "You cannot access this project";
          exit(0);
          }
        else
          {
          return false;
          }
        }
      }
    } // end project=0

  return true;
}

/** Clean the backup directory */
function clean_backup_directory()
{   
  include("config.php");
  foreach (glob($CDASH_BACKUP_DIRECTORY."/*.xml") as $filename) 
    {
    if(time()-filemtime($filename) > $CDASH_BACKUP_TIMEFRAME*3600)
      {
      unlink($filename);
      }
    }
}

/** Parse the XML file and returns an array */
function parse_XML($contents)
{
  $p = xml_parser_create();
  if(!xml_parse_into_struct($p, $contents, $vals, $index))
   {
   add_log("Cannot parse XML".xml_error_string(xml_get_error_code($p)),"parse_XML");
   }
  
  // create a parse struct with vals and index in it
  $parse->vals = $vals;
  $parse->index = $index;
  
  xml_parser_free($p);
  return $parse;
}

/** Backup an XML file */
function backup_xml_file($parser,$contents,$projectid)
{
  
  // If the content of the file is empty we return
  if(strlen($contents)==0)
    {
    return; 
    }

  include("config.php");
   
  clean_backup_directory(); // should probably be run as a cronjob
 
  if(@$parser->index["BUILD"] != "")
    {
    $file = "Build.xml";
    }
  else if(@$parser->index["CONFIGURE"] != "")
    {
    $file = "Configure.xml";
    }
  else if(@$parser->index["TESTING"] != "")
    {
    $file = "Test.xml";
    }
  else if(@$parser->index["UPDATE"] != "")
    {
    $file = "Update.xml";
    }  
  else if(@$parser->index["COVERAGE"] != "")
    {
    $file = "Coverage.xml";
    } 
  else if(@$parser->index["COVERAGELOG"] != "")
    {
    $file = "CoverageLog.xml";
    }
  else if(@$parser->index["NOTES"] != "")
    {
    $file = "Notes.xml";
    }
  else if(@$parser->index["DYNAMICANALYSIS"] != "")
    {
    $file = "DynamicAnalysis.xml";
    } 
  else
    {
    $file = "Other.xml";
    }
  
 // For some reasons the update.xml has a different format
 if(@$parser->index["UPDATE"] != "")
   {
   $vals = $parser->vals;
   $sitename = getXMLValue($vals,"SITE","UPDATE");
   $name = getXMLValue($vals,"BUILDNAME","UPDATE");
   $stamp = getXMLValue($vals,"BUILDSTAMP","UPDATE");
   }
 else
   {
   $site = $parser->index["SITE"];
   $sitename = $parser->vals[$site[0]]["attributes"]["NAME"]; 
   $name = $parser->vals[$site[0]]["attributes"]["BUILDNAME"];
   $stamp = $parser->vals[$site[0]]["attributes"]["BUILDSTAMP"];
   }
 
 $filename = $CDASH_BACKUP_DIRECTORY."/".get_project_name($projectid)."_".$sitename."_".$name."_".$stamp."_".$file;
  
 // If the file is other we append a number until we get a non existing file
 $i=1;
 while($file=="Other.xml" && file_exists($filename))
   {
   $filename = $CDASH_BACKUP_DIRECTORY."/".get_project_name($projectid)."_".$sitename."_".$name."_".$stamp."_Other.".$i.".xml";
   $i++;
   }
   
 while($file=="CoverageLog.xml" && file_exists($filename))
   {
   $filename = $CDASH_BACKUP_DIRECTORY."/".get_project_name($projectid)."_".$sitename."_".$name."_".$stamp."_CoverageLog.".$i.".xml";
   $i++;
   }
   
  if (!$handle = fopen($filename, 'w')) 
    {
    echo "Cannot open file ($filename)";
    add_log("Cannot open file ($filename)", "backup_xml_file");
    return;
    }
  
  // Write the file.
  if (fwrite($handle, $contents) === FALSE)  
    {
    echo "Cannot write to file ($contents)";
    add_log("Cannot write to file ($$contents)", "backup_xml_file");
    fclose($handle);
    return;
    }
    
  fclose($handle);
}

/** return an array of projects */
function get_projects()
{
  $projects = array();
  
  include("config.php");

  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);

  $projectres = mysql_query("SELECT id,name FROM project WHERE public='1' ORDER BY name");
  while($project_array = mysql_fetch_array($projectres))
    {
    $project = array();
    $project['id'] = $project_array["id"];  
    $project['name'] = $project_array["name"];
    $projectid = $project['id'];
    
    $project['last_build'] = "NA";
    $lastbuildquery = mysql_query("SELECT submittime FROM build WHERE projectid='$projectid' ORDER BY submittime DESC LIMIT 1");
    if(mysql_num_rows($lastbuildquery)>0)
      {
      $lastbuild_array = mysql_fetch_array($lastbuildquery);
      $project['last_build'] = $lastbuild_array["submittime"];
      }

  $project['first_build'] = "NA";
    $firstbuildquery = mysql_query("SELECT starttime FROM build WHERE projectid='$projectid' AND starttime>'2000-01-01' ORDER BY starttime ASC LIMIT 1");
    if(mysql_num_rows($firstbuildquery)>0)
      {
      $firstbuild_array = mysql_fetch_array($firstbuildquery);
      $project['first_build'] = $firstbuild_array["starttime"];
      }

    $buildquery = mysql_query("SELECT count(id) FROM build WHERE projectid='$projectid'");
    $buildquery_array = mysql_fetch_array($buildquery); 
    $project['nbuilds'] = $buildquery_array[0];
    
    $projects[] = $project; 
    }
    
  return $projects;
}

/** Get the build id from stamp, name and buildname */
function get_build_id($buildname,$stamp,$projectid)
{  
  if(!is_numeric($projectid))
    {
    return;
    }
  
  $buildname = mysql_real_escape_string($buildname);
  $stamp = mysql_real_escape_string($stamp);
  
  $sql = "SELECT id FROM build WHERE name='$buildname' AND stamp='$stamp'";
  $sql .= " AND projectid='$projectid'"; 
  $sql .= " ORDER BY id DESC";
  $build = mysql_query($sql);
  if(mysql_num_rows($build)>0)
    {
    $build_array = mysql_fetch_array($build);
    return $build_array["id"];
    }
  return -1;
}

/** Get the project id from the project name */
function get_project_id($projectname)
{
  $projectname = mysql_real_escape_string($projectname);
  $project = mysql_query("SELECT id FROM project WHERE name='$projectname'");
  if(mysql_num_rows($project)>0)
    {
    $project_array = mysql_fetch_array($project);
    return $project_array["id"];
    }
  return -1;
}

/** Get the project name from the project id */
function get_project_name($projectid)
{
  if(!isset($projectid) || !is_numeric($projectid))
    {
    echo "Not a valid buildid!";
    return;
    }

  $project = mysql_query("SELECT name FROM project WHERE id='$projectid'");
  if(mysql_num_rows($project)>0)
    {
    $project_array = mysql_fetch_array($project);
    return $project_array["name"];
    }
    
  return "NA";
}

/** Add a new coverage */
function add_coveragesummary($buildid,$loctested,$locuntested)
{
  if(!is_numeric($buildid))
    {
    return;
    }
  if(!is_numeric($loctested))
    {
    return;
    }
  if(!is_numeric($locuntested))
    {
    return;
    }  
  mysql_query ("INSERT INTO coveragesummary (buildid,loctested,locuntested) 
                VALUES ('$buildid','$loctested','$locuntested')");
}

/** Send a coverage email */
function send_coverage_email($buildid,$fileid,$fullpath,$loctested,$locuntested,$branchstested,$branchsuntested,
                             $functionstested,$functionsuntested)
{
  include("config.php");
  if(!is_numeric($buildid))
    {
    return;
    }
  $build = mysql_query("SELECT projectid,name from build WHERE id='$buildid'");
  $build_array = mysql_fetch_array($build);
  $projectid = $build_array["projectid"];
  
  // Check if we should send the email  
  $project = mysql_query("SELECT name,coveragethreshold,emaillowcoverage FROM project WHERE id='$projectid'");
  $project_array = mysql_fetch_array($project);
  if($project_array["emaillowcoverage"] == 0)
    {
    return;
    }
    
  $coveragethreshold = $project_array["coveragethreshold"]; 
  $coveragemetric = 1;
   
  // Compute the coverage metric for bullseye
  if($branchstested>0 || $branchsuntested>0 || $functionstested>0 || $functionsuntested>0)
    { 
    // Metric coverage
    $metric = 0;
    if($functionstested+$functionsuntested>0)
      {
      $metric += $functionstested/($functionstested+$functionsuntested);
      }
    if($branchsuntested+$branchsuntested>0)
      {
      $metric += $branchsuntested/($branchstested+$branchsuntested);
      $metric /= 2.0;
      }
    $coveragemetric = $metric;
    $percentcoverage = $metric*100;
    }
  else // coverage metric for gcov
    {
    $coveragemetric = ($loctested+10)/($loctested+$locuntested+10);
    $percentcoverage = ($loctested/($loctested+$locuntested))*100;    
    }
  
  // If the coveragemetric is below the coverage threshold we send the email
  if($coveragemetric < ($coveragethreshold/100.0))
    {
    // Find the cvs user
    $filename = $fullpath;
    if(substr($filename,0,2) == "./")
      {
      $filename = substr($filename,2);
      }
    $sql = "SELECT updatefile.author from updatefile,build
                               WHERE updatefile.buildid=build.id AND build.projectid='$projectid'
                                AND updatefile.filename='$filename' ORDER BY revision DESC LIMIT 1";
    $updatefile = mysql_query($sql);
    
    // If we have a user in the database
    if(mysql_num_rows($updatefile)>0)
      {                             
      $updatefile_array = mysql_fetch_array($updatefile);
      $author = $updatefile_array["author"];
      
      // Writing the message
      $messagePlainText = "The file *".$filename."* of the project ".$project_array["name"];
      $messagePlainText .= " submitted to CDash has a low coverage.\n"; 
      $messagePlainText .= "You have been identified as one of the authors who have checked in changes to that file.\n";
      $messagePlainText .= "Details on the submission can be found at ";
  
      $currentURI =  "http://".$_SERVER['SERVER_NAME'] .$_SERVER['REQUEST_URI']; 
      $currentURI = substr($currentURI,0,strrpos($currentURI,"/"));
      $messagePlainText .= $currentURI;
      $messagePlainText .= "/viewCoverageFile.php?buildid=".$buildid;
      $messagePlainText .= "&fileid=".$fileid;
      $messagePlainText .= "\n\n";
      
      $messagePlainText .= "Project: ".$project_array["name"]."\n";
      $messagePlainText .= "BuildName: ".$build_array["name"]."\n";
      $messagePlainText .= "Filename: ".$fullpath."\n";
      $threshold = round($coveragemetric*100,1);
      $messagePlainText .= "Coverage metric: ".$threshold."%\n";
      $messagePlainText .= "Coverage percentage: ".$percentcoverage."%\n";
      $messagePlainText .= "CVS User: ".$author."\n";
      
      $messagePlainText .= "\n-CDash on ".$_SERVER['SERVER_NAME']."\n";
      
      // Send the email
      $title = "CDash [".$project_array["name"]."] - ".$fullpath." - Low Coverage";
     
      $email = "jomier@unc.edu";
      //mail("$email", $title, $messagePlainText,
     //      "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" );
      }
    }
}

/** Create a coverage */
function add_coverage($buildid,$coverage_array)
{
  if(!is_numeric($buildid))
    {
    return;
    }
 
  // Construct the SQL query
  $sql = "INSERT INTO coverage (buildid,fileid,covered,loctested,locuntested,branchstested,branchsuntested,functionstested,functionsuntested) VALUES ";
  
  $i=0;
  foreach($coverage_array as $coverage)
    {    
    $fullpath = $coverage["fullpath"];

    // Create an empty file if doesn't exists
    $coveragefile = mysql_query("SELECT id FROM coveragefile WHERE fullpath='$fullpath' AND file IS NULL");
    if(mysql_num_rows($coveragefile)==0)
      {
      // Do not compute the crc32, that means it's a temporary file
      // Only when the crc32 is computed it means that the file is valid
      mysql_query ("INSERT INTO coveragefile (fullpath) VALUES ('$fullpath')");
      $fileid = mysql_insert_id();
      }
    else
      {
      $coveragefile_array = mysql_fetch_array($coveragefile);
      $fileid = $coveragefile_array["id"];
      }
      
    // Create an empty file if doesn't exists
    /*$coveragefile = mysql_query("SELECT cf.id FROM coverage AS c,coveragefile AS cf 
                                 WHERE cf.id=c.fileid AND c.buildid='$buildid' AND cf.fullpath='$fullpath'");
    if(mysql_num_rows($coveragefile)==0)
      {
      mysql_query ("INSERT INTO coveragefile (fullpath) VALUES ('$fullpath')");
      $fileid = mysql_insert_id();
      }
    else
      {
      $coveragefile_array = mysql_fetch_array($coveragefile);
      $fileid = $coveragefile_array["id"];
      }*/
      
    
    $covered = $coverage["covered"];
    $loctested = $coverage["loctested"];
    $locuntested = $coverage["locuntested"];
    @$branchstested = $coverage["branchstested"];
    @$branchsuntested = $coverage["branchsuntested"];
    @$functionstested = $coverage["functionstested"];
    @$functionsuntested = $coverage["functionsuntested"];
    
    // Send an email if the coverage is below the project threshold
    send_coverage_email($buildid,$fileid,$fullpath,$loctested,$locuntested,$branchstested,
                        $branchsuntested,$functionstested,$functionsuntested);
   
    if($i>0)
      {
      $sql .= ", ";
      }
    else
      {
      $i=1;
      }
       
    $sql .= "('$buildid','$fileid','$covered','$loctested','$locuntested','$branchstested','$branchsuntested','$functionstested','$functionsuntested')";    
    }
  // Insert into coverage
  mysql_query($sql);
  add_last_sql_error("add_coverage");
}

/** Create a coverage file */
function add_coveragefile($buildid,$fullpath,$filecontent)
{
  if(!is_numeric($buildid))
    {
    return;
    }
    
   // Compute the crc32 of the file
  $crc32 = crc32($fullpath.$filecontent);
  
  $coveragefile = mysql_query("SELECT id FROM coveragefile WHERE crc32='$crc32'");
  add_last_sql_error("add_coveragefile");
    
  if(mysql_num_rows($coveragefile)>0) // we have the same crc32
    {
    $coveragefile_array = mysql_fetch_array($coveragefile);
    $fileid = $coveragefile_array["id"];

    // Update the current coverage.fileid
    $coverage = mysql_query("SELECT c.fileid FROM coverage AS c,coveragefile AS cf 
                             WHERE c.fileid=cf.id AND c.buildid='$buildid' 
                             AND cf.fullpath='$fullpath'");
    $coverage_array = mysql_fetch_array($coverage);
    $prevfileid = $coverage_array["fileid"];

    mysql_query ("UPDATE coverage SET fileid='$fileid' WHERE buildid='$buildid' AND fileid='$prevfileid'");
    add_last_sql_error("add_coveragefile");

    // Remove the file if the crc32 is NULL
    mysql_query ("DELETE FROM coveragefile WHERE id='$prevfileid' AND file IS NULL and crc32 IS NULL");
    add_last_sql_error("add_coveragefile");
    }
  else // The file doesn't exist in the database
    {
    // We find the current fileid based on the name and the file should be null
    $coveragefile = mysql_query("SELECT cf.id,cf.file FROM coverage AS c,coveragefile AS cf 
                                 WHERE c.fileid=cf.id AND c.buildid='$buildid' 
                                 AND cf.fullpath='$fullpath' ORDER BY cf.id ASC");
    $coveragefile_array = mysql_fetch_array($coveragefile);
    $fileid = $coveragefile_array["id"];
    mysql_query ("UPDATE coveragefile SET file='$filecontent',crc32='$crc32' WHERE id='$fileid'"); 
    add_last_sql_error("add_coveragefile");
    }
    
  return $fileid;
}

/** Add the coverage log */
function add_coveragelogfile($buildid,$fileid,$coveragelogarray)
{
  if(!is_numeric($buildid))
    {
    return;
    }
  if(!is_numeric($fileid))
    {
    return;
    }

  $sql = "INSERT INTO coveragefilelog (buildid,fileid,line,code) VALUES ";
  
  $i=0;
  foreach($coveragelogarray as $key=>$value)
     {
     if($i>0)
       {
       $sql .= ",";
       }  
      
     $sql.= "('$buildid','$fileid','$key','$value')";
     
     if($i==0)
       {
       $i++;
       }
     }
 
  mysql_query ($sql);
  add_last_sql_error("add_coveragelogfile");
}

/** add a user to a site */
function add_site2user($siteid,$userid)
{
  if(!is_numeric($siteid))
    {
    return;
    }
  if(!is_numeric($userid))
    {
    return;
    }
        
  $site2user = mysql_query("SELECT * FROM site2user WHERE siteid='$siteid' AND userid='$userid'");
  if(mysql_num_rows($site2user) == 0)
    {
    mysql_query("INSERT INTO site2user (siteid,userid) VALUES ('$siteid','$userid')");
    add_last_sql_error("add_site2user");
    }
}

/** remove a user to a site */
function remove_site2user($siteid,$userid)
{
  mysql_query("DELETE FROM site2user WHERE siteid='$siteid' AND userid='$userid'");
  add_last_sql_error("remove_site2user");
}

/** Update a site */
function update_site($siteid,$name,
           $processoris64bits,
           $processorvendor,
           $processorvendorid,
           $processorfamilyid,
           $processormodelid,
           $processorcachesize,
           $numberlogicalcpus,
           $numberphysicalcpus,
           $totalvirtualmemory,
           $totalphysicalmemory,
           $logicalprocessorsperphysical,
           $processorclockfrequency,
           $description,$ip,$latitude,$longitude,$nonewrevision=false)
{  
  include_once("config.php");
 
  // Security checks
  if(!is_numeric($siteid))
    {
    return;
    }
    
  $latitude = mysql_real_escape_string($latitude);
  $longitude = mysql_real_escape_string($longitude);
  $ip = mysql_real_escape_string($ip);
  $name = mysql_real_escape_string($name);
  $processoris64bits = mysql_real_escape_string($processoris64bits);
  $processorvendor = mysql_real_escape_string($processorvendor);
  $processorvendorid = mysql_real_escape_string($processorvendorid);
  $processorfamilyid = mysql_real_escape_string($processorfamilyid);
  $processormodelid = mysql_real_escape_string($processormodelid);
  $processorcachesize = mysql_real_escape_string($processorcachesize);
  $numberlogicalcpus = mysql_real_escape_string($numberlogicalcpus);
  $numberphysicalcpus = mysql_real_escape_string($numberphysicalcpus);
  $totalvirtualmemory = mysql_real_escape_string($totalvirtualmemory);
  $totalphysicalmemory = mysql_real_escape_string($totalphysicalmemory);
  $logicalprocessorsperphysical = mysql_real_escape_string($logicalprocessorsperphysical);
  $processorclockfrequency = mysql_real_escape_string($processorclockfrequency);
  $description = mysql_real_escape_string($description);
 
  // Update the basic information first
  mysql_query ("UPDATE site SET name='$name',ip='$ip',latitude='$latitude',longitude='$longitude' WHERE id='$siteid'"); 
 
  add_last_sql_error("update_site");
 
  $names = array();
  $names[] = "processoris64bits";
  $names[] = "processorvendor";
  $names[] = "processorvendorid";
  $names[] = "processorfamilyid";
  $names[] = "processormodelid";
  $names[] = "processorcachesize";
  $names[] = "numberlogicalcpus";     
  $names[] = "numberphysicalcpus";    
  $names[] = "totalvirtualmemory";  
  $names[] = "totalphysicalmemory";  
  $names[] = "logicalprocessorsperphysical";  
  $names[] = "processorclockfrequency";  
  $names[] = "description";      
 
 // Check that we have a valid input
 $isinputvalid = 0;
 foreach($names as $name)
  {
   if($$name != "NA" && strlen($$name)>0)
     { 
     $isinputvalid = 1;
   break;
     }
 }  

 if(!$isinputvalid)
   {
  return;
   }  
  
 // Check if we have valuable information and the siteinformation doesn't exist
 $hasvalidinfo = false;
 $newrevision2 = false;
 $query = mysql_query("SELECT * from siteinformation WHERE siteid='$siteid' ORDER BY timestamp DESC LIMIT 1");
 if(mysql_num_rows($query)==0)
   {
  $noinformation = 1;
  foreach($names as $name)
   {
   if($$name!="NA" && strlen($$name)>0)
    {
     $nonewrevision = false;
    $newrevision2 = true;
    $noinformation = 0;
    break;
    }
   }
  if($noinformation)
   {
   return; // we have nothing to add
   }
   }
 else
   {
  $query_array = mysql_fetch_array($query);
  // Check if the information are different from what we have in the database, then that means
   // the system has been upgraded and we need to create a new revision
   foreach($names as $name)
    {  
    if($$name!="NA" && $query_array[$name]!=$$name && strlen($$name)>0)
      {
   // Take care of rounding issues
   if(is_numeric($$name))
     {
    if(round($$name)!=$query_array[$name])
      {
     $newrevision2 = true;
     break;
      }
     }
    else
        {
    $newrevision2 = true;
    break;
        }
      }
    }
   }
  
  if($newrevision2 && !$nonewrevision)
   {
  $now = gmdate("Y-m-d H:i:s");
  $sql = "INSERT INTO siteinformation(siteid,timestamp";
  foreach($names as $name)
    {
   if($$name != "NA" && strlen($$name)>0)
     {
     $sql .= " ,$name";
    }
    }
   
  $sql .= ") VALUES($siteid,'$now'";
  foreach($names as $name)
    {
   if($$name != "NA"  && strlen($$name)>0)
     {
     $sql .= ",'".$$name."'";
    }
    }
   $sql .= ")"; 
  mysql_query ($sql);
  add_last_sql_error("update_site",$sql);
   }
 else
   {
  $sql = "UPDATE siteinformation SET ";
  $i=0;
  foreach($names as $name)
    {
   if($$name != "NA" && strlen($$name)>0)
     { 
    if($i>0)
     {
     $sql .= " ,";
     }
     $sql .= " $name='".$$name."'";
        $i++;
    }
    }
   
     $timestamp = $query_array["timestamp"];
     $sql .= " WHERE siteid='$siteid' AND timestamp='$timestamp'";
  
     mysql_query ($sql); 
   add_last_sql_error("update_site",$sql);
  }
}      

/** Get the geolocation from IP address */
function get_geolocation($ip)
{  
  include("config.php");
  $location = array();
  
  // Test if curl exists
  if(function_exists("curl_init") == FALSE)
    {
    $location['latitude'] = "";
    $location['longitude'] = "";
    return $location;
    }

  // Ask hostip.info for geolocation
  $lat = "";
  $long = "";
 
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, "http://api.hostip.info/get_html.php?ip=".$ip."&position=true");
      
  ob_start();
  curl_exec($curl);
  $httpReply = ob_get_contents();
  ob_end_clean();
    
  $pos = strpos($httpReply,"Latitude: ");
  if($pos !== FALSE)
    {
    $pos2 = strpos($httpReply,"\n",$pos);
    $lat = substr($httpReply,$pos+10,$pos2-$pos-10);
    }
    
  $pos = strpos($httpReply,"Longitude: ");
  if($pos !== FALSE)
    {
    $pos2 = strlen($httpReply);
    $long = substr($httpReply,$pos+11,$pos2-$pos-11);
    } 
  curl_close($curl);
 
  $location['latitude'] = "";
  $location['longitude'] = "";
  
  // Sanity check
  if(strlen($lat) > 0 && strlen($long)>0
     && $lat[0] != ' ' && $long[0] != ' '
    )
    {
    $location['latitude'] = $lat;
    $location['longitude'] = $long;
    }
 else// Check if we have a list of default locations
   {
  foreach($CDASH_DEFAULT_IP_LOCATIONS as $defaultlocation)
    {
    $defaultip = $defaultlocation["IP"];
   $defaultlatitude = $defaultlocation["latitude"];
   $defaultlongitude = $defaultlocation["longitude"];
   if(preg_match("#^".strtr(preg_quote($defaultip, '#'), array('\*' => '.*', '\?' => '.'))."$#i", $ip))
     {
    $location['latitude'] =  $defaultlocation["latitude"];
        $location['longitude'] = $defaultlocation["longitude"];
    }
    }
   }
  
  return $location;
} 

/** Create a site */
function add_site($name,$parser)
{
  include("config.php");
  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);

 $siteindex = $parser->index["SITE"];
 @$processoris64bits=$parser->vals[$siteindex[0]]["attributes"]["IS64BITS"];
 @$processorvendor=$parser->vals[$siteindex[0]]["attributes"]["VENDORSTRING"]; 
 @$processorvendorid=$parser->vals[$siteindex[0]]["attributes"]["VENDORID"]; 
 @$processorfamilyid=$parser->vals[$siteindex[0]]["attributes"]["FAMILYID"]; 
 @$processormodelid=$parser->vals[$siteindex[0]]["attributes"]["MODELID"]; 
 @$processorcachesize=$parser->vals[$siteindex[0]]["attributes"]["PROCESSORCACHESIZE"]; 
 @$numberlogicalcpus=$parser->vals[$siteindex[0]]["attributes"]["NUMBEROFLOGICALCPU"]; 
 @$numberphysicalcpus=$parser->vals[$siteindex[0]]["attributes"]["NUMBEROFPHYSICALCPU"]; 
 @$totalvirtualmemory=$parser->vals[$siteindex[0]]["attributes"]["TOTALVIRTUALMEMORY"]; 
 @$totalphysicalmemory=$parser->vals[$siteindex[0]]["attributes"]["TOTALPHYSICALMEMORY"]; 
 @$logicalprocessorsperphysical=$parser->vals[$siteindex[0]]["attributes"]["LOGICALPROCESSORSPERPHYSICAL"]; 
 @$processorclockfrequency=$parser->vals[$siteindex[0]]["attributes"]["PROCESSORCLOCKFREQUENCY"]; 
 $description="";
 $ip = $_SERVER['REMOTE_ADDR'];
 
 
  $ip = mysql_real_escape_string($ip);
  $processoris64bits = mysql_real_escape_string($processoris64bits);
  $processorvendor = mysql_real_escape_string($processorvendor);
  $processorvendorid = mysql_real_escape_string($processorvendorid);
  $processorfamilyid = mysql_real_escape_string($processorfamilyid);
  $processormodelid = mysql_real_escape_string($processormodelid);
  $processorcachesize = mysql_real_escape_string($processorcachesize);
  $numberlogicalcpus = mysql_real_escape_string($numberlogicalcpus);
  $numberphysicalcpus = mysql_real_escape_string($numberphysicalcpus);
  $totalvirtualmemory = mysql_real_escape_string($totalvirtualmemory);
  $totalphysicalmemory = mysql_real_escape_string($totalphysicalmemory);
  $logicalprocessorsperphysical = mysql_real_escape_string($logicalprocessorsperphysical);
  $processorclockfrequency = mysql_real_escape_string($processorclockfrequency);

  // Check if we already have the site registered
  $site = mysql_query("SELECT id,name,latitude,longitude FROM site WHERE name='$name'");
  if(mysql_num_rows($site)>0)
    {
    $site_array = mysql_fetch_array($site);
    $siteid = $site_array["id"];
    $sitename = $site_array["name"];
    $latitude = $site_array["latitude"];
    $longitude = $site_array["longitude"];
  
    // We update the site information if needed
    update_site($siteid,$sitename,
        $processoris64bits,
        $processorvendor,
        $processorvendorid,
        $processorfamilyid,
        $processormodelid,
        $processorcachesize,
        $numberlogicalcpus,
        $numberphysicalcpus,
        $totalvirtualmemory,
        $totalphysicalmemory,
        $logicalprocessorsperphysical,
        $processorclockfrequency,
        $description,$ip,$latitude,$longitude,false);
    return $siteid;
    }
  
  // If not found we create the site
  // We retrieve the geolocation from the IP address
  $location = get_geolocation($ip);
  
  $latitude = $location['latitude'];
  $longitude = $location['longitude'];  

  if(!mysql_query ("INSERT INTO site (name,ip,latitude,longitude) 
                    VALUES ('$name','$ip','$latitude','$longitude')"))
    {
    echo "add_site = ".mysql_error();  
    }
  
  $siteid = mysql_insert_id();
 
  // Insert the site information
  $now = gmdate("Y-m-d H:i:s");
  mysql_query ("INSERT INTO siteinformation (siteid,
           timestamp,
           processoris64bits,
           processorvendor,
           processorvendorid,
           processorfamilyid,
           processormodelid,
           processorcachesize,
           numberlogicalcpus,
           numberphysicalcpus,
           totalvirtualmemory,
           totalphysicalmemory,
           logicalprocessorsperphysical,
           processorclockfrequency,
                    description) 
           VALUES ('$siteid',
            '$now',
            '$processoris64bits',
            '$processorvendor',
            '$processorvendorid',
            '$processorfamilyid',
            '$processormodelid',
            '$processorcachesize',
            '$numberlogicalcpus',
            '$numberphysicalcpus',
            '$totalvirtualmemory',
            '$totalphysicalmemory',
            '$logicalprocessorsperphysical',
            '$processorclockfrequency',
            '$description')");
  
  return $siteid;
}

/* remove all builds for a project */
function remove_project_builds($projectid)
{
  if(!is_numeric($projectid))
    {
    return;
    }
    
  $build = mysql_query("SELECT id FROM build WHERE projectid='$projectid'");
  while($build_array = mysql_fetch_array($build))
    {
    $buildid = $build_array["id"];
    remove_build($buildid);
    }
}


/** Remove all related inserts for a given build */
function remove_build($buildid)
{
  if(!is_numeric($buildid))
    {
    return;
    }
    
  mysql_query("DELETE FROM build WHERE id='$buildid'");
  mysql_query("DELETE FROM build2group WHERE buildid='$buildid'");
  mysql_query("DELETE FROM builderror WHERE buildid='$buildid'");
  mysql_query("DELETE FROM buildupdate WHERE buildid='$buildid'");
  mysql_query("DELETE FROM configure WHERE buildid='$buildid'");
    
  // coverage file are kept unless they are shared
  $coverage = mysql_query("SELECT fileid FROM coverage WHERE buildid='$buildid'");
  while($coverage_array = mysql_fetch_array($coverage))
    {
    $fileid = $coverage_array["fileid"];
    // Make sur the file is not shared
    $numfiles = mysql_query("SELECT count(*) FROM coveragefile WHERE id='$fileid'");
    if($numfiles[0]==1)
      {
      mysql_query("DELETE FROM coveragefile WHERE id='$fileid'"); 
      }
    }
  
  mysql_query("DELETE FROM coverage WHERE buildid='$buildid'");
  mysql_query("DELETE FROM coveragefilelog WHERE buildid='$buildid'");
  mysql_query("DELETE FROM coveragesummary WHERE buildid='$buildid'");

  // dynamicanalysisdefect
  $dynamicanalysis = mysql_query("SELECT id FROM dynamicanalysis WHERE buildid='$buildid'");
  while($dynamicanalysis_array = mysql_fetch_array($dynamicanalysis))
    {
    $dynid = $dynamicanalysis_array["id"];
    mysql_query("DELETE FROM dynamicanalysisdefect WHERE id='$dynid'"); 
    }
   
  mysql_query("DELETE FROM dynamicanalysis WHERE buildid='$buildid'");
  mysql_query("DELETE FROM note WHERE buildid='$buildid'");  
  mysql_query("DELETE FROM updatefile WHERE buildid='$buildid'");   
  
  // Delete the test if not shared
  $build2test = mysql_query("SELECT * FROM build2test WHERE buildid='$buildid'");
  while($build2test_array = mysql_fetch_array($build2test))
    {
    $testid = $build2test_array["testid"];
    if(mysql_num_rows(mysql_query("SELECT * FROM build2test WHERE testid='$testid'"))==1)
      {
      // Check if the images for the test are not shared
      $test2image = mysql_query("SELECT imgid FROM test2image WHERE testid='$testid'");
      while($test2image_array = mysql_fetch_array($test2image))
        {
        $imgid = $test2image_array["imgid"];
        // Check if the test images are shared
        if(mysql_num_rows(mysql_query("SELECT * FROM test2image WHERE imgid='$imgid'"))==1)
          {
          mysql_query("DELETE FROM image WHERE id='$imgid'");
          }
        }
      // Tests are not shared we delete
      mysql_query("DELETE FROM testmeasurement WHERE testid='$testid'");
      mysql_query("DELETE FROM test WHERE id='$testid'");
      mysql_query("DELETE FROM test2image WHERE testid='$testid'");
      }
    }
  mysql_query("DELETE FROM build2test WHERE buildid='$buildid'"); 
}

/** Add a new build */
function add_build($projectid,$siteid,$name,$stamp,$type,$generator,$starttime,$endtime,$submittime,$command,$log,$parser)
{
  if(!is_numeric($projectid) || !is_numeric($siteid))
    {
    return;
    }

  $name = mysql_real_escape_string($name);
  $stamp = mysql_real_escape_string($stamp);
  $type = mysql_real_escape_string($type);
  $generator = mysql_real_escape_string($generator);
  $starttime = mysql_real_escape_string($starttime);
  $endtime = mysql_real_escape_string($endtime);
  $submittime = mysql_real_escape_string($submittime);
  $command = mysql_real_escape_string($command);
  $log = mysql_real_escape_string($log);

  // First we check if the build already exists if this is the case we delete all related information regarding
  // The previous build 
  $build = mysql_query("SELECT id FROM build WHERE projectid='$projectid' AND siteid='$siteid' AND name='$name' AND stamp='$stamp' AND type='$type'");
  if(mysql_num_rows($build)>0)
    {
    $build_array = mysql_fetch_array($build);
    remove_build($build_array["id"]);
    }

  mysql_query ("INSERT INTO build (projectid,siteid,name,stamp,type,generator,starttime,endtime,submittime,command,log) 
                           VALUES ('$projectid','$siteid','$name','$stamp','$type','$generator',
                                  '$starttime','$endtime','$submittime','$command','$log')");
  
  $buildid = mysql_insert_id();

  // Insert information about the parser
 $site = $parser->index["SITE"];
 @$osname=$parser->vals[$site[0]]["attributes"]["OSNAME"]; 
 @$osrelease=$parser->vals[$site[0]]["attributes"]["OSRELEASE"]; 
 @$osversion=$parser->vals[$site[0]]["attributes"]["OSVERSION"]; 
 @$osplatform=$parser->vals[$site[0]]["attributes"]["OSPLATFORM"];
 
 if($osname!="" || $osrelease!="" || $osversion!="" || $osplatform!="")
   {
   mysql_query ("INSERT INTO buildinformation (buildid,osname,osrelease,osversion,osplatform) 
                  VALUES ('$buildid','$osname','$osrelease','$osversion','$osplatform')");
   }

  // Insert the build into the proper group
  // 1) Check if we have any build2grouprules for this build
  $build2grouprule = mysql_query("SELECT b2g.groupid FROM build2grouprule AS b2g, buildgroup as bg
                                  WHERE b2g.buildtype='$type' AND b2g.siteid='$siteid' AND b2g.buildname='$name'
                                  AND (b2g.groupid=bg.id AND bg.projectid='$projectid') 
                                  AND '$starttime'>b2g.starttime 
                 AND ('$starttime'<b2g.endtime OR b2g.endtime='0000-00-00 00:00:00')");
                                  
  if(mysql_num_rows($build2grouprule)>0)
    {
    $build2grouprule_array = mysql_fetch_array($build2grouprule);
    $groupid = $build2grouprule_array["groupid"];
    
    mysql_query ("INSERT INTO build2group (groupid,buildid) 
                  VALUES ('$groupid','$buildid')");
    }
  else // we don't have any rules we use the type 
    {
    $buildgroup = mysql_query("SELECT id FROM buildgroup WHERE name='$type' AND projectid='$projectid'");
    $buildgroup_array = mysql_fetch_array($buildgroup);
    $groupid = $buildgroup_array["id"];
    
    mysql_query ("INSERT INTO build2group (groupid,buildid) 
                  VALUES ('$groupid','$buildid')");
    }
  return $buildid;
}

/** Add a new configure */
function add_configure($buildid,$starttime,$endtime,$command,$log,$status)
{
  if(!is_numeric($buildid))
    {
    return;
    }
    
  $starttime = mysql_real_escape_string($starttime);
  $endtime = mysql_real_escape_string($endtime);
  $command = mysql_real_escape_string($command);
  $log = mysql_real_escape_string($log);
  $status = mysql_real_escape_string($status);
      
  mysql_query ("INSERT INTO configure (buildid,starttime,endtime,command,log,status) 
               VALUES ('$buildid','$starttime','$endtime','$command','$log','$status')");
  add_last_sql_error("add_configure");
}

/** Add a new test */
function add_test($buildid,$name,$status,$path,$fullname,$command,$time,$details, $output, $images,$measurements)
{
  if(!is_numeric($buildid))
    {
    return;
    }
    
  $command = mysql_real_escape_string($command);
  $output = mysql_real_escape_string($output);
  $name = mysql_real_escape_string($name);  
  $status = mysql_real_escape_string($status);  
  $path = mysql_real_escape_string($path);  
  $fullname = mysql_real_escape_string($fullname);  
  $time = mysql_real_escape_string($time);     
  $details = mysql_real_escape_string($details);
  
  // CRC32 is computed with the measurements name and type and value
  $buffer = $name.$path.$command.$output.$details; 
  foreach($measurements as $measurement)
    {
    $buffer .= $measurement['type'].$measurement['name'].$measurement['value'];
    }
  $crc32 = crc32($buffer);
  
  // Check if the test doesn't exist
  $test = mysql_query("SELECT id FROM test WHERE crc32='$crc32' LIMIT 1");
  
  $testexists = false;
    
  if(mysql_num_rows($test) > 0) // test exists
    {   
    while($test_array = mysql_fetch_array($test))
      {
      $currentid = $test_array["id"];
      $sql = "SELECT count(imgid) FROM test2image WHERE testid='$currentid' ";
        
      // need to double check that the images are the same as well
      $i=0;
      foreach($images as $image)
        {
        $imgid = $image["id"];
        if($i==0)
          {
          $sql .= "AND (";
          }
         
        if($i>0)
          {
          $sql .= " OR ";
          }
            
        $sql .= "imgid='$imagid'";
             
        $i++;
        if($i==count($images))
          {
          $sql .= ")";
          }   
        } // end for each image
        
      $nimage_array = mysql_fetch_array(mysql_query($sql));  
      add_last_sql_error("add_test");
      $nimages = $nimage_array[0];
        
      if($nimages == count($images))
        {
        $testid = $test_array["id"];
        $testexists = true;
        break;
        } 
     } // end while test_array  
   }  // end num rows 
    
  if(!$testexists)
    {
    // Need to create a new test
    $query = "INSERT INTO test (crc32,name,path,command,details,output) 
              VALUES ('$crc32','$name','$path','$command', '$details', '$output')";
    if(mysql_query("$query"))
      {
      $testid = mysql_insert_id();
      
      // Insert the images
      foreach($images as $image)
        {
        $imgid = $image["id"];
        $role = $image["role"];
        $query = "INSERT INTO test2image(imgid, testid, role)
                  VALUES('$imgid', '$testid', '$role')";
        if(!mysql_query("$query"))
          {
          add_last_sql_error("add_test");
          }
        }
      
      // Insert the measurements  
      foreach($measurements as $measurement)
        {
        $name = $measurement['name'];
        $type = $measurement['type'];
        $value = $measurement['value'];
        $query = "INSERT INTO testmeasurement (testid,name,type,value) 
                  VALUES ('$testid','$name','$type','$value')";
        if(!mysql_query("$query"))
          {
          add_last_sql_error("add_test");
          }
        }
      }
    else
      {
      add_last_sql_error("add_test");
      } 
    }    

  // Add into build2test
  // Make sure that the test is not already added
  $query = mysql_query("SELECT buildid FROM build2test 
                        WHERE buildid='$buildid' AND testid='$testid' AND status='$status'
             AND time='$time'");
  add_last_sql_error("add_test"); 
  if(mysql_num_rows($query)==0)
   {               
    mysql_query("INSERT INTO build2test (buildid,testid,status,time) 
                 VALUES ('$buildid','$testid','$status','$time')");
    }
  add_last_sql_error("add_test");              
}

/** Add a new error/warning */
function  add_error($buildid,$type,$logline,$text,$sourcefile,$sourceline,$precontext,$postcontext,$repeatcount)
{
  if(!is_numeric($buildid))
    {
    return;
    }
    
  $type = mysql_real_escape_string($type);
  $logline = mysql_real_escape_string($logline);
  $text = mysql_real_escape_string($text);
  $sourcefile = mysql_real_escape_string($sourcefile);
  $sourceline = mysql_real_escape_string($sourceline);  
  $precontext = mysql_real_escape_string($precontext);  
  $postcontext = mysql_real_escape_string($postcontext);  
  $repeatcount = mysql_real_escape_string($repeatcount);  
      
  mysql_query ("INSERT INTO builderror (buildid,type,logline,text,sourcefile,sourceline,precontext,postcontext,repeatcount) 
               VALUES ('$buildid','$type','$logline','$text','$sourcefile','$sourceline','$precontext','$postcontext','$repeatcount')");
  add_last_sql_error("add_error");
}

/** Add a new update */
function add_update($buildid,$start_time,$end_time,$command,$type,$status)
{
  if(!is_numeric($buildid))
    {
    return;
    }
    
  $start_time = mysql_real_escape_string($start_time);
  $end_time = mysql_real_escape_string($end_time);
  $command = mysql_real_escape_string($command);
  $type = mysql_real_escape_string($type);
  $status = mysql_real_escape_string($status);
    
  mysql_query ("INSERT INTO buildupdate (buildid,starttime,endtime,command,type,status) 
               VALUES ('$buildid','$start_time','$end_time','$command','$type','$status')");
  add_last_sql_error("add_update");
}

/** Add a new update file */
function add_updatefile($buildid,$filename,$checkindate,$author,$email,$log,$revision,$priorrevision)
{
  if(!is_numeric($buildid))
    {
    return;
    }
    
  $filename = mysql_real_escape_string($filename);
  $checkindate = mysql_real_escape_string($checkindate);
  $author = mysql_real_escape_string($author);
  $email = mysql_real_escape_string($email);
  $log = mysql_real_escape_string($log);
  $revision = mysql_real_escape_string($revision);
  $priorrevision = mysql_real_escape_string($priorrevision);
    
  mysql_query ("INSERT INTO updatefile (buildid,filename,checkindate,author,email,log,revision,priorrevision) 
               VALUES ('$buildid','$filename','$checkindate','$author','$email','$log','$revision','$priorrevision')");
  add_last_sql_error("add_updatefile");
}

/** Add dynamic analysis */
function add_dynamic_analysis($buildid,$status,$checker,$name,$path,$fullcommandline,$log)
{  
  if(!is_numeric($buildid))
    {
    return;
    }
    
  $status = mysql_real_escape_string($status);
  $checker = mysql_real_escape_string($checker);
  $name = mysql_real_escape_string($name);
  $path = mysql_real_escape_string($path);
  $fullcommandline = mysql_real_escape_string($fullcommandline);
  $log = mysql_real_escape_string($log);

  mysql_query ("INSERT INTO dynamicanalysis (buildid,status,checker,name,path,fullcommandline,log) 
               VALUES ('$buildid','$status','$checker','$name','$path','$fullcommandline','$log')");
  return mysql_insert_id();
}
     
/** Add dynamic analysis defect */
function add_dynamic_analysis_defect($dynid,$type,$value)
{
  if(!is_numeric($dynid))
    {
    return;
    }
    
  $type = mysql_real_escape_string($type);
  $value = mysql_real_escape_string($value);

  mysql_query ("INSERT INTO dynamicanalysisdefect (dynamicanalysisid,type,value) 
                VALUES ('$dynid','$type','$value')");
  add_last_sql_error("add_dynamic_analysis_defect");
}


/** Add a new note */
function add_note($buildid,$text,$timestamp,$name)
{
  if(!is_numeric($buildid))
    {
    return;
    }
    
  $text = mysql_real_escape_string($text);
  $timestamp = mysql_real_escape_string($timestamp);
  $name = mysql_real_escape_string($name);
    
  mysql_query ("INSERT INTO note (buildid,text,time,name) 
                VALUES ('$buildid','$text','$timestamp','$name')");
  add_last_sql_error("add_note");
}

/**
 * Recursive version of glob
 *
 * @return array containing all pattern-matched files.
 *
 * @param string $sDir      Directory to start with.
 * @param string $sPattern  Pattern to glob for.
 * @param int $nFlags       Flags sent to glob.
 */
function globr($sDir, $sPattern, $nFlags = NULL)
{
  $sDir = escapeshellcmd($sDir);

  // Get the list of all matching files currently in the
  // directory.

  $aFiles = glob("$sDir/$sPattern", $nFlags);

  // Then get a list of all directories in this directory, and
  // run ourselves on the resulting array.  This is the
  // recursion step, which will not execute if there are no
  // directories.

  foreach (glob("$sDir/*", GLOB_ONLYDIR) as $sSubDir)
    {
    $aSubFiles = globr($sSubDir, $sPattern, $nFlags);
    $aFiles = array_merge($aFiles, $aSubFiles);
    }

  // The array we return contains the files we found, and the
  // files all of our children found.

  return $aFiles;
} 

/** Get dates 
 * today: the *starting* timestamp of the current dashboard
 * previousdate: the date in Ymd format of the previous dashboard
 * nextdate: the date in Ymd format of the next dashboard
 */
function get_dates($date,$nightlytime)
{
  $nightlytime = strtotime($nightlytime);
  
  $nightlyhour = date("H",$nightlytime);
  $nightlyminute = date("i",$nightlytime);
  $nightlysecond = date("s",$nightlytime);
 
  if(!isset($date) || strlen($date)==0)
    { 
    $date = date("Ymd"); // the date is always the date of the server
    
    if(date("His")>date("HiS",$nightlytime))
      {
      $date = date("Ymd",time()+3600*24); //next day
      } 
    }
 
  $today = mktime($nightlyhour,$nightlyminute,$nightlysecond,substr($date,4,2),substr($date,6,2),substr($date,0,4))-3600*24; // starting time
  
  $todaydate = mktime(0,0,0,substr($date,4,2),substr($date,6,2),substr($date,0,4)); 
  $previousdate = date("Ymd",$todaydate-3600*24);
  $nextdate = date("Ymd",$todaydate+3600*24);
 
  return array($previousdate, $today, $nextdate);
}

/** Get the logo id */
function getLogoID($projectid)
{
  if(!is_numeric($projectid))
    {
    return;
    }

  //asume the caller already connected to the database
  $query = "SELECT imageid FROM project WHERE id='$projectid'";
  $result = mysql_query($query);
  if(!$result)
    {
    return 0;
    }

  $row = mysql_fetch_array($result);
  return $row["imageid"];
}


function get_project_properties($projectname)
{
  include("config.php");

  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
  if(!$db)
    {
    echo "Error connecting to CDash database server<br>\n";
    exit(0);
    }

  if(!mysql_select_db("$CDASH_DB_NAME",$db))
    {
    echo "Error selecting CDash database<br>\n";
    exit(0);
    }

  $projectname = mysql_real_escape_string($projectname);
  $project = mysql_query("SELECT * FROM project WHERE name='$projectname'");
  if(mysql_num_rows($project)>0)
    {
    $project_props = mysql_fetch_array($project);
    }
  else
    {
    $project_props = array();
    }

  return $project_props;
}


function get_project_property($projectname, $prop)
{
  $project_props = get_project_properties($projectname);
  return $project_props[$prop];
}


// make_cdash_url ensures that a url begins with a known url protocol
// identifier
//
function make_cdash_url($url)
{
  // By default, same as the input
  //
  $cdash_url = $url;

  // Unless the input does *not* start with a known protocol identifier...
  // If it does not start with http or https already, then prepend "http://"
  // to the input.
  //
  $npos = strpos($url, "http://");
  if ($npos === FALSE)
  {
    $npos2 = strpos($url, "https://");
    if ($npos2 === FALSE)
    {
      $cdash_url = "http://" . $url;
    }
  }

  return $cdash_url;
}


// Return the email of a given author within a given project.
//
function get_author_email($projectname, $author)
{
  include("config.php");

  $projectid = get_project_id($projectname);
  if($projectid == -1)
    {
    return "unknownProject";
    }

  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
  if(!$db)
    {
    echo "Error connecting to CDash database server<br>\n";
    exit(0);
    }

  if(!mysql_select_db("$CDASH_DB_NAME",$db))
    {
    echo "Error selecting CDash database<br>\n";
    exit(0);
    }

  $qry = mysql_query("SELECT email FROM user WHERE id IN (SELECT userid FROM user2project WHERE projectid='$projectid' AND cvslogin='$author') LIMIT 1");

  $email = "";

  if(mysql_num_rows($qry) === 1)
    {
    $results = mysql_fetch_array($qry);
    $email = $results["email"];
    }

  if (0 === strcmp($email, ""))
    {
    $email = "userEmailUnknown";
    }

  return $email;  
}


function get_cdash_dashboard_xml($projectname, $date)
{
  include("config.php");

  $projectid = get_project_id($projectname);
  if($projectid == -1)
    {
    return;
    }

  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
  if(!$db)
    {
    echo "Error connecting to CDash database server<br>\n";
    exit(0);
    }

  if(!mysql_select_db("$CDASH_DB_NAME",$db))
    {
    echo "Error selecting CDash database<br>\n";
    exit(0);
    }

  $project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
  if(mysql_num_rows($project)>0)
    {
    $project_array = mysql_fetch_array($project);
    }
  else
    {
    $project_array = array();
    $project_array["cvsurl"] = "unknown";
    $project_array["bugtrackerurl"] = "unknown";
    $project_array["documentationurl"] = "unknown";
    $project_array["homeurl"] = "unknown";
    $project_array["googletracker"] = "unknown";
    $project_array["name"] = $projectname;
    $project_array["nightlytime"] = "00:00:00";
    }
  
  list ($previousdate, $currentstarttime, $nextdate) = get_dates($date,$project_array["nightlytime"]);

  $xml = "<dashboard>
  <datetime>".date("l, F d Y H:i:s",time())."</datetime>
  <date>".$date."</date>
  <unixtimestamp>".$currentstarttime."</unixtimestamp>
  <startdate>".date("l, F d Y H:i:s",$currentstarttime)."</startdate>
  <svn>".make_cdash_url(htmlentities($project_array["cvsurl"]))."</svn>
  <bugtracker>".make_cdash_url(htmlentities($project_array["bugtrackerurl"]))."</bugtracker>
  <googletracker>".htmlentities($project_array["googletracker"])."</googletracker>
  <documentation>".make_cdash_url(htmlentities($project_array["documentationurl"]))."</documentation> 
  <home>".make_cdash_url(htmlentities($project_array["homeurl"]))."</home>
  <projectid>".$projectid."</projectid>
  <projectname>".$project_array["name"]."</projectname>
  <projectpublic>".$project_array["public"]."</projectpublic>
  <previousdate>".$previousdate."</previousdate>
  <nextdate>".$nextdate."</nextdate>
  <logoid>".getLogoID($projectid)."</logoid>
  </dashboard>
  ";
  
  $userid = 0;
  if(isset($_SESSION['cdash']))
    {
    $xml .= "<user>";
    $userid = $_SESSION['cdash']['loginid'];
    $xml .= add_XML_value("id",$userid);
    $xml .= "</user>";
    }
    
  return $xml;
}

/** */
function get_cdash_dashboard_xml_by_name($projectname, $dates)
{
  return get_cdash_dashboard_xml($projectname, $dates);
}

function get_previous_revision($revision)
{
  // Split revision into components based on any "." separators:
  //
  $revcmps = split("\.", $revision);
  $n = count($revcmps);

  // svn style "single-component" revision number, just subtract one:
  //
  if ($n === 1)
  {
    return $revcmps[0] - 1;
  }

  // cvs style "multi-component" revision number, subtract one from last
  // component -- if result is 0, chop off last two components -- finally,
  // re-assemble $n components for previous_revision:
  //
  $revcmps[$n-1] = $revcmps[$n-1] - 1;
  if ($revcmps[$n-1] === 0)
  {
    $n = $n - 2;
  }

  if ($n < 2)
  {
    // Can't reassemble less than 2 components; use original revision
    // as previous...
    //
    $previous_revision = $revision;
  }
  else
  {
    // Reassemble components into previous_revision:
    //
    $previous_revision = $revcmps[0];
    $i = 1;
    while ($i<$n)
    {
      $previous_revision = $previous_revision . "." . $revcmps[$i];
      $i = $i + 1;
    }
  }

  return $previous_revision;
}


/** Return the ViewCVS URL */
function get_viewcvs_diff_url($projecturl, $directory, $file, $revision)
{
  // The project's viewcvs URL is expected to contain "?root=projectname"
  // Split it at the "?"
  //
  $cmps = split("\?", $projecturl);

  // If $cmps[1] starts with "root=" and the $directory value starts
  // with "whatever comes after that" then remove that bit from directory:
  //
  $npos = strpos($cmps[1], "root=");
  if ($npos !== FALSE && $npos === 0)
    {
    $rootdir = substr($cmps[1], 5);

    $npos = strpos($directory, $rootdir);
    if ($npos !== FALSE && $npos === 0)
      {
      $directory = substr($directory, strlen($rootdir));
      $npos = strpos($directory, "/");
      if ($npos !== FALSE && $npos === 0)
        {
        if (1 === strlen($directory))
          {
          $directory = "";
          }
        else
          {
          $directory = substr($directory, 1);
          }
        }
      }
    }


  if (strlen($directory)>0)
    {
    $dircmp = $directory . "/";
    }
  else
    {
    $dircmp = "";
    }

 
  // If we have a revision
  if($revision != '')
    { 
    $prev_revision = get_previous_revision($revision);
    if (0 === strcmp($revision, $prev_revision))
      {
      $revcmp = "&rev=" . $revision . "&view=markup";
      $diff_url = $cmps[0] . $dircmp . $file . "?" . $cmps[1] . $revcmp;
      }
    else
      {
      // different : view the diff of r1 and r2:
      $revcmp = "&r1=" . $prev_revision . "&r2=" . $revision;
      $diff_url = $cmps[0] . $dircmp . $file . ".diff?" . $cmps[1] . $revcmp;
      }
    }
  else
    {
    $diff_url = $cmps[0] . $dircmp . $file ."?".$cmps[1];
    }

  return make_cdash_url($diff_url);
}


/** Return the Trac URL */
function get_trac_diff_url($projecturl, $directory, $file, $revision)
{
  if ($directory == "")
    {
    $diff_url = $projecturl."/".$file;
    }
  else
    {
    $diff_url = $projecturl."/".$directory."/".$file;
    }

  if($revision != '')
    {
    $diff_url .= "?rev=".$revision;
    }

  return make_cdash_url($diff_url);
}

/** Return the Fisheye URL */
function get_fisheye_diff_url($projecturl, $directory, $file, $revision)
{
  $diff_url = rtrim($projecturl, '/').($directory ? ("/".$directory) : "")."/".$file;
  
  if($revision != '')
    {
    $prev_revision = get_previous_revision($revision);
    if($prev_revision != $revision)
      {
      $diff_url .= "?r1=".$prev_revision."&r2=".$revision;
      } 
    else 
      {
      $diff_url .= "?r=".$revision;
      }
    }
  return make_cdash_url($diff_url);
}

/** Return the CVSTrac URL */
function get_cvstrac_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $prev_revision = get_previous_revision($revision);
    if($prev_revision != $revision)
      {
      $diff_url = $projecturl."/filediff?f=".($directory ? ($directory) : "")."/".$file;
      $diff_url .= "&v1=".$prev_revision."&v2=".$revision;
      } 
    else 
      {
      $diff_url = $projecturl."/fileview?f=".($directory ? ($directory) : "")."/".$file;
      $diff_url .= "&v=".$revision;
      }
    } 
  else
    {
    $diff_url = $projecturl."/rlog?f=".($directory ? ($directory) : "")."/".$file;
    }

  return make_cdash_url($diff_url);
}

/** Get the diff url based on the type of viewer */
function get_diff_url($projectid,$projecturl, $directory, $file, $revision='')
{
  if(!is_numeric($projectid))
    {
    return;
    }
  
  $project = mysql_query("SELECT cvsviewertype FROM project WHERE id='$projectid'");
  $project_array = mysql_fetch_array($project);
   
  if($project_array["cvsviewertype"] == "trac")
    {
    return get_trac_diff_url($projecturl, $directory, $file, $revision);
    }
  elseif($project_array["cvsviewertype"] == "fisheye")
    {
    return get_fisheye_diff_url($projecturl, $directory, $file, $revision);
    }
  elseif($project_array["cvsviewertype"] == "cvstrac")
    {
    return get_cvstrac_diff_url($projecturl, $directory, $file, $revision);
    }
  else // default is viewcvs
    {
    return get_viewcvs_diff_url($projecturl, $directory, $file, $revision);
    }
}


?>
