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
$noforcelogin = 1;
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include("cdash/version.php");

@$buildid = $_GET["buildid"];
@$date = $_GET["date"];

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }
  
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);
  
echo "mysql_client_encoding: " . mysql_client_encoding() . "<br/>";

$build_array = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];

$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);  
  $projectname = $project_array["name"];  
  }

checkUserPolicy(@$_SESSION['cdash']['loginid'],$project_array["id"]);

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
 
$build = pdo_query("SELECT * FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build); 
$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$starttime = $build_array["starttime"];

$date = get_dashboard_date_from_build_starttime($build_array["starttime"],$project_array["nightlytime"]);
$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);
 
$xml .= "<menu>";
$xml .= add_XML_value("back","index.php?project=".urlencode($projectname)."&date=".$date);
$previousbuildid = get_previous_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($previousbuildid>0)
  {
  $xml .= add_XML_value("previous","viewBuildError.php?buildid=".$previousbuildid);
  }
else
  {
  $xml .= add_XML_value("noprevious","1");
  }  
$xml .= add_XML_value("current","viewBuildError.php?buildid=".get_last_buildid($projectid,$siteid,$buildtype,$buildname,$starttime));  
$nextbuildid = get_next_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($nextbuildid>0)
  {
  $xml .= add_XML_value("next","viewBuildError.php?buildid=".$nextbuildid);
  }  
else
  {
  $xml .= add_XML_value("nonext","1");
  }
