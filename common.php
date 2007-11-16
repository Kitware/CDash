<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
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

/**used to escape special XML characters*/
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


/** Add an XML tag to a string */
function add_XML_value($tag,$value)
{
  return "<".$tag.">".XMLStrFormat($value)."</".$tag.">";
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

/** Backup an XML file */
function backup_xml_file($contents)
{
  include("config.php");
  
  clean_backup_directory(); // shoudl probably be run as a cronjob
  
  $p = xml_parser_create();
  xml_parse_into_struct($p, $contents, $vals, $index);
  xml_parser_free($p);

  if(@$vals[1]["tag"] == "BUILD")
    {
    $file = "Build.xml";
    }
  else if(@$vals[1]["tag"] == "CONFIGURE")
    {
    $file = "Configure.xml";
    }
  else if(@$vals[1]["tag"] == "TESTING")
    {
    $file = "Test.xml";
    }
  else if(@$vals[0]["tag"] == "UPDATE")
    {
    $file = "Update.xml";
    }  
  else if($vals[1]["tag"] == "COVERAGE")
    {
    $file = "Coverage.xml";
    }
  else if(@$vals[1]["tag"] == "COVERAGELOG")
    {
    $file = "CoverageLog.xml";
    }   
  else if(@$vals[1]["tag"] == "NOTES")
    {
    $file = "Notes.xml";
    }
  else if(@$vals[1]["tag"] == "DynamicAnalysis")
    {
    $file = "DynamicAnalysis.xml";
    } 
  else
    {
    $file = "Other.xml";
    }
 
  $sitename = $vals[0]["attributes"]["NAME"]; 
  $name = $vals[0]["attributes"]["BUILDNAME"];
  $stamp = $vals[0]["attributes"]["BUILDSTAMP"];
 
  $filename = $CDASH_BACKUP_DIRECTORY."/".$sitename."_".$name."_".$stamp."_".$file;
    
  if (!$handle = fopen($filename, 'w')) 
    {
    echo "Cannot open file ($filename)";
    exit;
    }
  
  // Write $somecontent to our opened file.
  if (fwrite($handle, $contents) === FALSE)  
    {
    echo "Cannot write to file ($contents)";
    exit;
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

    $buildquery = mysql_query("SELECT count(id) FROM build WHERE projectid='$projectid'");
    $buildquery_array = mysql_fetch_array($buildquery); 
    $project['nbuilds'] = $buildquery_array[0];
    
    /*$testquery = mysql_query("SELECT count(t.id) FROM test AS t,build AS b WHERE b.projectid='$projectid' AND t.buildid=b.id");
    //$testquery = mysql_query("SELECT count(id) FROM test WHERE buildid IN (SELECT id FROM build WHERE projectid='$projectid')");
    $testquery_array = mysql_fetch_array($testquery); 
    $project['ntests'] = $testquery_array[0];*/
     
    $projects[] = $project; 
    }
    
  return $projects;
}

/** Get the build id from stamp, name and buildname */
function get_build_id($buildname,$stamp,$projectid)
{
  include("config.php");

  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  if(!$db)
    {
    echo("Problem with mysql_connect<br>\n");
    }
  mysql_select_db("$CDASH_DB_NAME",$db);
  
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
  include("config.php");

  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);

  $project = mysql_query("SELECT id FROM project WHERE name='$projectname'");
  if(mysql_num_rows($project)>0)
    {
    $project_array = mysql_fetch_array($project);
    return $project_array["id"];
    }
    
  return -1;
}

