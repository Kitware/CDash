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
include_once("config.php");
require_once("pdo.php");
include('login.php');
include('version.php');
      
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$userid = $_SESSION['cdash']['loginid'];
// Checks
if(!isset($userid) || !is_numeric($userid))
  {
  echo "Not a valid userid!";
  return;
  }
  
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";  

@$projectname = $_GET["project"];
$projectid = get_project_id($projectname);
if($projectid)
  {
  $project_array = pdo_fetch_array(pdo_query("SELECT name,nightlytime FROM project WHERE id='$projectid'"));  
  $xml .= "<backurl>user.php";
  $xml .= "</backurl>";
  }
else  
  {
  $xml .= "<backurl>index.php</backurl>";
  }
$xml .= "<title>CDash - User Statistics</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>User Stats</menusubtitle>";
 
@$projectid = $_GET["projectid"];

// If the projectid is not set and there is only one project we go directly to the page
if(!isset($projectid))
{
  $project = pdo_query("SELECT id FROM project");
  if(pdo_num_rows($project)==1)
    {
    $project_array = pdo_fetch_array($project);
    $projectid = $project_array["id"];
    }
}
  
$role=0;

$user_array = pdo_fetch_array(pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$userid'"));
if($projectid && is_numeric($projectid))
  {
  $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
  if(pdo_num_rows($user2project)>0)
    {
    $user2project_array = pdo_fetch_array($user2project);
    $role = $user2project_array["role"];
    }  
  }
    
if(!(isset($_SESSION['cdash']['user_can_create_project']) && 
   $_SESSION['cdash']['user_can_create_project'] == 1)
   && ($user_array["admin"]!=1 && $role<=1))
  {
  echo "You don't have the permissions to access this page";
  return;
  }
 
checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);   
  
$sql = "SELECT id,name FROM project";
if($user_array["admin"] != 1)
  {
  $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$userid' AND role>0)"; 
  }
$projects = pdo_query($sql);
while($project_array = pdo_fetch_array($projects))
   {
   $xml .= "<availableproject>";
   $xml .= add_XML_value("id",$project_array['id']);
   $xml .= add_XML_value("name",$project_array['name']);
   if($project_array['id']==$projectid)
      {
      $xml .= add_XML_value("selected","1");
      }
   $xml .= "</availableproject>";
   }

