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
require_once("cdash/config.php");
require_once("cdash/log.php");

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
function generate_XSLT($xml,$pageName,$only_in_local=false)
{
  // For common xsl pages not referenced directly
  // i.e. header, headerback, etc...
  // look if they are in the local directory, and set
  // an XML value accordingly
  include("cdash/config.php");
  if($CDASH_USE_LOCAL_DIRECTORY&&!$only_in_local)
    {
    $pos = strpos($xml,"</cdash>"); // this should be the last
    if($pos !== FALSE)
      {
      $xml = substr($xml,0,$pos);
      $xml .= "<uselocaldirectory>1</uselocaldirectory>";
      
      // look at the local directory if we have the same php file
      // and add the xml if needed
      $localphpfile = "local/".$pageName.".php";
      if(file_exists($localphpfile))
        {
        include_once($localphpfile);
        $xml .= getLocalXML();
        }
        
      $xml .= "</cdash>"; // finish the xml
      }
    }

  $xh = xslt_create();
  
  if(PHP_VERSION < 5)
    { 
    $filebase = 'file://' . getcwd () . '/';
    xslt_set_base($xh,$filebase);
    }

  $arguments = array (
    '/_xml' => $xml
  );

  if(!empty($CDASH_DEBUG_XML))
    {
     $tmp=eregi_replace("(\<)([A-Za-z0-9\-_.]{1,250})(\>)","\\0\n",$xml);
     $tmp=eregi_replace("(\</)([A-Za-z0-9\-_.]{1,250})(\>)","\n\\0\n",$tmp);
     $inF=fopen($CDASH_DEBUG_XML,"w");
     fwrite($inF, $tmp);
     fclose($inF);
    }
  $xslpage = $pageName.".xsl";
  
  // Check if the page exists in the local directory
  if($CDASH_USE_LOCAL_DIRECTORY && file_exists("local/".$xslpage))
    {
    $xslpage = "local/".$xslpage;
    }
 
  $html = xslt_process($xh, 'arg:/_xml', $xslpage, NULL, $arguments);
  
  // Enfore the charset to be UTF-8
  header('Content-type: text/html; charset=utf-8'); 
  echo $html;
  
  xslt_free($xh);
}

/** used to escape special XML characters */
function XMLStrFormat($str)
{
  $str = str_replace("&", "&amp;", $str);
  $str = str_replace("<", "&lt;", $str); 
  $str = str_replace(">", "&gt;", $str); 
  $str = str_replace("'", "&apos;", $str);  
  $str = str_replace("\"", "&quot;", $str); 
  $str = str_replace("\r", "", $str);
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
  $value = preg_replace_callback('/[\x1b]/',
    create_function('$match',
      '$decimal_value = hexdec(bin2hex($match[0]));
      return "&#".$decimal_value.";";'
    ), $value);
  return "<".$tag.">".XMLStrFormat($value)."</".$tag.">";
}

/** Report last my SQL error */
function add_last_sql_error($functionname,$projectid=0,$buildid=0,$resourcetype=0,$resourceid=0)
{
  $pdo_error = pdo_error();
  if(strlen($pdo_error)>0)
    {
    add_log("SQL error: ".$pdo_error,$functionname,LOG_ERR,$projectid,$buildid,$resourcetype,$resourceid);
    $text = "SQL error in $functionname():".$pdo_error."<br>";
    echo $text;
    }
}

/** Catch any PHP fatal errors */
//
// This is a registered shutdown function (see register_shutdown_function help)
// and gets called at script exit time, regardless of reason for script exit.
// i.e. -- it gets called when a script exits normally, too.
//
global $PHP_ERROR_BUILD_ID;
global $PHP_ERROR_RESOURCE_TYPE;
global $PHP_ERROR_RESOURCE_ID;

function PHPErrorHandler($projectid)
{
  if (connection_aborted())
    {
    add_log('PHPErrorHandler', "connection_aborted()='".connection_aborted()."'", LOG_INFO, $projectid);
    add_log('PHPErrorHandler', "connection_status()='".connection_status()."'", LOG_INFO, $projectid);
    }

  if ($error = error_get_last())
    {
    switch($error['type'])
      {
      case E_ERROR:
      case E_CORE_ERROR:
      case E_COMPILE_ERROR:
      case E_USER_ERROR:
        if(strlen($GLOBALS['PHP_ERROR_RESOURCE_TYPE'])==0) {$GLOBALS['PHP_ERROR_RESOURCE_TYPE']=0;}
        if(strlen($GLOBALS['PHP_ERROR_BUILD_ID'])==0) {$GLOBALS['PHP_ERROR_BUILD_ID']=0;}
        if(strlen($GLOBALS['PHP_ERROR_RESOURCE_ID'])==0) {$GLOBALS['PHP_ERROR_RESOURCE_ID']=0;}
      
        add_log('Fatal error:'.$error['message'],$error['file'].' ('.$error['line'].')',
                 LOG_ERR,$projectid,$GLOBALS['PHP_ERROR_BUILD_ID'],
                 $GLOBALS['PHP_ERROR_RESOURCE_TYPE'],$GLOBALS['PHP_ERROR_RESOURCE_ID']);
        exit();  // stop the script
        break;
      }
    }
}  // end PHPErrorHandler()