$xml .= "</menu>";

  // Build
  $xml .= "<build>";
  $site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
  $xml .= add_XML_value("site",$site_array["name"]);
  $xml .= add_XML_value("siteid",$siteid);
  $xml .= add_XML_value("buildname",$build_array["name"]);
  $xml .= add_XML_value("starttime",date(FMT_DATETIMETZ,strtotime($build_array["starttime"]."UTC")));
  $xml .= add_XML_value("buildid",$build_array["id"]);
  $xml .= "</build>";
  
  @$type = $_GET["type"];
  if(!isset($type))
    {
    $type = 0;
    }
  // Set the error
  if($type == 0)
    {
    $xml .= add_XML_value("errortypename","Error");
    $xml .= add_XML_value("nonerrortypename","Warning");
    $xml .= add_XML_value("nonerrortype","1"); 
    }
  else
    {
    $xml .= add_XML_value("errortypename","Warning");
    $xml .= add_XML_value("nonerrortypename","Error");
    $xml .= add_XML_value("nonerrortype","0"); 
    } 
  
  $xml .= "<errors>";
  
  if(isset($_GET["onlydeltan"]))
    {
    // Build error table
    $errors = pdo_query("SELECT *
               FROM (SELECT * FROM builderror WHERE buildid=".$previousbuildid."
               AND type=".$type.") AS builderrora 
               LEFT JOIN (SELECT crc32 as crc32b FROM builderror WHERE buildid=".$buildid." 
               AND type=".$type.") AS builderrorb 
               ON builderrora.crc32=builderrorb.crc32b WHERE builderrorb.crc32b IS NULL");
        
    while($error_array = pdo_fetch_array($errors))
      {
      $xml .= "<error>";
      $xml .= add_XML_value("new","-1");
      $xml .= add_XML_value("logline",$error_array["logline"]);
      $xml .= add_XML_value("text",$error_array["text"]);
      $xml .= add_XML_value("sourcefile",$error_array["sourcefile"]);
      $xml .= add_XML_value("sourceline",$error_array["sourceline"]);
      $xml .= add_XML_value("precontext",$error_array["precontext"]);
      $xml .= add_XML_value("postcontext",$error_array["postcontext"]);
    
      $projectCvsUrl = $project_array["cvsurl"];
      $file = basename($error_array["sourcefile"]);
      $directory = dirname($error_array["sourcefile"]);  
      $cvsurl = get_diff_url($projectid,$projectCvsUrl,$directory,$file);
  
  
      $xml .= add_XML_value("cvsurl",$cvsurl);
      $xml .= "</error>";
      }
      
    // Build failure table
    $errors = pdo_query("SELECT *
               FROM (SELECT * FROM buildfailure WHERE buildid=".$previousbuildid."
               AND type=".$type.") AS builderrora 
               LEFT JOIN (SELECT crc32 as crc32b FROM buildfailure WHERE buildid=".$buildid." 
               AND type=".$type.") AS builderrorb 
               ON builderrora.crc32=builderrorb.crc32b WHERE builderrorb.crc32b IS NULL"); 
    
   while($error_array = pdo_fetch_array($errors))
      {
      $xml .= "<error>";
      $xml .= add_XML_value("language",$error_array["language"]);
      $xml .= add_XML_value("sourcefile",$error_array["sourcefile"]);
      $xml .= add_XML_value("targetname",$error_array["targetname"]);
      $xml .= add_XML_value("outputfile",$error_array["outputfile"]);
      $xml .= add_XML_value("outputtype",$error_array["outputtype"]);
      $xml .= add_XML_value("workingdirectory",$error_array["workingdirectory"]);
      
      $buildfailureid = $error_array["id"];
      $arguments = pdo_query("SELECT bfa.argument FROM buildfailureargument AS bfa,buildfailure2argument AS bf2a
                              WHERE bf2a.buildfailureid='$buildfailureid' AND bf2a.argumentid=bfa.id ORDER BY bf2a.place ASC");
      while($argument_array = pdo_fetch_array($arguments))
        {
        $xml .= add_XML_value("argument",$argument_array["argument"]);
        }
  
      $xml .= get_labels_xml_from_query_results(
        "SELECT text FROM label, label2buildfailure WHERE ".
        "label.id=label2buildfailure.labelid AND ".
        "label2buildfailure.buildfailureid='$buildfailureid' ".
        "ORDER BY text ASC");

      //echo "stderror: [" . $error_array["stderror"] . "]";
      $xml2 = add_XML_value("stderror",$error_array["stderror"]);
      echo "xml2: " . $xml2;
      $xml .= add_XML_value("stderror",$error_array["stderror"]);
      $rows = substr_count($error_array["stderror"],"\n")+1;
      if ($rows > 10)
        {
        $rows = 10;
        }
      $xml .= add_XML_value("stderrorrows",$rows);
  
      $xml .= add_XML_value("stdoutput",$error_array["stdoutput"]);
      $rows = substr_count($error_array["stdoutput"],"\n")+1;
      if ($rows > 10)
        {
        $rows = 10;
        }
      $xml .= add_XML_value("stdoutputrows",$rows);
  
      $xml .= add_XML_value("exitcondition",$error_array["exitcondition"]);
    
      if(isset($error_array["sourcefile"]))
        {
        $projectCvsUrl = $project_array["cvsurl"];
        $file = basename($error_array["sourcefile"]);
        $directory = dirname($error_array["sourcefile"]);  
        $cvsurl = get_diff_url($projectid,$projectCvsUrl,$directory,$file);
        $xml .= add_XML_value("cvsurl",$cvsurl);
        }
      $xml .= "</error>";
      } 
      
    }
  else
    {  
    $extrasql = "";
    if(isset($_GET["onlydeltap"]))
      {
      $extrasql = " AND newstatus='1'";
      }
    
    // Build error table
    $errors = pdo_query("SELECT * FROM builderror WHERE buildid='$buildid' AND type='$type'".$extrasql." ORDER BY logline ASC");
    while($error_array = pdo_fetch_array($errors))
      {
      $xml .= "<error>";
      $xml .= add_XML_value("new",$error_array["newstatus"]);
      $xml .= add_XML_value("logline",$error_array["logline"]);
      $xml .= add_XML_value("text",$error_array["text"]);
      $xml .= add_XML_value("sourcefile",$error_array["sourcefile"]);
      $xml .= add_XML_value("sourceline",$error_array["sourceline"]);
      $xml .= add_XML_value("precontext",$error_array["precontext"]);
      $xml .= add_XML_value("postcontext",$error_array["postcontext"]);
    
      $projectCvsUrl = $project_array["cvsurl"];
      $file = basename($error_array["sourcefile"]);
      $directory = dirname($error_array["sourcefile"]);  
      $cvsurl = get_diff_url($projectid,$projectCvsUrl,$directory,$file);
  
  
      $xml .= add_XML_value("cvsurl",$cvsurl);
      $xml .= "</error>";
      }

    // Build failure table
    $errors = pdo_query("SELECT * FROM buildfailure WHERE buildid='$buildid' and type='$type' ORDER BY id ASC");
    while($error_array = pdo_fetch_array($errors))
      {
      $xml .= "<error>";
      $xml .= add_XML_value("language",$error_array["language"]);
      $xml .= add_XML_value("sourcefile",$error_array["sourcefile"]);
      $xml .= add_XML_value("targetname",$error_array["targetname"]);
      $xml .= add_XML_value("outputfile",$error_array["outputfile"]);
      $xml .= add_XML_value("outputtype",$error_array["outputtype"]);
      $xml .= add_XML_value("workingdirectory",$error_array["workingdirectory"]);
      
      $buildfailureid = $error_array["id"];
      $arguments = pdo_query("SELECT bfa.argument FROM buildfailureargument AS bfa,buildfailure2argument AS bf2a
                              WHERE bf2a.buildfailureid='$buildfailureid' AND bf2a.argumentid=bfa.id ORDER BY bf2a.place ASC");
      while($argument_array = pdo_fetch_array($arguments))
        {
        $xml .= add_XML_value("argument",$argument_array["argument"]);
        }
  
      $xml .= get_labels_xml_from_query_results(
        "SELECT text FROM label, label2buildfailure WHERE ".
        "label.id=label2buildfailure.labelid AND ".
        "label2buildfailure.buildfailureid='$buildfailureid' ".
        "ORDER BY text ASC");
  
      //echo "stderror: [" . $error_array["stderror"] . "]";
      $xml2 = add_XML_value("stderror",$error_array["stderror"]);
      echo "xml2: " . $xml2;
      $xml .= add_XML_value("stderror",$error_array["stderror"]);
      $rows = substr_count($error_array["stderror"],"\n")+1;
      if ($rows > 10)
        {
        $rows = 10;
        }
      $xml .= add_XML_value("stderrorrows",$rows);
  
      $xml .= add_XML_value("stdoutput",$error_array["stdoutput"]);
      $rows = substr_count($error_array["stdoutput"],"\n")+1;
      if ($rows > 10)
        {
        $rows = 10;
        }
      $xml .= add_XML_value("stdoutputrows",$rows);
  
      $xml .= add_XML_value("exitcondition",$error_array["exitcondition"]);
    
      if(isset($error_array["sourcefile"]))
        {
        $projectCvsUrl = $project_array["cvsurl"];
        $file = basename($error_array["sourcefile"]);
        $directory = dirname($error_array["sourcefile"]);  
        $cvsurl = get_diff_url($projectid,$projectCvsUrl,$directory,$file);
        $xml .= add_XML_value("cvsurl",$cvsurl);
        }
      $xml .= "</error>";
      }
    } // end if onlydeltan
  $xml .= "</errors>";
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewBuildError");
?>
