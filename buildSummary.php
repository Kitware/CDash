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
include("config.php");
require_once("pdo.php");
include('login.php');
include_once("common.php");
include("version.php");
    
@$buildid = $_GET["buildid"];
// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }

@$date = $_GET["date"];
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$build_array = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];
$date = date("Y-m-d", strtotime($build_array["starttime"]));

checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);
  
// Format the text to fit the iPhone
function format_for_iphone($text)
{
  global $FormatTextForIphone;
  if(!isset($FormatTextForIphone))
    {
    return $text;
    }
  $text = str_replace("\n","<br/>",$text);
  return $text;
}  
  
$xml = '<?xml version="1.0"?><cdash>';
$projectname = get_project_name($projectid);
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= get_cdash_dashboard_xml($projectname,$date);

// User
 if(isset($_SESSION['cdash']))
   {
   $xml .= "<user>";
   $userid = $_SESSION['cdash']['loginid'];
   $user = pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$userid'");
   $user_array = pdo_fetch_array($user);
   $xml .= add_XML_value("id",$userid);
   $xml .= add_XML_value("admin",$user_array["admin"]);
   $xml .= "</user>";
   }
  
  // Notes
  $note = pdo_query("SELECT * FROM buildnote WHERE buildid='$buildid' ORDER BY timestamp ASC");
  while($note_array = pdo_fetch_array($note))
    {
    $xml .= "<note>";
    $userid = $note_array["userid"];
    $user_array = pdo_fetch_array(pdo_query("SELECT firstname,lastname FROM user WHERE id='$userid'"));
    $timestamp = strtotime($note_array["timestamp"]." UTC");
    $usernote = $user_array["firstname"]." ".$user_array["lastname"];
    switch($note_array["status"])
      {
      case 0: $status = "[note]"; break;
      case 1: $status = "[fix in progress]"; break;
      case 2: $status = "[fixed]"; break;
      }
    $xml .= add_XML_value("status",$status);
    $xml .= add_XML_value("user",$usernote);
    $xml .= add_XML_value("date",date("H:i:s T",$timestamp));
    $xml .= add_XML_value("text",$note_array["note"]);
    $xml .= "</note>";
    }
  
  // Build
  $xml .= "<build>";
  $build = pdo_query("SELECT * FROM build WHERE id='$buildid'");
  $build_array = pdo_fetch_array($build); 
  $siteid = $build_array["siteid"];
  $site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
  $xml .= add_XML_value("site",$site_array["name"]);
  $xml .= add_XML_value("name",$build_array["name"]);
  $xml .= add_XML_value("id",$build_array["id"]);
  $xml .= add_XML_value("time",date("Y-m-d H:i:s T",strtotime($build_array["starttime"]." UTC")));  
  $xml .= add_XML_value("type",$build_array["type"]);
  
  // Find the last submit date
  $buildtype = $build_array["type"];
  $buildname = $build_array["name"];
  $starttime = $build_array["starttime"];
  $previousbuild = pdo_query("SELECT id,starttime FROM build
                                WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
                                AND projectid='$projectid' AND starttime<'$starttime' ORDER BY starttime DESC LIMIT 1");

  if(pdo_num_rows($previousbuild)>0)
    {
    $previousbuild_array = pdo_fetch_array($previousbuild);              
    $lastsubmitbuild = $previousbuild_array["id"];
    $lastsubmitdate = date("Y-m-d H:i:s T",strtotime($previousbuild_array["starttime"]." UTC"));
    }
  else
    {
    $lastsubmitbuild = 0;
    $lastsubmitdate = 0;
    }
  $xml .= add_XML_value("generator",$build_array["generator"]);
  $xml .= add_XML_value("command",$build_array["command"]);
  $xml .= add_XML_value("starttime",date("Y-m-d H:i:s T",strtotime($build_array["starttime"]." UTC"))); 
  $xml .= add_XML_value("endtime",date("Y-m-d H:i:s T",strtotime($build_array["endtime"]." UTC"))); 
  
  $xml .= add_XML_value("lastsubmitdate",$lastsubmitdate);
 
  // Number of errors and warnings
  $builderror = pdo_query("SELECT count(buildid) FROM builderror WHERE buildid='$buildid' AND type='0'");
  $builderror_array = pdo_fetch_array($builderror);
  $nerrors = $builderror_array[0];
  $xml .= add_XML_value("error",$nerrors);
  $buildwarning = pdo_query("SELECT count(buildid) FROM builderror WHERE buildid='$buildid' AND type='1'");
  $buildwarning_array = pdo_fetch_array($buildwarning);
  $nwarnings = $buildwarning_array[0];
   
  $xml .= add_XML_value("nerrors",$nerrors);
  $xml .= add_XML_value("nwarnings",$nwarnings);
  
  
  // Display the errors
  $errors = pdo_query("SELECT * FROM builderror WHERE buildid='$buildid' and type='0'");
  while($error_array = pdo_fetch_array($errors))
    {
    $xml .= "<error>";
    $xml .= add_XML_value("logline",$error_array["logline"]);
    $xml .= add_XML_value("text",format_for_iphone($error_array["text"]));
    $xml .= add_XML_value("sourcefile",$error_array["sourcefile"]);
    $xml .= add_XML_value("sourceline",$error_array["sourceline"]);
    $xml .= add_XML_value("precontext",format_for_iphone($error_array["precontext"]));
    $xml .= add_XML_value("postcontext",format_for_iphone($error_array["postcontext"]));
    $xml .= "</error>";
    }
  
  // Display the warnings
  $errors = pdo_query("SELECT * FROM builderror WHERE buildid='$buildid' and type='1'");
  while($error_array = pdo_fetch_array($errors))
    {
    $xml .= "<warning>";
    $xml .= add_XML_value("logline",$error_array["logline"]);
    $xml .= add_XML_value("text",format_for_iphone($error_array["text"]));
    $xml .= add_XML_value("sourcefile",$error_array["sourcefile"]);
    $xml .= add_XML_value("sourceline",$error_array["sourceline"]);
    $xml .= add_XML_value("precontext",format_for_iphone($error_array["precontext"]));
    $xml .= add_XML_value("postcontext",format_for_iphone($error_array["postcontext"]));
    $xml .= "</warning>";
    }
  
  $xml .= "</build>";

  // Update
  $buildupdate = pdo_query("SELECT * FROM buildupdate WHERE buildid='$buildid'");
  
  if(pdo_num_rows($buildupdate)>0) // show the update only if we have one
    {
    $xml .= "<update>";
  
    // Checking for locally modify files
    $updatelocal = pdo_query("SELECT buildid FROM updatefile WHERE buildid='$buildid' AND author='Local User'");      
    $nerrors = pdo_num_rows($updatelocal);
    $nwarnings = 0;
    $xml .= add_XML_value("nerrors",$nerrors);
    $xml .= add_XML_value("nwarnings",$nwarnings);
    
    $update = pdo_query("SELECT buildid FROM updatefile WHERE buildid='$buildid'");
    $nupdates = pdo_num_rows($update);
    $xml .= add_XML_value("nupdates",$nupdates);  
       
    
    $update_array = pdo_fetch_array($buildupdate);
    $xml .= add_XML_value("command",$update_array["command"]);
    $xml .= add_XML_value("type",$update_array["type"]);
    $xml .= add_XML_value("starttime",date("Y-m-d H:i:s T",strtotime($update_array["starttime"]." UTC")));
    $xml .= add_XML_value("endtime",date("Y-m-d H:i:s T",strtotime($update_array["endtime"]." UTC")));
    $xml .= "</update>";
    }
  
  
  // Configure
  $xml .= "<configure>";
  $configure = pdo_query("SELECT * FROM configure WHERE buildid='$buildid'");
  $configure_array = pdo_fetch_array($configure);
  
  $nerrors = 0;
  
  if($configure_array["status"]!=0)
    {
    $nerrors = 1;
    }
  
  $nwarnings = 0;
  $xml .= add_XML_value("nerrors",$nerrors);
  $xml .= add_XML_value("nwarnings",$nwarnings);
  

  $xml .= add_XML_value("status",$configure_array["status"]);
  $xml .= add_XML_value("command",$configure_array["command"]);
  $xml .= add_XML_value("output",format_for_iphone($configure_array["log"]));
  $xml .= add_XML_value("starttime",date("Y-m-d H:i:s T",strtotime($configure_array["starttime"]." UTC")));
  $xml .= add_XML_value("endtime",date("Y-m-d H:i:s T",strtotime($configure_array["endtime"]." UTC")));
  $xml .= "</configure>";

  // Test
  $xml .= "<test>";
  $nerrors = 0;
  $nwarnings = 0;
  $xml .= add_XML_value("nerrors",$nerrors);
  $xml .= add_XML_value("nwarnings",$nwarnings);
  
  $npass_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='passed'"));
  $npass = $npass_array[0];
  $nnotrun_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='notrun'"));
  $nnotrun = $nnotrun_array[0];
  $nfail_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='failed'"));
  $nfail = $nfail_array[0];
  
  $xml .= add_XML_value("npassed",$npass);
  $xml .= add_XML_value("nnotrun",$nnotrun);  
  $xml .= add_XML_value("nfailed",$nfail); 
  
  $xml .= "</test>";
  
  // Previous build
  // Find the previous build
  if($lastsubmitbuild > 0)
    {
    $xml .= "<previousbuild>";
    $previousbuildid = $lastsubmitbuild;
    $xml .= add_XML_value("buildid",$previousbuildid);
    
    // Find if the build has any errors
    $builderror = pdo_query("SELECT count(buildid) FROM builderror WHERE buildid='$previousbuildid' AND type='0'");
    $builderror_array = pdo_fetch_array($builderror);
    $npreviousbuilderrors = $builderror_array[0];
       
    // Find if the build has any warnings
    $buildwarning = pdo_query("SELECT count(buildid) FROM builderror WHERE buildid='$previousbuildid' AND type='1'");
    $buildwarning_array = pdo_fetch_array($buildwarning);
    $npreviousbuildwarnings = $buildwarning_array[0];
  
    // Find if the build has any test failings
    $nfail_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$previousbuildid' AND status='failed'"));
    $npreviousfailingtests = $nfail_array[0];
    $nfail_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$previousbuildid' AND status='notrun'"));
    $npreviousnotruntests = $nfail_array[0];
  
    $updatelocal = pdo_query("SELECT buildid FROM updatefile WHERE buildid='$previousbuildid' AND author='Local User'");      
    $nupdateerrors = pdo_num_rows($updatelocal);
    $nupdatewarnings = 0;
    $xml .= add_XML_value("nupdateerrors",$nupdateerrors);
    $xml .= add_XML_value("nupdatewarnings",$nupdatewarnings);

    $configure = pdo_query("SELECT * FROM configure WHERE buildid='$previousbuildid'");
    $configure_array = pdo_fetch_array($configure);
  
    $nconfigureerrors = 0;
    if($configure_array["status"]!=0)
      {
      $nconfigureerrors = 1;
      }
    $nconfigurewarnings = 0;
    $xml .= add_XML_value("nconfigureerrors",$nconfigureerrors);
    $xml .= add_XML_value("nconfigurewarnings",$nconfigurewarnings);

    $xml .= add_XML_value("nerrors",$npreviousbuilderrors);
    $xml .= add_XML_value("nwarnings",$npreviousbuildwarnings);

    $xml .= add_XML_value("ntestfailed",$npreviousfailingtests);
    $xml .= add_XML_value("ntestnotrun",$npreviousnotruntests);
     
    $xml .= "</previousbuild>";
    }
  $xml .= "</cdash>";

// Now doing the xslt transition
if(!isset($NoXSLGenerate))
  {
  generate_XSLT($xml,"buildSummary");
  }
?>