/** Set the CDash version number in the database */
function setVersion()
{
  include("cdash/config.php");
  include("cdash/version.php");
  require_once("cdash/pdo.php");

  $version = pdo_query("SELECT major FROM version");
  if(pdo_num_rows($version) == 0)
    {
    pdo_query("INSERT INTO version (major,minor,patch) 
               VALUES ($CDASH_VERSION_MAJOR,$CDASH_VERSION_MINOR,$CDASH_VERSION_PATCH)");
    }
  else
    {
    pdo_query("UPDATE version SET major=$CDASH_VERSION_MAJOR,
                                  minor=$CDASH_VERSION_MINOR,
                                  patch=$CDASH_VERSION_PATCH");
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
  if($projectid==0 && pdo_num_rows(pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$userid' AND admin='1'"))==0)
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
    $project = pdo_query("SELECT public FROM project WHERE id='$projectid'");
    $project_array = pdo_fetch_array($project);
    
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
        LoginForm(0);
        exit(0);
        }
      else
        {
        return false;
        }
      }
    else if($userid)
      {
      $user2project = pdo_query("SELECT projectid FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
      if(pdo_num_rows($user2project) == 0)
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
  include("cdash/config.php");
  require_once("cdash/pdo.php");
  foreach (glob($CDASH_BACKUP_DIRECTORY."/*.xml") as $filename) 
    {
    if(time()-filemtime($filename) > $CDASH_BACKUP_TIMEFRAME*3600)
      {
      unlink($filename);
      }
    }
}

/** Backup an XML file */
/*function backup_xml_file($parser,$contents,$projectid)
{
  
  // If the content of the file is empty we return
  if(strlen($contents)==0)
    {
    return; 
    }

  include("cdash/config.php");
  require_once("cdash/pdo.php");
   
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
}*/

/** return an array of projects */
function get_projects()
{
  $projects = array();
  
  include("cdash/config.php");
  require_once("cdash/pdo.php");

  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME",$db);

  $projectres = pdo_query("SELECT id,name,description FROM project WHERE public='1' ORDER BY name");
  while($project_array = pdo_fetch_array($projectres))
    {
    $project = array();
    $project['id'] = $project_array["id"];  
    $project['name'] = $project_array["name"];
    $project['description'] = $project_array["description"];
    $projectid = $project['id'];
    
    $project['last_build'] = "NA";
    $lastbuildquery = pdo_query("SELECT submittime FROM build WHERE projectid='$projectid' ORDER BY submittime DESC LIMIT 1");
    if(pdo_num_rows($lastbuildquery)>0)
      {
      $lastbuild_array = pdo_fetch_array($lastbuildquery);
      $project['last_build'] = $lastbuild_array["submittime"];
      }

    $project['first_build'] = "NA";
    $firstbuildquery = pdo_query("SELECT starttime FROM build WHERE projectid='$projectid' AND starttime>'2000-01-01' ORDER BY starttime ASC LIMIT 1");
    if(pdo_num_rows($firstbuildquery)>0)
      {
      $firstbuild_array = pdo_fetch_array($firstbuildquery);
      $project['first_build'] = $firstbuild_array["starttime"];
      }

    $buildquery = pdo_query("SELECT count(id) FROM build WHERE projectid='$projectid'");
    $buildquery_array = pdo_fetch_array($buildquery); 
    $project['nbuilds'] = $buildquery_array[0];
    
    $projects[] = $project; 
    }
    
  return $projects;
}

/** Get the build id from stamp, name and buildname */
function get_build_id($buildname,$stamp,$projectid,$sitename)
{  
  if(!is_numeric($projectid))
    {
    return;
    }
  
  $buildname = pdo_real_escape_string($buildname);
  $stamp = pdo_real_escape_string($stamp);
  
  $sql = "SELECT build.id AS id FROM build,site WHERE build.name='$buildname' AND build.stamp='$stamp'";
  $sql .= " AND build.projectid='$projectid'";
  $sql .= " AND build.siteid=site.id AND site.name='$sitename'"; 
  $sql .= " ORDER BY build.id DESC";
  
  $build = pdo_query($sql);
  if(pdo_num_rows($build)>0)
    {
    $build_array = pdo_fetch_array($build);
    return $build_array["id"];
    }
  return -1;
}

/** Get the project id from the project name */
function get_project_id($projectname)
{
  $projectname = pdo_real_escape_string($projectname);
  $project = pdo_query("SELECT id FROM project WHERE name='$projectname'");
  if(pdo_num_rows($project)>0)
    {
    $project_array = pdo_fetch_array($project);
    return $project_array["id"];
    }
  return -1;
}

/** Get the project name from the project id */
function get_project_name($projectid)
{
  if(!isset($projectid) || !is_numeric($projectid))
    {
    echo "Not a valid projectid!";
    return;
    }

  $project = pdo_query("SELECT name FROM project WHERE id='$projectid'");
  if(pdo_num_rows($project)>0)
    {
    $project_array = pdo_fetch_array($project);
    return $project_array["name"];
    }
    
  return "NA";
}

/** strip slashes from the post if magic quotes are on */
function stripslashes_if_gpc_magic_quotes( $string ) 
{
  if(get_magic_quotes_gpc()) 
    {
    return stripslashes($string);
    } 
  else 
    {
    return $string;
    }
}

/** Get the current URI of the dashboard */
function get_server_URI()
{
  include("cdash/config.php");
  $currentPort="";
  $httpprefix="http://";
  if($_SERVER['SERVER_PORT']!=80 && $_SERVER['SERVER_PORT']!=443)
    {
    $currentPort=":".$_SERVER['SERVER_PORT'];
    }
  if($_SERVER['SERVER_PORT']==443 || $CDASH_USE_HTTPS === true)
    {
    $httpprefix = "https://";
    }
  $serverName = $CDASH_SERVER_NAME;
  if(strlen($serverName) == 0)
    {
    $serverName = $_SERVER['SERVER_NAME'];
    }
  $currentURI =  $httpprefix.$serverName.$currentPort.$_SERVER['REQUEST_URI']; 
  $currentURI = substr($currentURI,0,strrpos($currentURI,"/"));
  return $currentURI;
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
        
  $site2user = pdo_query("SELECT * FROM site2user WHERE siteid='$siteid' AND userid='$userid'");
  if(pdo_num_rows($site2user) == 0)
    {
    pdo_query("INSERT INTO site2user (siteid,userid) VALUES ('$siteid','$userid')");
    add_last_sql_error("add_site2user");
    }
}

/** remove a user to a site */
function remove_site2user($siteid,$userid)
{
  pdo_query("DELETE FROM site2user WHERE siteid='$siteid' AND userid='$userid'");
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
  include("cdash/config.php");
  require_once("cdash/pdo.php");
 
  // Security checks
  if(!is_numeric($siteid))
    {
    return;
    }
    
  $latitude = pdo_real_escape_string($latitude);
  $longitude = pdo_real_escape_string($longitude);
  $ip = pdo_real_escape_string($ip);
  $name = pdo_real_escape_string($name);
  $processoris64bits = pdo_real_escape_string($processoris64bits);
  $processorvendor = pdo_real_escape_string($processorvendor);
  $processorvendorid = pdo_real_escape_string($processorvendorid);
  $processorfamilyid = pdo_real_escape_string($processorfamilyid);
  $processormodelid = pdo_real_escape_string($processormodelid);
  $processorcachesize = pdo_real_escape_string($processorcachesize);
  $numberlogicalcpus = pdo_real_escape_string($numberlogicalcpus);
  $numberphysicalcpus = pdo_real_escape_string($numberphysicalcpus);
  $totalvirtualmemory = round(pdo_real_escape_string($totalvirtualmemory));
  $totalphysicalmemory = round(pdo_real_escape_string($totalphysicalmemory));
  $logicalprocessorsperphysical = round(pdo_real_escape_string($logicalprocessorsperphysical));
  $processorclockfrequency = round(pdo_real_escape_string($processorclockfrequency));
  $description = pdo_real_escape_string($description);
 
  // Update the basic information first
  pdo_query ("UPDATE site SET name='$name',ip='$ip',latitude='$latitude',longitude='$longitude' WHERE id='$siteid'"); 
 
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
 $query = pdo_query("SELECT * from siteinformation WHERE siteid='$siteid' ORDER BY timestamp DESC LIMIT 1");
 if(pdo_num_rows($query)==0)
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
  $query_array = pdo_fetch_array($query);
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
  $now = gmdate(FMT_DATETIME);
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
  pdo_query ($sql);
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
  
     pdo_query ($sql); 
   add_last_sql_error("update_site",$sql);
  }
}      

/** Get the geolocation from IP address */
function get_geolocation($ip)
{  
  include("cdash/config.php");
  require_once("cdash/pdo.php");
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
  curl_setopt($curl, CURLOPT_TIMEOUT, 5); // if we cannot get the geolocation in 5 seconds we quit
      
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
/*function add_site($name, $attributes)
{
  include("cdash/config.php");
  require_once("cdash/pdo.php");
  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME",$db);

 @$processoris64bits=$attributes["IS64BITS"];
 @$processorvendor=$attributes["VENDORSTRING"]; 
 @$processorvendorid=$attributes["VENDORID"]; 
 @$processorfamilyid=$attributes["FAMILYID"]; 
 @$processormodelid=$attributes["MODELID"]; 
 @$processorcachesize=$attributes["PROCESSORCACHESIZE"]; 
 @$numberlogicalcpus=$attributes["NUMBEROFLOGICALCPU"]; 
 @$numberphysicalcpus=$attributes["NUMBEROFPHYSICALCPU"]; 
 @$totalvirtualmemory=$attributes["TOTALVIRTUALMEMORY"]; 
 @$totalphysicalmemory=$attributes["TOTALPHYSICALMEMORY"]; 
 @$logicalprocessorsperphysical=$attributes["LOGICALPROCESSORSPERPHYSICAL"]; 
 @$processorclockfrequency=$attributes["PROCESSORCLOCKFREQUENCY"]; 
 $description="";
 $ip = $_SERVER['REMOTE_ADDR'];
 
 
  $ip = pdo_real_escape_string($ip);
  $processoris64bits = pdo_real_escape_string($processoris64bits);
  $processorvendor = pdo_real_escape_string($processorvendor);
  $processorvendorid = pdo_real_escape_string($processorvendorid);
  $processorfamilyid = pdo_real_escape_string($processorfamilyid);
  $processormodelid = pdo_real_escape_string($processormodelid);
  $processorcachesize = pdo_real_escape_string($processorcachesize);
  $numberlogicalcpus = pdo_real_escape_string($numberlogicalcpus);
  $numberphysicalcpus = pdo_real_escape_string($numberphysicalcpus);
  $totalvirtualmemory = round(pdo_real_escape_string($totalvirtualmemory));
  $totalphysicalmemory = round(pdo_real_escape_string($totalphysicalmemory));
  $logicalprocessorsperphysical = round(pdo_real_escape_string($logicalprocessorsperphysical));
  $processorclockfrequency = round(pdo_real_escape_string($processorclockfrequency));

  // Check if we already have the site registered
  $site = pdo_query("SELECT id,name,latitude,longitude FROM site WHERE name='$name'");
  if(pdo_num_rows($site)>0)
    {
    $site_array = pdo_fetch_array($site);
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

  if(!pdo_query ("INSERT INTO site (name,ip,latitude,longitude) 
                    VALUES ('$name','$ip','$latitude','$longitude')"))
    {
    echo "add_site = ".pdo_error();  
    }
  
  $siteid = pdo_insert_id("site");
 
  // Insert the site information
  $now = gmdate(FMT_DATETIME);
  pdo_query ("INSERT INTO siteinformation (siteid,
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
}*/

/* remove all builds for a project */
function remove_project_builds($projectid)
{
  if(!is_numeric($projectid))
    {
    return;
    }
    
  $build = pdo_query("SELECT id FROM build WHERE projectid='$projectid'");
  while($build_array = pdo_fetch_array($build))
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

  pdo_query("DELETE FROM build2group WHERE buildid='$buildid'");
  pdo_query("DELETE FROM builderror WHERE buildid='$buildid'");
  pdo_query("DELETE FROM buildemail WHERE buildid='$buildid'");
   
  pdo_query("DELETE FROM buildinformation WHERE buildid='$buildid'");
  pdo_query("DELETE FROM builderrordiff WHERE buildid='$buildid'");   
  pdo_query("DELETE FROM buildupdate WHERE buildid='$buildid'");
   
  pdo_query("DELETE FROM configure WHERE buildid='$buildid'");
  pdo_query("DELETE FROM configureerror WHERE buildid='$buildid'");
  pdo_query("DELETE FROM configureerrordiff WHERE buildid='$buildid'");
  pdo_query("DELETE FROM coveragesummarydiff WHERE buildid='$buildid'");
  pdo_query("DELETE FROM testdiff WHERE buildid='$buildid'");
  pdo_query("DELETE FROM buildtesttime WHERE buildid='$buildid'");
  pdo_query("DELETE FROM summaryemail WHERE buildid='$buildid'");
  pdo_query("DELETE FROM errorlog WHERE buildid='$buildid'");
   
  // Remove the buildfailureargument
  $buildfailure = pdo_query("SELECT id FROM buildfailure WHERE buildid='$buildid'");
  while($buildfailure_array = pdo_fetch_array($buildfailure))
    {
    $buildfailureid = $buildfailure_array['id'];
    pdo_query("DELETE FROM buildfailure2argument WHERE buildfailureid='$buildfailureid'");
    pdo_query("DELETE FROM label2buildfailure WHERE buildfailureid='$buildfailureid'");
      // Don't delete the arguments for now...
    }
  
  pdo_query("DELETE FROM buildfailure WHERE buildid='$buildid'");      
          
  // coverage file are kept unless they are shared
  $coverage = pdo_query("SELECT fileid FROM coverage WHERE buildid='$buildid'");
  while($coverage_array = pdo_fetch_array($coverage))
    {
    $fileid = $coverage_array["fileid"];
    // Make sur the file is not shared
    $numfiles = pdo_query("SELECT count(*) AS c FROM coveragefile WHERE id='$fileid'");
    $numfiles_array = pdo_fetch_array($numfiles);
    if($numfiles_array['c']==1)
      {
      pdo_query("DELETE FROM coveragefile WHERE id='$fileid'"); 
      pdo_query("DELETE FROM label2coveragefile WHERE coveragefileid='$fileid' AND buildid=".qnum($buildid)); 
      }
    }
  
  pdo_query("DELETE FROM coverage WHERE buildid='$buildid'");
  pdo_query("DELETE FROM coveragefilelog WHERE buildid='$buildid'");
  pdo_query("DELETE FROM coveragesummary WHERE buildid='$buildid'");

  // dynamicanalysisdefect
  $dynamicanalysis = pdo_query("SELECT id FROM dynamicanalysis WHERE buildid='$buildid'");
  while($dynamicanalysis_array = pdo_fetch_array($dynamicanalysis))
    {
    $dynid = $dynamicanalysis_array["id"];
    pdo_query("DELETE FROM dynamicanalysisdefect WHERE dynamicanalysisid='$dynid'"); 
    pdo_query("DELETE FROM label2dynamicanalysis WHERE dynamicanalysisid='$dynid'"); 
    }
   
  pdo_query("DELETE FROM dynamicanalysis WHERE buildid='$buildid'");
  pdo_query("DELETE FROM updatefile WHERE buildid='$buildid'");   
  
  // Delete the note if not shared
  $build2note = pdo_query("SELECT * FROM build2note WHERE buildid='$buildid'");
  while($build2note_array = pdo_fetch_array($build2note))
    {
    $noteid = $build2note_array["noteid"];
    if(pdo_num_rows(pdo_query("SELECT * FROM build2note WHERE noteid='$noteid'"))==1)
      {
      // Note is not shared we delete
      pdo_query("DELETE FROM note WHERE id='$noteid'");
      }
    }
  pdo_query("DELETE FROM build2note WHERE buildid='$buildid'"); 
  
  // Delete the test if not shared
  $build2test = pdo_query("SELECT * FROM build2test WHERE buildid='$buildid'");
  while($build2test_array = pdo_fetch_array($build2test))
    {
    $testid = $build2test_array["testid"];
    if(pdo_num_rows(pdo_query("SELECT * FROM build2test WHERE testid='$testid'"))==1)
      {
      // Check if the images for the test are not shared
      $test2image = pdo_query("SELECT imgid FROM test2image WHERE testid='$testid'");
      while($test2image_array = pdo_fetch_array($test2image))
        {
        $imgid = $test2image_array["imgid"];
        // Check if the test images are shared
        if(pdo_num_rows(pdo_query("SELECT * FROM test2image WHERE imgid='$imgid'"))==1)
          {
          pdo_query("DELETE FROM image WHERE id='$imgid'");
          }
        }
      // Tests are not shared we delete
      pdo_query("DELETE FROM testmeasurement WHERE testid='$testid'");
      pdo_query("DELETE FROM test WHERE id='$testid'");
      pdo_query("DELETE FROM test2image WHERE testid='$testid'");
      pdo_query("DELETE FROM label2test WHERE testid='$testid' AND buildid='$buildid'");
      }
    }
  pdo_query("DELETE FROM build2test WHERE buildid='$buildid'"); 

  // Delete the uploaded files if not shared
  $build2uploadfiles = pdo_query("SELECT fileid FROM build2uploadfile WHERE buildid='$buildid'");
  while($build2uploadfile_array = pdo_fetch_array($build2uploadfiles))
    {
    $fileid = $build2uploadfile_array['fileid'];
    if(pdo_num_rows(pdo_query("SELECT * FROM build2uploadfile WHERE fileid='$fileid' AND buildid != '$buildid'")) == 0)
      {
      pdo_query("DELETE FROM uploadfile WHERE id='$fileid'");
      }
    }
  pdo_query("DELETE FROM build2uploadfile WHERE buildid='$buildid'");

  // Delete the subproject
  pdo_query("DELETE FROM subproject2build WHERE buildid='$buildid'"); 

  // Delete the labels
  pdo_query("DELETE FROM label2build WHERE buildid='$buildid'");
   
  // Only delete the buildid at the end so that no other build can get it in the meantime
  pdo_query("DELETE FROM build WHERE id='$buildid'");

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

/** Get year from formatted date */
function date2year($date)
{
  return substr($date,0,4);
}

/** Get month from formatted date */
function date2month($date)
{
  return is_numeric(substr($date,4,1)) ? substr($date,4,2) : substr($date,5,2);
}

/** Get day from formatted date */
function date2day($date)
{
  return is_numeric(substr($date,4,1)) ? substr($date,6,2) : substr($date,8,2);
}

/** Get hour from formatted time */
function time2hour($time)
{
  return substr($time,0,2);
}

/** Get minute from formatted time */
function time2minute($time)
{
  return is_numeric(substr($time,2,1)) ? substr($time,2,2) : substr($time,3,2);
}

/** Get second from formatted time */
function time2second($time)
{
  return is_numeric(substr($time,2,1)) ? substr($time,4,2) : substr($time,6,2);
}

/** Get dates 
 * today: the *starting* timestamp of the current dashboard
 * previousdate: the date in Y-m-d format of the previous dashboard
 * nextdate: the date in Y-m-d format of the next dashboard
 */
function get_dates($date,$nightlytime)
{
  $nightlytime = strtotime($nightlytime);

  $nightlyhour = date("H",$nightlytime);
  $nightlyminute = date("i",$nightlytime);
  $nightlysecond = date("s",$nightlytime);
 
  if(!isset($date) || strlen($date)==0)
    { 
    $date = date(FMT_DATE); // the date is always the date of the server
    
    if(date(FMT_TIME)>date(FMT_TIME,$nightlytime))
      {
      $date = date(FMT_DATE,time()+3600*24); //next day
      }
    }
  else
    {  
     // If the $nightlytime is in the morning it's actually the day after
    if(date(FMT_TIME,$nightlytime)<'12:00:00')
      {
      $date = date(FMT_DATE,strtotime($date)+3600*24); // previous date
      }  
     }
    
  $today = mktime($nightlyhour,$nightlyminute,$nightlysecond,date2month($date),date2day($date),date2year($date))-3600*24; // starting time

  // If the $nightlytime is in the morning it's actually the day after
  if(date(FMT_TIME,$nightlytime)<'12:00:00')
    {
    $date = date(FMT_DATE,strtotime($date)-3600*24); // previous date
    }  

  $todaydate = mktime(0,0,0,date2month($date),date2day($date),date2year($date)); 
  $previousdate = date(FMT_DATE,$todaydate-3600*24);
  $nextdate = date(FMT_DATE,$todaydate+3600*24);

  return array($previousdate, $today, $nextdate, $date);
}

function has_next_date($date, $currentstarttime)
{
  return (
    isset($date) &&
    strlen($date)>=8 &&
    date(FMT_DATE, $currentstarttime)<date(FMT_DATE));
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
  $result = pdo_query($query);
  if(!$result)
    {
    return 0;
    }

  $row = pdo_fetch_array($result);
  return $row["imageid"];
}


function get_project_properties($projectname)
{
  include("cdash/config.php");
  require_once("cdash/pdo.php");

  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
  if(!$db)
    {
    echo "Error connecting to CDash database server<br>\n";
    exit(0);
    }

  if(!pdo_select_db("$CDASH_DB_NAME",$db))
    {
    echo "Error selecting CDash database<br>\n";
    exit(0);
    }

  $projectname = pdo_real_escape_string($projectname);
  $project = pdo_query("SELECT * FROM project WHERE name='$projectname'");
  if(pdo_num_rows($project)>0)
    {
    $project_props = pdo_fetch_array($project);
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
function get_author_email($projectname, $author)
{
  include("cdash/config.php");
  require_once("cdash/pdo.php");

  $projectid = get_project_id($projectname);
  if($projectid == -1)
    {
    return "unknownProject";
    }

  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
  if(!$db)
    {
    echo "Error connecting to CDash database server<br>\n";
    exit(0);
    }

  if(!pdo_select_db("$CDASH_DB_NAME",$db))
    {
    echo "Error selecting CDash database<br>\n";
    exit(0);
    }

  $qry = pdo_query("SELECT email FROM ".qid("user")." WHERE id IN 
  (SELECT up.userid FROM user2project AS up, user2repository AS ur
   WHERE ur.userid=up.userid AND up.projectid='$projectid' 
   AND ur.credential='$author' AND (ur.projectid=0 OR ur.projectid='$projectid')
   ) LIMIT 1");

  $email = "";

  if(pdo_num_rows($qry) === 1)
    {
    $results = pdo_fetch_array($qry);
    $email = $results["email"];
    }
    
  return $email;  
}

/** Get the previous build id */
function get_previous_buildid($projectid,$siteid,$buildtype,$buildname,$starttime)
{
  $previousbuild = pdo_query("SELECT id FROM build
                              WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                              AND projectid='$projectid' AND starttime<'$starttime' ORDER BY starttime DESC LIMIT 1");
  
  if(pdo_num_rows($previousbuild)>0)
    {
    $previousbuild_array = pdo_fetch_array($previousbuild);              
    return $previousbuild_array["id"];
    }
  return 0;
}

/** Get the next build id */
function get_next_buildid($projectid,$siteid,$buildtype,$buildname,$starttime)
{
  $nextbuild = pdo_query("SELECT id FROM build
                          WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                          AND projectid='$projectid' AND starttime>'$starttime' ORDER BY starttime ASC LIMIT 1");

  if(pdo_num_rows($nextbuild)>0)
    {
    $nextbuild_array = pdo_fetch_array($nextbuild);              
    return $nextbuild_array["id"];
    }
  return 0;
}

/** Get the last build id */
function get_last_buildid($projectid,$siteid,$buildtype,$buildname,$starttime)
{
 
   $nextbuild = pdo_query("SELECT id FROM build
                          WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                          AND projectid='$projectid' ORDER BY starttime DESC LIMIT 1");

  if(pdo_num_rows($nextbuild)>0)
    {
    $nextbuild_array = pdo_fetch_array($nextbuild);              
    return $nextbuild_array["id"];
    }
  return 0;
}

/** Get the previous build id dynamicanalysis*/
function get_previous_buildid_dynamicanalysis($projectid,$siteid,$buildtype,$buildname,$starttime)
{
  $previousbuild = pdo_query("SELECT build.id FROM build,dynamicanalysis
                              WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                              AND build.projectid='$projectid' AND build.starttime<'$starttime' 
                              AND dynamicanalysis.buildid=build.id
                              ORDER BY build.starttime DESC LIMIT 1");
 
  if(pdo_num_rows($previousbuild)>0)
    {
    $previousbuild_array = pdo_fetch_array($previousbuild);              
    return $previousbuild_array["id"];
    }
  return 0;
}

/** Get the next build id dynamicanalysis*/
function get_next_buildid_dynamicanalysis($projectid,$siteid,$buildtype,$buildname,$starttime)
{
  $nextbuild = pdo_query("SELECT build.id FROM build,dynamicanalysis
                          WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                          AND build.projectid='$projectid' AND build.starttime>'$starttime' 
                          AND dynamicanalysis.buildid=build.id
                          ORDER BY build.starttime ASC LIMIT 1");

  if(pdo_num_rows($nextbuild)>0)
    {
    $nextbuild_array = pdo_fetch_array($nextbuild);              
    return $nextbuild_array["id"];
    }
  return 0;
}

/** Get the last build id dynamicanalysis */
function get_last_buildid_dynamicanalysis($projectid,$siteid,$buildtype,$buildname,$starttime)
{
 
   $nextbuild = pdo_query("SELECT build.id FROM build,dynamicanalysis
                          WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                          AND build.projectid='$projectid' 
                          AND dynamicanalysis.buildid=build.id
                          ORDER BY build.starttime DESC LIMIT 1");

  if(pdo_num_rows($nextbuild)>0)
    {
    $nextbuild_array = pdo_fetch_array($nextbuild);              
    return $nextbuild_array["id"];
    }
  return 0;
}

/** Get the date from the buildid */
function get_dashboard_date_from_build_starttime($starttime,$nightlytime)
{
  $nightlytime = strtotime($nightlytime)-1; // in case it's midnight
  $starttime = strtotime($starttime." GMT");

  $date = date(FMT_DATE,$starttime);
  if(date(FMT_TIME,$starttime)>date(FMT_TIME,$nightlytime))
    {
    $date = date(FMT_DATE,$starttime+3600*24); //next day
    }
    
  // If the $nightlytime is in the morning it's actually the day after
  if(date(FMT_TIME,$nightlytime)<'12:00:00')
    {
    $date = date(FMT_DATE,strtotime($date)-3600*24); // previous date
    } 
  return $date;
}

function get_dashboard_date_from_project($projectname, $date)
{
  $project = pdo_query("SELECT nightlytime FROM project WHERE name='$projectname'");
  $project_array = pdo_fetch_array($project);
  
  $nightlytime = strtotime($project_array["nightlytime"]);
  $nightlyhour = date("H",$nightlytime);
  $nightlyminute = date("i",$nightlytime);
  $nightlysecond = date("s",$nightlytime);

  if(!isset($date) || strlen($date)==0)
    { 
    $date = date(FMT_DATE); // the date is always the date of the server
    
    if(date(FMT_TIME)>date(FMT_TIME,$nightlytime))
      {
      $date = date(FMT_DATE,time()+3600*24); //next day
      } 
    }
  
  return $date;  
}

function get_cdash_dashboard_xml($projectname, $date)
{
  include("cdash/config.php");
  require_once("cdash/pdo.php");

  $projectid = get_project_id($projectname);
  if($projectid == -1)
    {
    return;
    }

  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
  if(!$db)
    {
    echo "Error connecting to CDash database server<br>\n";
    exit(0);
    }

  if(!pdo_select_db("$CDASH_DB_NAME",$db))
    {
    echo "Error selecting CDash database<br>\n";
    exit(0);
    }

  $project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
  if(pdo_num_rows($project)>0)
    {
    $project_array = pdo_fetch_array($project);
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
  <projectname_encoded>".urlencode($project_array["name"])."</projectname_encoded>
  <projectpublic>".$project_array["public"]."</projectpublic>
  <previousdate>".$previousdate."</previousdate>
  <nextdate>".$nextdate."</nextdate>
  <logoid>".getLogoID($projectid)."</logoid>";
    
  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/models/proProject.php"))
    {
    include_once("local/models/proProject.php");
    $pro= new proProject; 
    $pro->ProjectId=$projectid;
    $xml.="<proedition>".$pro->GetEdition(1)."</proedition>";
    }
  $xml .="</dashboard>";
  
  $userid = 0;
  if(isset($_SESSION['cdash']))
    {
    $xml .= "<user>";
    $userid = $_SESSION['cdash']['loginid'];
    $xml .= add_XML_value("id",$userid);
   
    // Is the user super administrator
    $userquery = pdo_query("SELECT admin FROM ".qid('user')." WHERE id='$userid'");
    $user_array = pdo_fetch_array($userquery);
    $xml .= add_XML_value("admin",$user_array[0]);

    // Is the user administrator of the project
    $userquery = pdo_query("SELECT role FROM user2project WHERE userid=".qnum($userid)." AND projectid=".qnum($projectid));
    $user_array = pdo_fetch_array($userquery);
    $xml .= add_XML_value("projectrole",$user_array[0]);
    
    $xml .= "</user>";
    }

  return $xml;
}

/** */
function get_cdash_dashboard_xml_by_name($projectname, $date)
{
  return get_cdash_dashboard_xml($projectname, $date);
}

function get_previous_revision($revision)
{
  // Split revision into components based on any "." separators:
  //
  $revcmps = explode(".", $revision);
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
  if(strlen($projecturl)==0)
    {
    return "";
    }
    
  $cmps = explode("?", $projecturl);

  // If $cmps[1] starts with "root=" and the $directory value starts
  // with "whatever comes after that" then remove that bit from directory:
  //
  @$npos = strpos($cmps[1], "root=");
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
    @$diff_url = $cmps[0] . $dircmp . $file ."?".$cmps[1];
    }

  return make_cdash_url($diff_url);
}


/** Return the Trac URL */
function get_trac_diff_url($projecturl, $directory, $file, $revision)
{
  $filename = $file;
  
  if($directory != "")
    {
    $filename = $directory."/".$file;
    }
 
  if($revision != '')
    {
    $diff_url = $projecturl."/changeset/".$revision."/".$filename;
    }
  else // no revision
    {
    $diff_url = $projecturl."/browser/".$filename;
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


/** Return the ViewVC URL */
function get_viewvc_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $prev_revision = get_previous_revision($revision);
    if($prev_revision != $revision) //diff
      {
      $diff_url = $projecturl."/?action=browse&path=".($directory ? ($directory) : "")."/".$file;
      $diff_url .= "&r1=".$prev_revision."&r2=".$revision;
      } 
    else //view
      {
      $diff_url = $projecturl."/?action=browse&path=".($directory ? ($directory) : "")."/".$file;
      $diff_url .= "&revision=".$revision."&view=markup";
      }
    } 
  else //log
    {
    $diff_url = $projecturl."/?action=browse&path=".($directory ? ($directory) : "")."/".$file."&view=log";
    }

  return make_cdash_url($diff_url);
}

/** Return the viewVC 1-1 url */
function get_viewvc_1_1_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $prev_revision = get_previous_revision($revision);
    if($prev_revision != $revision) //diff
      {
      $diff_url = $projecturl."/".($directory ? ($directory) : "")."/".$file;
      $diff_url .= "?r1=".$prev_revision."&r2=".$revision;
      } 
    else //view
      {
      $diff_url = $projecturl."/".($directory ? ($directory) : "")."/".$file;
      $diff_url .= "?revision=".$revision."&view=markup";
      }
    } 
  else //log
    {
    $diff_url = $projecturl."/".($directory ? ($directory) : "")."/".$file."?view=log";
    }

  return make_cdash_url($diff_url);
}

/** Return the WebSVN URL */
function get_websvn_diff_url($projecturl, $directory, $file, $revision)
{
  $repname = "";
  $root = "";
  // find the repository name
  $pos_repname = strpos($projecturl,"repname=");
  if($pos_repname !== false)
    {
    $pos_repname_end = strpos($projecturl,"&",$pos_repname+1);
    if($pos_repname_end !== false)
      {
      $repname = substr($projecturl,$pos_repname,$pos_repname_end-$pos_repname);
      }
    else
      {
      $repname = substr($projecturl,$pos_repname);
      }
    }
  
  // find the root name
  $pos_root = strpos($projecturl,"path=");
  if($pos_root !== false)
    {
    $pos_root_end = strpos($projecturl,"&",$pos_root+1);
    if($pos_root_end !== false)
      {
      $root = substr($projecturl,$pos_root+5,$pos_root_end-$pos_root-5);
      }
    else
      {
      $root = substr($projecturl,$pos_root+5);
      }
    }
  
  
  // find the project url
  $pos_dotphp = strpos($projecturl,".php?");
  if($pos_dotphp !== false)
    {
    $projecturl = substr($projecturl,0,$pos_dotphp);
    $pos_slash = strrpos($projecturl,"/");
    $projecturl = substr($projecturl,0,$pos_slash);
    }

  if($revision != '')
    {
    $prev_revision = get_previous_revision($revision);
    if($prev_revision != $revision) //diff
      {
      $diff_url = $projecturl."/diff.php?".$repname."&path=".$root.($directory ? "/".($directory) : "")."/".$file;
      $diff_url .= "&rev=".$revision."&sc=1";
      } 
    else //view
      {
      $diff_url = $projecturl."/filedetails.php?".$repname."&path=".$root.($directory ? "/".($directory) : "")."/".$file;
      $diff_url .= "&rev=".$revision;
      }
    } 
  else //log 
    {
    $diff_url = $projecturl."/log.php?".$repname."&path=".$root.($directory ? "/".($directory) : "")."/".$file;
    $diff_url .= "&rev=0&sc=0&isdir=0";
    }

  return make_cdash_url($diff_url);
}

/** Return the Loggerhead URL */
function get_loggerhead_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $diff_url = $projecturl."/revision/".$revision.($directory ? ("/".$directory) : "")."/".$file;
    } 
  else
    {
    $diff_url = $projecturl."/changes/head:/".($directory ? ($directory) : "")."/".$file;
    }

  return make_cdash_url($diff_url);
}

/** Return the GitWeb diff URL */
function get_gitweb_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $diff_url = $projecturl . ";a=commitdiff;h=" . $revision;
    }
  else if ($file != '')
    {
    $diff_url = $projecturl . ";a=blob;f=";
    if ($directory != '')
      {
      $diff_url .= $directory . "/";
      }
    $diff_url .= $file;
    }
  else
    {
    return '';
    }

  return make_cdash_url($diff_url);
}

/** Return the Gitorious/GitHub diff URL */
function get_gitoriousish_diff_url($projecturl, $directory, $file, $revision, $blobs, $branch='master')
{
  if ($revision != '')
    {
    $diff_url = $projecturl . "/commit/" . $revision;
    }
  else if ($file != '')
    {
    $diff_url = $projecturl . "/" . $blobs . "/" . $branch . "/";
    if ($directory != '')
      {
      $diff_url .= $directory . "/";
      }
    $diff_url .= $file;
    }
  else
    {
    return '';
    }

  return make_cdash_url($diff_url);
}

/** Return the Gitorious diff URL */
function get_gitorious_diff_url($projecturl, $directory, $file, $revision)
{
  // Gitorious uses 'blobs' or 'trees' (plural)
  return get_gitoriousish_diff_url($projecturl, $directory, $file, $revision, 'blobs');
}

/** Return the GitHub diff URL */
function get_github_diff_url($projecturl, $directory, $file, $revision)
{
  // GitHub uses 'blob' or 'tree' (singular, no s)
  return get_gitoriousish_diff_url($projecturl, $directory, $file, $revision, 'blob');
}

/** Return the cgit diff URL */
function get_cgit_diff_url($projecturl, $directory, $file, $revision)
{
  $diff_url = $projecturl . "/diff/";
  if($directory)
  {
    $diff_url .= $directory . "/";
  }
  $diff_url .= $file . "?id=" . $revision;
  return make_cdash_url($diff_url);
}

/** Get the diff url based on the type of viewer */
function get_diff_url($projectid, $projecturl, $directory, $file, $revision='')
{
  if(!is_numeric($projectid))
    {
    return;
    }

  $project = pdo_query("SELECT cvsviewertype FROM project WHERE id='$projectid'");
  $project_array = pdo_fetch_array($project);

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
  elseif($project_array["cvsviewertype"] == "viewvc")
    {
    return get_viewvc_diff_url($projecturl, $directory, $file, $revision);
    }
  elseif($project_array["cvsviewertype"] == "viewvc1.1")
    {
    return get_viewvc_1_1_diff_url($projecturl, $directory, $file, $revision);
    }
  elseif($project_array["cvsviewertype"] == "websvn")
    {
    return get_websvn_diff_url($projecturl, $directory, $file, $revision);
    }
  elseif($project_array["cvsviewertype"] == "loggerhead")
    {
    return get_loggerhead_diff_url($projecturl, $directory, $file, $revision);
    }
  elseif($project_array["cvsviewertype"] == "gitweb")
    {
    return get_gitweb_diff_url($projecturl, $directory, $file, $revision);
    }
  elseif($project_array["cvsviewertype"] == "gitorious")
    {
    return get_gitorious_diff_url($projecturl, $directory, $file, $revision);
    }
  elseif($project_array["cvsviewertype"] == "github")
    {
    return get_github_diff_url($projecturl, $directory, $file, $revision);
    }
  elseif($project_array["cvsviewertype"] == "cgit")
    {
    return get_cgit_diff_url($projecturl, $directory, $file, $revision);
    }
  else // default is viewcvs
    {
    return get_viewcvs_diff_url($projecturl, $directory, $file, $revision);
    }
}

/** Return the ViewCVS URL */
function get_viewcvs_revision_url($projecturl, $revision)
{
  $revision_url = $projecturl."&rev=".$revision;
  return make_cdash_url($revision_url);
}

/** Return the Trac URL */
function get_trac_revision_url($projecturl, $revision)
{
  $revision_url = $projecturl."/changeset/".$revision;
  return make_cdash_url($revision_url);
}

/** Return the Fisheye URL */
function get_fisheye_revision_url($projecturl, $revision)
{
  $revision_url = $projecturl."?r=".$revision;;
  return make_cdash_url($revision_url);
}

/** Return the CVSTrac URL */
function get_cvstrac_revision_url($projecturl, $revision)
{
  $revision_url = ""; // not implemented
  return make_cdash_url($revision_url);
}

/** Return the ViewVC URL */
function get_viewvc_revision_url($projecturl, $revision)
{
  $revision_url = $projecturl."?view=rev&revision=".$revision;
  return make_cdash_url($diff_url);
}

/** Return the viewVC 1-1 url */
function get_viewvc_1_1_revision_url($projecturl, $revision)
{
  $revision_url = ""; // not implemented
  return make_cdash_url($revision_url);
}

/** Return the WebSVN URL */
function get_websvn_revision_url($projecturl, $revision)
{
  $revision_url = $projecturl."?view=revision&revision=".$revision;
  return make_cdash_url($revision_url);
}

/** Return the Loggerhead URL */
function get_loggerhead_revision_url($projecturl, $revision)
{
  $revision_url = ""; // not implemented
  return make_cdash_url($revision_url);
}

/** Return the GitWeb revision URL */
function get_gitweb_revision_url($projecturl, $revision)
{
  $revision_url = $projecturl . ";a=shortlog;h=" . $revision;
  return make_cdash_url($revision_url);
}

/** Return the Gitorious revision URL */
function get_gitorious_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl . "/commits/";
  if ($priorrevision)
  {
    $revision_url .= $priorrevision . "..";
  }
  $revision_url .= $revision;
  return make_cdash_url($revision_url);
}

/** Return the GitHub revision URL */
function get_github_revision_url($projecturl, $revision, $priorrevision)
{
  return get_gitorious_revision_url($projecturl, $revision, $priorrevision);
}

/** Return the cgit revision URL */
function get_cgit_revision_url($projecturl, $revision)
{
  $revision_url = $projecturl . "/log/?id=" . $revision;
  return make_cdash_url($revision_url);
}

/** Return the global revision URL (not file based) for a repository */
function get_revision_url($projectid, $revision, $priorrevision)
{
  if(!is_numeric($projectid))
    {
    return;
    }

  $project = pdo_query("SELECT cvsviewertype,cvsurl FROM project WHERE id='$projectid'");
  $project_array = pdo_fetch_array($project);
  $projecturl = $project_array['cvsurl'];
  if($project_array["cvsviewertype"] == "trac")
    {
    return get_trac_revision_url($projecturl,$revision);
    }
  elseif($project_array["cvsviewertype"] == "fisheye")
    {
    return get_fisheye_revision_url($projecturl,$revision);
    }
  elseif($project_array["cvsviewertype"] == "cvstrac")
    {
    return get_cvstrac_revision_url($projecturl,$revision);
    }
  elseif($project_array["cvsviewertype"] == "viewvc")
    {
    return get_viewvc_revision_url($projecturl,$revision);
    }
  elseif($project_array["cvsviewertype"] == "viewvc1.1")
    {
    return get_viewvc_1_1_revision_url($projecturl,$revision);
    }
  elseif($project_array["cvsviewertype"] == "websvn")
    {
    return get_websvn_revision_url($projecturl,$revision);
    }
  elseif($project_array["cvsviewertype"] == "loggerhead")
    {
    return get_loggerhead_revision_url($projecturl,$revision);
    }
  elseif($project_array["cvsviewertype"] == "gitweb")
    {
    return get_gitweb_revision_url($projecturl,$revision);
    }
  elseif($project_array["cvsviewertype"] == "gitorious")
    {
    return get_gitorious_revision_url($projecturl,$revision,$priorrevision);
    }
  elseif($project_array["cvsviewertype"] == "github")
    {
    return get_github_revision_url($projecturl,$revision,$priorrevision);
    }
  elseif($project_array["cvsviewertype"] == "cgit")
    {
    return get_cgit_revision_url($projecturl,$revision);
    }
  else // default is viewcvs
    {
    return get_viewcvs_revision_url($projecturl,$revision);
    }
}

/** Quote SQL identifier */
function qid($id)
{
  global $CDASH_DB_TYPE;

  if(!isset($CDASH_DB_TYPE) || ($CDASH_DB_TYPE == "mysql")) 
    {
    return "`$id`";
    }
  elseif($CDASH_DB_TYPE == "pgsql") 
    {
    return "\"$id\"";
    }
  else 
    {
    return $id;
    }
}

/** Quote SQL interval specifier */
function qiv($iv)
{
  global $CDASH_DB_TYPE;

  if($CDASH_DB_TYPE == "pgsql") 
    {
    return "'$iv'";
    }
  else 
    {
    return $iv;
    }
}

/** Quote SQL number */
function qnum($num)
{
  global $CDASH_DB_TYPE;

  if(!isset($CDASH_DB_TYPE) || ($CDASH_DB_TYPE == "mysql")) 
    {
    return "'$num'";
    }
  elseif($CDASH_DB_TYPE == "pgsql") 
    {
    return $num != "" ? $num : "0";
    }
  else 
    {
    return $num;
    }
}

/** Return the list of site maintainers for a given project */
function find_site_maintainers($projectid)
{
  $userids = array();
  
  // Get the registered user first
  $site2user = pdo_query("SELECT site2user.userid FROM site2user,user2project 
                        WHERE site2user.userid=user2project.userid AND user2project.projectid='$projectid'");
  while($site2user_array = pdo_fetch_array($site2user))
    {
    $userids[] = $site2user_array["userid"];
    }

  // Then we list all the users that have been submitting in the past 48 hours 
  $submittime_UTCDate = gmdate(FMT_DATETIME,time()-3600*48);
  $site2project = pdo_query("SELECT DISTINCT  userid FROM site2user WHERE siteid IN 
                            (SELECT siteid FROM build WHERE projectid=$projectid 
                             AND submittime>'$submittime_UTCDate')");                           
  while($site2project_array = pdo_fetch_array($site2project))
    {
    $userids[] = $site2project_array["userid"];
    }
  
  return array_unique($userids);
}

/** Return formated time given time in minutes (that's how CTest returns the time */
function get_formated_time($minutes)
{
  $time_in_seconds = round($minutes*60);
  $hours=floor($time_in_seconds/3600);
  
  $remainingseconds = $time_in_seconds-$hours*3600;
  $minutes=floor($remainingseconds/60);
  $seconds = $remainingseconds-$minutes*60;  
  return $hours.":".str_pad($minutes, 2, "0", STR_PAD_LEFT).":".str_pad($seconds, 2, "0", STR_PAD_LEFT);  
}

/** Check the email category */
function check_email_category($name,$emailcategory)
{
  if($emailcategory>= 64)
    {
    if($name == "dynamicanalysis")
      {
      return true;
      }
    $emailcategory -= 64;
    }
    
  if($emailcategory>= 32)
    {
    if($name == "test")
      {
      return true;
      }
    $emailcategory -= 32;
    }

  if($emailcategory >= 16)
    {
    if($name == "error")
      {
      return true;
      }
    $emailcategory -= 16;
    }
    
  if($emailcategory >= 8)
    {
    if($name == "warning")
      {
      return true;
      }
    $emailcategory -= 8;
    }
  
  if($emailcategory >= 4)
    {
    if($name == "configure")
      {
      return true;
      }
    $emailcategory -= 4;
    }
      
  if($emailcategory >= 2)
    {
    if($name == "update")
      {
      return true;
      }
    }
      
  return false;
}

/** Return the byte value with proper extension */
function getByteValueWithExtension($value)
    {
    $valueext = "";
    if($value>1024)
      {
      $value /= 1024;
      $value = $value;
      $valueext = "K";
      }
    if($value>1024)
      {
      $value /= 1024;
      $value = $value;
      $valueext = "M";
      }
    if($value>1024)
      {
      $value /= 1024;
      $value = $value;        
      $valueext = "G";
      }    
    return round($value,2).$valueext;
    }

/** Given a query that returns a set of rows,
  * each of which contains a 'text' field,
  * construct a chunk of <labels><label>....
  * style xml
  */
function get_labels_xml_from_query_results($qry)
  {
  $xml = '';

  $rows = pdo_all_rows_query($qry);

  if (count($rows)>0)
    {
    $xml .= '<labels>';
    foreach($rows as $row)
      {
      $xml .= add_XML_value('label', $row['text']);
      }
    $xml .= '</labels>';
    }

  return $xml;
  }

function generate_web_api_key()
  {
  $keychars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  $length = 40;

  // seed with microseconds
  function make_seed_recoverpass()
    {
    list($usec, $sec) = explode(' ', microtime());
    return (float) $sec + ((float) $usec * 100000);
    }
  srand(make_seed_recoverpass());

  $key = "";
  $max=strlen($keychars)-1;
  for ($i=0;$i<$length;$i++)
    {
    $key .= substr($keychars, rand(0, $max), 1);
    }
  return $key;
  }

function create_web_api_token($projectid)
  {
  $token = generate_web_api_key();
  $expTime = gmdate(FMT_DATETIME,time()+3600); //hard-coding 1 hour for now
  pdo_query("INSERT INTO apitoken (projectid,token,expiration_date) VALUES ($projectid,'$token','$expTime')");
  clean_outdated_api_tokens();
  return $token;
  }
  
function clean_outdated_api_tokens()
  {
  $now = gmdate(FMT_DATETIME);
  pdo_query("DELETE FROM apitoken WHERE expiration_date < '$now'");
  }

/** 
  * Pass this a valid token created by create_web_api_token.
  * Returns true if token is valid, false otherwise.
  * Handles SQL escaping/validation of parameters.
  */
function web_api_authenticate($projectid, $token)
  {
  if(!is_numeric($projectid))
    {
    return false;
    }
  $now = gmdate(FMT_DATETIME);
  $token = pdo_real_escape_string($token);
  $result = pdo_query("SELECT * FROM apitoken WHERE projectid=$projectid AND token='$token' AND expiration_date > '$now'");
  return pdo_num_rows($result) != 0;
  }

?>
