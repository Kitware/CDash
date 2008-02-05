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

/** Add an XML tag to a string */
function add_XML_value($tag,$value)
{
  return "<".$tag.">".XMLStrFormat($value)."</".$tag.">";
}

/** add information to the log file */
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
    
    $projects[] = $project; 
    }
    
  return $projects;
}

/** Get the build id from stamp, name and buildname */
function get_build_id($buildname,$stamp,$projectid)
{  
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
  mysql_query ("INSERT INTO coveragesummary (buildid,loctested,locuntested) 
                VALUES ('$buildid','$loctested','$locuntested')");
}

/** Send a coverage email */
function send_coverage_email($buildid,$fileid,$fullpath,$loctested,$locuntested,$branchstested,$branchsuntested,
                             $functionstested,$functionsuntested)
{
  include("config.php");
   
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
  // Construct the SQL query
  $sql = "INSERT INTO coverage (buildid,fileid,covered,loctested,locuntested,branchstested,branchsuntested,functionstested,functionsuntested) VALUES ";
  
  $i=0;
  foreach($coverage_array as $coverage)
    {    
    $fullpath = $coverage["fullpath"];

    // Create an empty file if doesn't exists
    $coveragefile = mysql_query("SELECT cf.id FROM coverage AS c,coveragefile AS cf 
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
      }
    
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
  // Check if we have the file
  $coveragefile = mysql_query("SELECT cf.id,cf.file FROM coverage AS c,coveragefile AS cf WHERE c.fileid=cf.id AND c.buildid='$buildid' AND cf.fullpath='$fullpath'");
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
      add_last_sql_error("add_coveragefile");
      $fileid = mysql_insert_id();
      mysql_query ("UPDATE coverage SET fileid='$fileid' WHERE buildid='$buildid' AND fileid='$previousfileid'");
      add_last_sql_error("add_coveragefile");
      }  
    }
  return $fileid;
}

/** Add the coverage log */
function add_coveragelogfile($buildid,$fileid,$coveragelogarray)
{
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
  echo $sql;
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

/** Remove all related inserts for a given build */
function remove_build($buildid)
{
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
  mysql_query("DELETE FROM build2test WHERE buildid='$buildid'");  
  
}

/** Add a new build */
function add_build($projectid,$siteid,$name,$stamp,$type,$generator,$starttime,$endtime,$submittime,$command,$log,$parser)
{
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
                                  AND '$submittime'>b2g.starttime AND ('$submittime'<b2g.endtime OR b2g.endtime='0000-00-00 00:00:00')");
                                  
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
  $command = addslashes($command);
  $log = addslashes($log);
    
  mysql_query ("INSERT INTO configure (buildid,starttime,endtime,command,log,status) 
               VALUES ('$buildid','$starttime','$endtime','$command','$log','$status')");
  add_last_sql_error("add_configure");
}

/** Add a new test */
function add_test($buildid,$name,$status,$path,$fullname,$command,$time,$details, $output, $images)
{
  //add_log("Start buildid=".$buildid,"add_test");
  
  $command = addslashes($command);
  $output = addslashes($output);
  
  $buffer = $name.$path.$command.$output.$details; 
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
          $sql .= " OR";
          }
            
        $sql .= "imgid='$imagid'";
             
        $i++;
        if($i==count($images))
          {
          $sql .= ")";
          }   
        } // end for each image
        
      $nimage_array = mysql_fetch_array(mysql_query($sql));  
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
      }
    else
      {
      add_last_sql_error("add_test");
      } 
    } 
    

   // Add into build2test
   mysql_query("INSERT INTO build2test (buildid,testid,status,time) 
                 VALUES ('$buildid','$testid','$status','$time')");
   add_last_sql_error("add_test");              
}