/** Add a new coverage */
function add_coveragesummary($buildid,$loctested,$locuntested)
{
  mysql_query ("INSERT INTO coveragesummary (buildid,loctested,locuntested) 
                VALUES ('$buildid','$loctested','$locuntested')");
}


/** Create a coverage */
function add_coverage($buildid,$fullpath,$covered,$loctested,$locuntested,
                      $branchstested=0,$branchsuntested=0,$functionstested=0,$functionsuntested=0)
{
  // Create an empty file if doesn't exists
  $coveragefile = mysql_query("SELECT cf.id FROM coverage AS c,coveragefile AS cf WHERE c.buildid='$buildid' AND cf.fullpath='$fullpath'");
  if(mysql_num_rows($coveragefile)==0)
    {
    mysql_query ("INSERT INTO coveragefile (fullpath) VALUES ('$fullpath')");
    $fileid = mysql_insert_id();
    }
  else
    {
    $coveragefile_array = mysql_fetch_array($coveragefile);
    $fileid = $coveragefile_array["id"];
    }
    
  // Insert into coverage
  mysql_query ("INSERT INTO coverage (buildid,fileid,covered,loctested,locuntested,branchstested,branchsuntested,functionstested,functionsuntested) 
                VALUES ('$buildid','$fileid','$covered','$loctested','$locuntested','$branchstested','$branchsuntested','$functionstested','$functionsuntested')");
  echo mysql_error();
}

/** Create a coverage file */
function add_coveragefile($buildid,$fullpath,$filecontent)
{
  // Check if we have the file
  $coveragefile = mysql_query("SELECT cf.id,cf.file FROM coverage AS c,coveragefile AS cf WHERE c.buildid='$buildid' AND cf.fullpath='$fullpath'");
  $coveragefile_array = mysql_fetch_array($coveragefile);
  $fileid = $coveragefile_array["id"];
  
  if($fileid)
    {
    if(strlen($coveragefile_array["file"])==0)
      {
      mysql_query ("UPDATE coveragefile SET file='$filecontent' WHERE id='$fileid'");
      }
    else if(crc32($filecontent) != crc32($coveragefile_array["file"]))
      {
      $previousfileid = $fileid;
      mysql_query ("INSERT INTO coveragefile(fullpath,file) VALUES ('$fullpath','$filecontent')");
      echo mysql_error();
      $fileid = mysql_insert_id();
      mysql_query ("UPDATE coverage SET fileid='$fileid' WHERE buildid='$buildid' AND fileid='$previousfileid'");
      echo mysql_error();
      }  
    }
  return $fileid;
}

/** Add the coverage log */
function add_coveragelogfile($buildid,$fileid,$line,$code)
{
  mysql_query ("INSERT INTO coveragefilelog (buildid,fileid,line,code) VALUES ('$buildid','$fileid','$line','$code')");
  echo mysql_error();    
}

/** Create a site */
function add_site($name,$description="",$processor="",$numprocessors="1",$ip="")
{
  include("config.php");
  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);

  // Check if we already have the site registered
  $site = mysql_query("SELECT id FROM site WHERE name='$name'");
  if(mysql_num_rows($site)>0)
    {
    $site_array = mysql_fetch_array($site);
    return $site_array["id"];
    }
  
  // If not found we create the site
  // Should compute the location from IP
  $latitude = "";
  $longitude = "";
    
  mysql_query ("INSERT INTO site (name,description,processor,numprocessors,ip,latitude,longitude) 
                          VALUES ('$name','$description','$processor','$numprocessors','$ip','$latitude','$longitude')");
  echo mysql_error();  
  return mysql_insert_id();
}

/** Add a new build */
function add_build($projectid,$siteid,$name,$stamp,$type,$generator,$starttime,$endtime,$submittime,$command,$log)
{
  mysql_query ("INSERT INTO build (projectid,siteid,name,stamp,type,generator,starttime,endtime,submittime,command,log) 
                          VALUES ('$projectid','$siteid','$name','$stamp','$type','$generator',
                                  '$starttime','$endtime','$submittime','$command','$log')");
  
  //$handle = fopen("log.txt","a");
  //fwrite($handle,"buildid = ".mysql_error());
  //fclose($handle);
  return mysql_insert_id();
}

/** Add a new configure */
function add_configure($buildid,$starttime,$endtime,$command,$log,$status)
{
  $command = addslashes($command);
  $log = addslashes($log);
    
  mysql_query ("INSERT INTO configure (buildid,starttime,endtime,command,log,status) 
               VALUES ('$buildid','$starttime','$endtime','$command','$log','$status')");
  echo mysql_error();
}

/** Add a new test */
function add_test($buildid,$name,$status,$path,$fullname,$command,$time,$details, $output, $images)
{
  $command = addslashes($command);
  $output = addslashes($output);
    
  $query = "INSERT INTO test
            (buildid,name,status,path,fullname,command,time,details, output) 
            VALUES ('$buildid','$name','$status','$path','$fullname',
                    '$command','$time', '$details', '$output')";
  if(mysql_query("$query"))
    {
    $testid = mysql_insert_id();
    foreach($images as $image)
      {
      $imgid = $image["id"];
      $role = $image["role"];
      $query = "INSERT INTO image2test(imgid, testid, role)
                VALUES('$imgid', '$testid', '$role')";
      if(!mysql_query("$query"))
        {
        echo mysql_error();
        }
      }
    }
  else
    {
    echo mysql_error();
    }
}

/** Add a new error/warning */
function  add_error($buildid,$type,$logline,$text,$sourcefile,$sourceline,$precontext,$postcontext,$repeatcount)
{
  $text = addslashes($text);
  $precontext = addslashes($precontext);
  $postcontext = addslashes($postcontext);
    
  mysql_query ("INSERT INTO builderror (buildid,type,logline,text,sourcefile,sourceline,precontext,postcontext,repeatcount) 
               VALUES ('$buildid','$type','$logline','$text','$sourcefile','$sourceline','$precontext','$postcontext','$repeatcount')");
  echo mysql_error();
}

/** Add a new update */
function  add_update($buildid,$start_time,$end_time,$command,$type)
{
  $command = addslashes($command);
    
  mysql_query ("INSERT INTO buildupdate (buildid,starttime,endtime,command,type) 
               VALUES ('$buildid','$start_time','$end_time','$command','$type')");
  echo mysql_error();
}

/** Add a new update file */
function add_updatefile($buildid,$filename,$checkindate,$author,$email,$log,$revision,$priorrevision)
{
  $log = addslashes($log);
    
  mysql_query ("INSERT INTO updatefile (buildid,filename,checkindate,author,email,log,revision,priorrevision) 
               VALUES ('$buildid','$filename','$checkindate','$author','$email','$log','$revision','$priorrevision')");
  echo mysql_error();
}

/** Add dynamic analysis */
function add_dynamic_analysis($buildid,$status,$checker,$name,$path,$fullcommandline,$log)
{  
  $log = addslashes($log);
  mysql_query ("INSERT INTO dynamicanalysis (buildid,status,checker,name,path,fullcommandline,log) 
               VALUES ('$buildid','$status','$checker','$name','$path','$fullcommandline','$log')");
  return mysql_insert_id();
}
     
/** Add dynamic analysis defect */
function add_dynamic_analysis_defect($dynid,$type,$value)
{
  mysql_query ("INSERT INTO dynamicanalysisdefect (dynamicanalysisid,type,value) 
                VALUES ('$dynid','$type','$value')");
  echo mysql_error();
}


/** Add a new note */
function add_note($buildid,$text,$timestamp,$name)
{
  $text = addslashes($text);
    
  mysql_query ("INSERT INTO note (buildid,text,time,name) VALUES ('$buildid','$text','$timestamp','$name')");
  echo mysql_error();
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

/** Get dates */
function get_dates($date,$nightlytime)
{
  $nightlyhour = substr($nightlytime,0,2);
  $nightlyminute = substr($nightlytime,3,2);
  $nightlysecond = substr($nightlytime,6,2);
  
  if(!isset($date) || strlen($date)==0)
    { 
    $date = date("Ymd");
    $currenttime = time();
    $today = mktime($nightlyhour,$nightlyminute,$nightlysecond,substr($date,4,2),substr($date,6,2),substr($date,0,4));
    }
   else
    {
    $today = mktime($nightlyhour,$nightlyminute,$nightlysecond,substr($date,4,2),substr($date,6,2),substr($date,0,4));
    $currenttime = $today-1; // minus one second
    }
  
  $previousdate = date("Ymd",$today-3600*24-1);
  $nextdate = date("Ymd",$today+3600*24-1);
  return array($previousdate, $currenttime, $nextdate);
}

/** Get the logo id */
function getLogoID($projectid)
{
  //asume the caller already connected to the database
  //$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  //mysql_select_db("$CDASH_DB_NAME",$db);
  $query = "SELECT imgid FROM image2project WHERE projectid='$projectid'";
  $result = mysql_query($query);
  if(!$result)
    {
    return 0;
    }
  $row = mysql_fetch_array($result);
  return $row["imgid"];
}

?>