if($projectid>0)
  {
  $project = pdo_query("SELECT id,name FROM project WHERE id='$projectid'");
  $project_array = pdo_fetch_array($project);
  $xml .= "<project>";
  $xml .= add_XML_value("id",$project_array['id']);
  $xml .= add_XML_value("name",$project_array['name']);
  $xml .= "</project>";
  
  $range = "thisweek";
  if(isset($_POST["range"]))
  {
    $range = $_POST["range"];
    $xml .= add_XML_value("datarange",$range);
  }
  
  // Find the list of the best submitters for the project
  $now = time();
  
  if($range=="thisweek")
    {
    // find the current day of the week
    $day = date("w");
    $end = $now;
    $beginning = $now-$day*3600*24;
    }
  else if($range=="lastweek")
    {
    // find the current day of the week
    $day = date("w");
    $end = $now-$day*3600*24;
    $beginning = $end-7*3600*24;
    }
  else if($range=="thismonth")
    {
    // find the current day of the month
    $day = date("j");
    $end = $now;
    $beginning = $now-$day*3600*24;
    }
  else if($range=="lastmonth")
    {
    // find the current day of the month
    $day = date("j");
    $end = $now-$day*3600*24;
    $beginning = $end-30*3600*24; // assume 30 days months
    }
  else if($range=="thisyear")
    {
    // find the current day of the month
    $day = date("z");
    $end = $now-$day*3600*24;
    $beginning = $now;
    }
  
  $beginning_UTCDate = gmdate(FMT_DATETIME,$beginning);
  $end_UTCDate = gmdate(FMT_DATETIME,$end);                                                      
  
  $endselect = "select f.userid, f.checkindate, f.totalbuilds, f.nfixedwarnings, 
                       f.nfailedwarnings, f.nfixederrors, f.nfailederrors, 
                       f.nfixedtests, f.nfailedtests, f.totalupdatedfiles
  from (
     select userid, max(checkindate) as checkindate
     from userstatistics WHERE checkindate<'$end_UTCDate' AND checkindate>='$beginning_UTCDate' AND projectid='$projectid' group by userid
  ) as x inner join userstatistics as f on f.userid=x.userid AND f.checkindate=x.checkindate";
  
  $startselect = "select f.userid, f.checkindate, f.totalbuilds, f.nfixedwarnings,
                         f.nfailedwarnings, f.nfixederrors, f.nfailederrors, 
                         f.nfixedtests, f.nfailedtests, f.totalupdatedfiles
  from (
     select userid, max(checkindate) as checkindate
     from userstatistics WHERE checkindate<'$beginning_UTCDate' AND projectid='$projectid' group by userid
  ) as x inner join userstatistics as f on f.userid=x.userid AND f.checkindate=x.checkindate";
    
  // First loop through the endselect
  $users = array();
  $endquery = pdo_query($endselect);
  while($endquery_array = pdo_fetch_array($endquery))
    {
    $user = array();
    $user['nfailedwarnings'] = $endquery_array['nfailedwarnings'];
    $user['nfixedwarnings'] = $endquery_array['nfixedwarnings'];
    $user['nfailederrors'] = $endquery_array['nfailederrors'];
    $user['nfixederrors'] = $endquery_array['nfixederrors'];
    $user['nfailedtests'] = $endquery_array['nfailedtests'];
    $user['nfixedtests'] = $endquery_array['nfixedtests'];
    $user['totalbuilds'] = $endquery_array['totalbuilds'];
    $user['totalupdatedfiles'] = $endquery_array['totalupdatedfiles'];
    $users[$endquery_array['userid']] = $user;
    }
  
  $startquery = pdo_query($startselect);
  while($startquery_array = pdo_fetch_array($startquery))
    {
    if(isset($users[$startquery_array['userid']]))
      {
      $users[$startquery_array['userid']]['nfailedwarnings'] -= $startquery_array['nfailedwarnings'];
      $users[$startquery_array['userid']]['nfixedwarnings'] -= $startquery_array['nfixedwarnings'];
      $users[$startquery_array['userid']]['nfailederrors'] -= $startquery_array['nfailederrors'];
      $users[$startquery_array['userid']]['nfixederrors'] -= $startquery_array['nfixederrors'];
      $users[$startquery_array['userid']]['nfailedtests'] -= $startquery_array['nfailedtests'];
      $users[$startquery_array['userid']]['nfixedtests'] -= $startquery_array['nfixedtests']; 
      $users[$startquery_array['userid']]['totalbuilds'] -= $startquery_array['totalbuilds'];
      $users[$startquery_array['userid']]['totalupdatedfiles'] -= $startquery_array['totalupdatedfiles'];  
      }
    }
  
  // Compute the total score
  $alpha_warning = 0.2;
  $alpha_error = 0.5;
  $alpha_test = 0.3;
  $fixingvsfailing = 0.2;
  
  foreach($users as $key=>$user)
    {  
    $xml .= "<user>";
    $user_array = pdo_fetch_array(pdo_query("SELECT firstname,lastname FROM ".qid("user")." WHERE id=".qnum($key)));
  
    $xml .= add_XML_value("name",$user_array['firstname']." ".$user_array['lastname']);
    $xml .= add_XML_value("id",$key);
    $score=$alpha_test*$fixingvsfailing*$user['nfixedtests'];
    $score-=$alpha_test*(1-$fixingvsfailing)*$user['nfailedtests'];
    $score+=$alpha_error*$fixingvsfailing*$user['nfixederrors'];
    $score-=$alpha_error*(1-$fixingvsfailing)*$user['nfailederrors'];
    $score+=$alpha_warning*$fixingvsfailing*$user['nfixedwarnings'];
    $score-=$alpha_warning*(1-$fixingvsfailing)*$user['nfailedwarnings'];
    $score /= $user['totalbuilds'];
    $xml .= add_XML_value("score",round($score,3));
    $xml .= add_XML_value("failed_errors",round($user['nfailederrors']/$user['totalbuilds']));
    $xml .= add_XML_value("fixed_errors",round($user['nfixederrors']/$user['totalbuilds']));  
    $xml .= add_XML_value("failed_warnings",round($user['nfailedwarnings']/$user['totalbuilds']));
    $xml .= add_XML_value("fixed_warnings",round($user['nfixedwarnings']/$user['totalbuilds']));
    $xml .= add_XML_value("failed_tests",round($user['nfailedtests']/$user['totalbuilds']));
    $xml .= add_XML_value("fixed_tests",round($user['nfixedtests']/$user['totalbuilds']));
    $xml .= add_XML_value("fixed_tests",round($user['nfixedtests']/$user['totalbuilds']));
    $xml .= add_XML_value("totalupdatedfiles",round($user['totalupdatedfiles']/$user['totalbuilds']));
    $xml .= "</user>";
    }
  } // end if project found

$xml .= "</cdash>";
  
// Now doing the xslt transition
generate_XSLT($xml,"userStatistics");

?>