/** Add a new error/warning */
function  add_error($buildid,$type,$logline,$text,$sourcefile,$sourceline,$precontext,$postcontext,$repeatcount)
{
  $text = addslashes($text);
  $precontext = addslashes($precontext);
  $postcontext = addslashes($postcontext);
    
  mysql_query ("INSERT INTO builderror (buildid,type,logline,text,sourcefile,sourceline,precontext,postcontext,repeatcount) 
               VALUES ('$buildid','$type','$logline','$text','$sourcefile','$sourceline','$precontext','$postcontext','$repeatcount')");
  add_last_sql_error("add_error");
}

/** Add a new update */
function  add_update($buildid,$start_time,$end_time,$command,$type,$status)
{
  $command = addslashes($command);
    
  mysql_query ("INSERT INTO buildupdate (buildid,starttime,endtime,command,type,status) 
               VALUES ('$buildid','$start_time','$end_time','$command','$type','$status')");
  add_last_sql_error("add_update");
}

/** Add a new update file */
function add_updatefile($buildid,$filename,$checkindate,$author,$email,$log,$revision,$priorrevision)
{
  $log = addslashes($log);
    
  mysql_query ("INSERT INTO updatefile (buildid,filename,checkindate,author,email,log,revision,priorrevision) 
               VALUES ('$buildid','$filename','$checkindate','$author','$email','$log','$revision','$priorrevision')");
  add_last_sql_error("add_updatefile");
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
  add_last_sql_error("add_dynamic_analysis_defect");
}


/** Add a new note */
function add_note($buildid,$text,$timestamp,$name)
{
  $text = addslashes($text);
    
  mysql_query ("INSERT INTO note (buildid,text,time,name) VALUES ('$buildid','$text','$timestamp','$name')");
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

function get_cdash_dashboard_xml($projectname, $date)
{
  include("config.php");

  $projectid = get_project_id($projectname);

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
    $project_array["homeurl"] = "unknown";
    $project_array["name"] = $projectname;
    $project_array["nightlytime"] = "00:00:00";
    }
  
  list ($previousdate, $currentstarttime, $nextdate) = get_dates($date,$project_array["nightlytime"]);

  $xml = "<dashboard>
  <datetime>".date("l, F d Y H:i:s",time())."</datetime>
  <date>".$date."</date>
 <unixtimestamp>".$currentstarttime."</unixtimestamp>
  <startdate>".date("l, F d Y H:i:s",$currentstarttime)."</startdate>
  <svn>".$project_array["cvsurl"]."</svn>
  <bugtracker>".$project_array["bugtrackerurl"]."</bugtracker>
  <home>".$project_array["homeurl"]."</home>
  <projectid>".$projectid."</projectid>
  <projectname>".$project_array["name"]."</projectname>
  <previousdate>".$previousdate."</previousdate>
  <nextdate>".$nextdate."</nextdate>
  <logoid>".getLogoID($projectid)."</logoid>
  </dashboard>
  ";
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
//  echo "rootdir: '" . $rootdir . "'<br/>";

    $npos = strpos($directory, $rootdir);
    if ($npos !== FALSE && $npos === 0)
    {
      $directory = substr($directory, strlen($rootdir));
//  echo "directory: '" . $directory . "'<br/>";

      $npos = strpos($directory, "/");
      if ($npos !== FALSE && $npos === 0)
      {
        if (1 === strlen($directory))
        {
          $directory = "";
//  echo "empty directory! '" . $directory . "'<br/>";
        }
        else
        {
          $directory = substr($directory, 1);
//  echo "non-empty directory! '" . $directory . "'<br/>";
        }
      }
    }
  }

  $prev_revision = get_previous_revision($revision);

  if (strlen($directory)>0)
  {
    $dircmp = $directory . "/";
  }
  else
  {
    $dircmp = "";
  }

  if (0 === strcmp($revision, $prev_revision))
  {
    // same : just view whole file:
    $revcmp = "&rev=" . $revision . "&view=markup";
  }
  else
  {
    // different : view the diff of r1 and r2:
    $revcmp = "&r1=" . $prev_revision . "&r2=" . $revision;
  }

//  echo "dircmp: '" . $dircmp . "'<br/>";
//  echo "revcmp: '" . $revcmp . "'<br/>";

  $diff_url = $cmps[0] . $dircmp . $file . ".diff?" . $cmps[1] . $revcmp;

//  echo "diff_url: '" . $diff_url . "'<br/>";
//  echo "0: '" . $cmps[0] . "'<br/>";
//  echo "1: '" . $cmps[1] . "'<br/>";
//  echo "<br/>";

  $npos = strpos($diff_url, "http://");
  if ($npos === FALSE)
  {
    $diff_url = "http://" . $diff_url;
  }

  return $diff_url;
}


function get_diff_url($projecturl, $directory, $file, $revision)
{
  return get_viewcvs_diff_url($projecturl, $directory, $file, $revision);
}


?>
