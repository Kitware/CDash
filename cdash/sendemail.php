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

/** */
function checkEmailPreferences($emailcategory,$errors)
{
  include_once("cdash/common.php");
  if($errors['update_errors']>0 && check_email_category("update",$emailcategory))
    {
    return true;
    }
  if($errors['configure_errors']>0 && check_email_category("configure",$emailcategory))
    {
    return true;
    }
  if($errors['build_warnings']>0 && check_email_category("warning",$emailcategory))
    {
    return true;
    }
  if($errors['build_errors']>0 && check_email_category("error",$emailcategory))
    {
    return true;
    }
  if($errors['test_errors']>0 && check_email_category("test",$emailcategory))
    {
    return true;
    }
  return false;  
}

/** Given a user check if we should send an email based on labels */
function checkEmailLabel($projectid, $userid, $buildid)
{
  include_once("models/labelemail.php");
  include_once("models/build.php");
  $LabelEmail = new LabelEmail();
  $LabelEmail->UserId = $userid;
  $LabelEmail->ProjectId = $projectid;
  
  $labels = $LabelEmail->GetLabels();
  if(count($labels)==0) // if the number of subscribed labels is zero we send the email
    {
    return true;
    }
  
  $Build = new Build();
  $Build->Id = $buildid;
  $buildlabels = $Build->GetLabels();
  if(count(array_intersect($labels, $buildlabels))>0)
    {
    return true;
    }
  return false;
} // end checkEmailLabel


/** Send a summary email */
function sendsummaryemail($projectid,$projectname,$dashboarddate,$groupid,$errors,$buildid)
{
  include("config.php");
 
  // Check if the email has been sent
  $date = ""; // now
  list ($previousdate, $currentstarttime, $nextdate, $today) = get_dates($date,$project_array["nightlytime"]);
  $dashboarddate = gmdate(FMT_DATE, $currentstarttime);

  // If we already have it we return
  if(pdo_num_rows(pdo_query("SELECT buildid FROM summaryemail WHERE date='$dashboarddate' AND groupid=".qnum($groupid)))==1)
    {
    return;
    }  

  // Update the summaryemail table to specify that we have send the email
  // We also delete any previous rows from that groupid
  pdo_query("DELETE FROM summaryemail WHERE groupid=$groupid");
  pdo_query("INSERT INTO summaryemail (buildid,date,groupid) VALUES ($buildid,'$dashboarddate',$groupid)");
  add_last_sql_error("sendmail");
    
  // If the trigger for SVN/CVS diff is not done yet we specify that the asynchronous trigger should
  // send an email
  $dailyupdatequery = pdo_query("SELECT status FROM dailyupdate WHERE projectid=".qnum($projectid)." AND date='$dashboarddate'");
  add_last_sql_error("sendmail");
    
  if(pdo_num_rows($dailyupdatequery) == 0)
    {
    return;
    }
      
  $dailyupdate_array = pdo_fetch_array($dailyupdatequery);
  $dailyupdate_status = $dailyupdate_array['status'];
  if($dailyupdate_status == 0)
    {
    pdo_query("UPDATE dailyupdate SET status='2' WHERE projectid='$projectid' AND date='$dashboarddate'");
    return;
    }
       
  // Find the current updaters from the night using the dailyupdatefile table
  $summaryEmail = "";
  $query = "SELECT ".qid("user").".email,user2project.emailcategory,".qid("user").".id FROM ".qid("user").",user2project,dailyupdate,dailyupdatefile WHERE 
                           user2project.projectid=$projectid
                           AND user2project.userid=".qid("user").".id 
                           AND user2project.cvslogin=dailyupdatefile.author
                           AND dailyupdatefile.dailyupdateid=dailyupdate.id
                           AND dailyupdate.date='$dashboarddate'
                           AND dailyupdate.projectid=$projectid
                           AND user2project.emailtype>0
                           ";
  $user = pdo_query($query);
  add_last_sql_error("sendmail");
      
  // Loop through the users and add them to the email array  
  while($user_array = pdo_fetch_array($user))
    {
    // If the user is already in the list we quit
    if(strpos($summaryEmail,$user_array["email"]) !== false)
      {
      continue;
      }
        
    // If the user doesn't want to receive email
    if(!checkEmailPreferences($user_array["emailcategory"],$errors))
      {
      continue;
      }
    
    // Check if the labels are defined for this user
    if(!checkEmailLabel($projectid, $user_array["id"], $buildid))
      {
      continue;
      }
      
    if($summaryEmail != "")
      {
      $summaryEmail .= ", ";
      }
    $summaryEmail .= $user_array["email"];
    }
    
  // Select the users who want to receive all emails
  $user = pdo_query("SELECT ".qid("user").".email,user2project.emailtype,".qid("user").".id  FROM ".qid("user").",user2project WHERE user2project.projectid='$projectid' 
                       AND user2project.userid=".qid("user").".id AND user2project.emailtype>1");
  add_last_sql_error("sendsummaryemail");
  while($user_array = pdo_fetch_array($user))
    {
    // If the user is already in the list we quit
    if(strpos($summaryEmail,$user_array["email"]) !== false)
       {
       continue;
       }
    
    // Check if the labels are defined for this user
    if(!checkEmailLabel($projectid, $user_array["id"], $buildid))
      {
      continue;
      }   
       
    if($summaryEmail != "")
      {
      $summaryEmail .= ", ";
      }
     $summaryEmail .= $user_array["email"];
    }
       
  // Send the email
  if($summaryEmail != "")
    {
    $summaryemail_array = pdo_fetch_array(pdo_query("SELECT name FROM buildgroup WHERE id=$groupid"));
    add_last_sql_error("sendsummaryemail");

    $title = "CDash [".$projectname."] - ".$summaryemail_array["name"]." Failures";
      
    $messagePlainText = "The \"".$summaryemail_array["name"]."\" group has either errors, warnings or test failures.\n";
    $messagePlainText .= "You have been identified as one of the authors who have checked in changes that are part of this submission ";
    $messagePlainText .= "or you are listed in the default contact list.\n\n";  
     
    $currentURI = get_server_URI();
    
    $messagePlainText .= "To see this dashboard:\n";  
    $messagePlainText .= $currentURI;
    $messagePlainText .= "/index.php?project=".$projectname."&date=".$dashboarddate;
    $messagePlainText .= "\n\n";
    
    $serverName = $CDASH_SERVER_NAME;
    if(strlen($serverName) == 0)
      {
      $serverName = $_SERVER['SERVER_NAME'];
      }
    
    $messagePlainText .= "\n-CDash on ".$serverName."\n";
      
    // Send the email
    if(mail("$summaryEmail", $title, $messagePlainText,
         "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" ))
      {
      add_log("email sent to: ".$email,"sendemail ".$projectname,LOG_INFO);
      return;
      }
    else
      {
      add_log("cannot send email to: ".$email,"sendemail ".$projectname,LOG_ERR);
      }
    } // end $summaryEmail!=""
}

/** Check for errors for a given build. Return false if no errors */
function check_email_errors($buildid,$checktesttimeingchanged,$testtimemaxstatus,$checkpreviousbuild)
{
  // Includes
  require_once("models/buildupdate.php");  
  require_once("models/buildconfigure.php");
  require_once("models/build.php");
  require_once("models/buildtest.php");
  
  $errors = array();  

  // Update errors
  $BuildUpdate = new BuildUpdate ();
  $BuildUpdate ->BuildId = $buildid;
  $errors['update_errors'] = $BuildUpdate ->GetNumberOfErrors();

  // Configure errors    
  $BuildConfigure = new BuildConfigure();
  $BuildConfigure->BuildId = $buildid;
  $errors['configure_errors'] = $BuildConfigure->GetNumberOfErrors();
  
  // Build errors and warnings
  $Build = new Build();
  $Build->BuildId = $buildid;
  $errors['build_errors'] = $Build->GetNumberOfErrors();
  $errors['build_warnings'] = $Build->GetNumberOfWarnings();

  // Test errors
  $BuildTest = new BuildTest();
  $BuildTest->BuildId = $buildid;
  $errors['test_errors'] = $BuildTest->GetNumberOfFailures($checktesttimeingchanged,$testtimemaxstatus);
    
  // Green build we return
  if(   $errors['update_errors'] == 0 
     && $errors['configure_errors'] == 0
     && $errors['build_errors'] == 0
     && $errors['build_warnings'] ==0 
     && $errors['test_errors'] ==0) 
    {
    return false;
    }
  
  // look for the previous build only if necessary
  if($checkpreviousbuild)
    {
    $Build->FillFromId($buildid);
    $previousbuildid = $Build->GetPreviousBuildId();
    
    if($previousbuildid > 0)
      {
      // Configure errors    
      $PreviousBuildConfigure = new BuildConfigure();
      $PreviousBuildConfigure->BuildId = $previousbuildid;
      $npreviousconfigures = $PreviousBuildConfigure->GetNumberOfErrors();
      
      // Build errors and warnings
      $PreviousBuild = new Build();
      $PreviousBuild->BuildId = $previousbuildid;
      $npreviousbuilderrors = $PreviousBuild->GetNumberOfErrors();
      $npreviousbuildwarnings = $PreviousBuild->GetNumberOfWarnings();
    
      // Test errors
      $PreviousBuildTest = new BuildTest();
      $PreviousBuildTest->BuildId = $previousbuildid;
      $npreviousfailingtests = $PreviousBuildTest->GetNumberOfFailures($checktesttimeingchanged,$testtimemaxstatus);

      // If we have exactly the same number of (or less) test failing, errors and warnings has the previous build
      // we don't send any emails
      if($npreviousconfigures>=$errors['configure_errors']
         && $npreviousbuilderrors==$errors['build_errors']
         && $npreviousbuildwarnings>=$errors['build_warnings']
         && $npreviousfailingtests>=$errors['test_errors']
        ) 
        {
        return false;
        }
      }
    } // end emailredundantfailures

  return $errors;
}

/** Return the list of user id who should get emails */
function lookup_emails_to_send($errors,$buildid,$projectid,$buildtype)
{
  require_once("models/userproject.php");
    
  $userids = array();
  
  // Check if we know to whom we should send the email
  $authors = pdo_query("SELECT author FROM updatefile WHERE buildid=".qnum($buildid));
  add_last_sql_error("sendmail");
  while($authors_array = pdo_fetch_array($authors))
    {
    $author = $authors_array["author"];
    if($author=="Local User")
      {
      continue;
      }
    
    $UserProject = new UserProject();
    $UserProject->CvsLogin = $author;
    $UserProject->ProjectId = $projectid;
    
    if(!$UserProject->FillFromCVSLogin())
      {
      // Should send an email to the project admin to let him know that this user is not registered
      add_log("User: ".$author." is not registered (or has no email) for the project ".$projectid,"SendEmail",LOG_WARNING);
      continue;
      }
       
    // If the user doesn't want to receive email
    if(!checkEmailPreferences($UserProject->EmailCategory,$errors))
      {
      continue;
      }
    
    // Check if the labels are defined for this user
    if(!checkEmailLabel($projectid,$UserProject->UserId, $buildid))
      {
      continue;
      }
    
    if(!in_array($UserProject->UserId,$userids))
      {
      $userids[] = $UserProject->UserId;
      }
    } 

  // Select the users who want to receive all emails
  $user = pdo_query("SELECT emailtype,emailcategory,userid FROM user2project WHERE user2project.projectid=".qnum($projectid)." AND user2project.emailtype>1");
  add_last_sql_error("sendmail");
  while($user_array = pdo_fetch_array($user))
    {
    if(in_array($user_array["userid"],$userids))
      {
      continue;
      }
      
    // If the user doesn't want to receive email
    if(!checkEmailPreferences($user_array["emailcategory"],$errors))
      {
      continue;
      }
  
    // Check if the labels are defined for this user
    if(!checkEmailLabel($projectid,$user_array['userid'],$buildid))
      {
      continue;
      }
        
    // Nightly build notification
    if($user_array["emailtype"] == 2 && $buildtype=="Nightly")
      {
      $userids[] = $user_array["userid"];
      }
    else if($user_array["emailtype"] == 3) // want to receive all emails
      {
      $userids[] = $user_array["userid"];
      }
    }
  
  return $userids;
    
} // end lookup_emails_to_send

/** Return a summary for a category of error */
function get_email_summary($buildid,$errors,$errorkey,$maxitems,$maxchars,$testtimemaxstatus,$emailtesttimingchanged)
{
  $serverURI = get_server_URI();
  
  $information = "";
  
  // Update information
  if($errorkey == 'update_errors')
    {
    $information = "\n\n*Update*\n";
 
    $update = pdo_query("SELECT command,status FROM buildupdate WHERE buildid=".qnum($buildid));
    $update_array = pdo_fetch_array($update);
  
    $information .= "Status: ".$update_array["status"]." (".$currentURI."/viewUpdate.phpbuildid=".$buildid.")\n";
    $information .= "Command: ";
    $information .= substr($update_array["command"],0,$maxchars);
    $information .= "\n";
    } // endconfigure   
  else if($errorkey == 'configure_errors') // Configure information
    {
    $information = "\n\n*Configure*\n";
 
    $configure = pdo_query("SELECT status,log FROM configure WHERE buildid=".qnum($buildid));
    $configure_array = pdo_fetch_array($configure);
  
    $information .= "Status: ".$configure_array["status"]." (".$currentURI."/viewConfigure.phpbuildid=".$buildid.")\n";
    $information .= "Output: ";
    $information .= substr($configure_array["log"],0,$maxchars);
    $information .= "\n";
    } // endconfigure
  else if($errorkey == 'build_errors')
    {
    $information .= "\n\n*Error*";
    
    // Old error format
    $error_query = pdo_query("SELECT sourcefile,text,sourceline,postcontext FROM builderror WHERE buildid=".qnum($buildid)." AND type=0 LIMIT $maxitems");
    add_last_sql_error("sendmail");
    
    if(pdo_num_rows($error_query) == $maxitems)
      {
      $information .= " (first ".$maxitems.")";
      }
    $information .= "\n";
    
    while($error_array = pdo_fetch_array($error_query))
      {
      $info = "";
      if(strlen($error_array["sourcefile"])>0)
        {
        $info .= $error_array["sourcefile"]." line ".sourceline." (".$serverURI."/viewBuildError.php?type=0&buildid=".$buildid.")\n";
        $info .= $error_array["text"]."\n";
        }
      else
        {
        $info .= $error_array["text"]."\n".$error_array["postcontext"]."\n";
        }
      $information .= substr($info,0,$maxchars);
      }
      
    // New error format
    $error_query = pdo_query("SELECT sourcefile,stdoutput,stderror FROM buildfailure WHERE buildid=".qnum($buildid)." AND type=0 LIMIT $maxitems");
    add_last_sql_error("sendmail");
    while($error_array = pdo_fetch_array($error_query))
      {
      $info = "";
      if(strlen($error_array["sourcefile"])>0)
        {
        $info .= $error_array["sourcefile"]." (".$serverURI."/viewBuildError.php?type=0&buildid=".$buildid.")\n";
        }
      if(strlen($error_array["stdoutput"])>0)
        {  
        $info .= $error_array["stdoutput"]."\n";
        }
      if(strlen($error_array["stderror"])>0)
        {  
        $info .= $error_array["stderror"]."\n";
        }
      $information .= substr($info,0,$maxchars);
      }
    $information .= "\n";
    }
  else if($errorkey == 'build_warnings')
    {
    $information .= "\n\n*Warnings*";
    
    $error_query = pdo_query("SELECT sourcefile,text,sourceline,postcontext FROM builderror WHERE buildid=".qnum($buildid)." AND type=1 LIMIT $maxitems");
    add_last_sql_error("sendmail");
    
    if(pdo_num_rows($error_query) == $maxitems)
      {
      $information .= " (first ".$maxitems.")";
      }
    $information .= "\n";
    
    while($error_array = pdo_fetch_array($error_query))
      {    
      $info = "";
      if(strlen($error_array["sourcefile"])>0)
        {
        $info .= $error_array["sourcefile"]." line ".$error_array["sourceline"]." (".$serverURI."/viewBuildError.php?type=1&buildid=".$buildid.")\n";
        $info .= $error_array["text"]."\n";
        }
      else
        {
        $info .= $error_array["text"]."\n".$error_array["postcontext"]."\n";
        }
      $information .= substr($info,0,$maxchars);
      }
    
    // New error format
    $error_query = pdo_query("SELECT sourcefile,stdoutput,stderror FROM buildfailure WHERE buildid=".qnum($buildid)." AND type=1 LIMIT $maxitems");
    add_last_sql_error("sendmail");
    while($error_array = pdo_fetch_array($error_query))
      {
      $info = "";
      if(strlen($error_array["sourcefile"])>0)
        {
        $info .= $error_array["sourcefile"]." (".$serverURI."/viewBuildError.php?type=1&buildid=".$buildid.")\n";
        }
      if(strlen($error_array["stdoutput"])>0)
        {  
        $info .= $error_array["stdoutput"]."\n";
        }
      if(strlen($error_array["stderror"])>0)
        {  
        $info .= $error_array["stderror"]."\n";
        }
      $information .= substr($info,0,$maxchars);
      }
    $information .= "\n";  
    }
 else if($errorkey == 'test_errors')
    {   
    $information .= "\n\n*Tests failing*";
    $sql = "";
    if($emailtesttimingchanged)
      {
      $sql = "OR timestatus>".qnum($testtimemaxstatus);
      }
    $test_query = pdo_query("SELECT test.name,test.id FROM build2test,test WHERE build2test.buildid=".qnum($buildid).
                            " AND test.id=build2test.testid AND (build2test.status='failed'".$sql.") LIMIT $maxitems");
    add_last_sql_error("sendmail");
    
    if(pdo_num_rows($test_query) == $maxitems)
      {
      $information .= " (first ".$maxitems.")";
      }
    $information .= "\n";
    
    while($test_array = pdo_fetch_array($test_query))
      {
      $info = $test_array["name"]." (".$serverURI."/testDetails.php?test=".$test_array["id"]."&build=".$buildid.")\n";
      $information .= substr($info,0,$maxchars);
      }
    $information .= "\n";
    }

  return $information;
} // end get_email_summary

/** Check if the email has already been sent for that category */
function set_email_sent($userid,$buildid,$emailtext)
{
  foreach($emailtext['category'] as $key=>$value)
    {
    $category = 0;
    switch($key)
      {
      case 'update_errors': $category=1; break;
      case 'configure_errors': $category=2; break;
      case 'build_warnings': $category=3; break;
      case 'build_errors': $category=4; break;
      case 'test_errors': $category=5; break;
      }
        
   if($category>0)
     {
     $today = date(FMT_DATETIME);
     pdo_query("INSERT INTO buildemail (userid,buildid,category,time) VALUES (".qnum($userid).",".qnum($buildid).",".qnum($category).",'".$today."')");
     add_last_sql_error("sendmail");
     }
   }
}

/** Check if the email has already been sent for that category */
function check_email_sent($userid,$buildid,$errorkey)
{
  $category = 0;
  switch($errorkey)
    {
    case 'update_errors': $category=1; break;
    case 'configure_errors': $category=2; break;
    case 'build_warnings': $category=3; break;
    case 'build_errors': $category=4; break;
    case 'test_errors': $category=5; break;
    }
  
  if($category == 0)
    {
    return false;
    }
  
  $query = pdo_query("SELECT count(*) FROM buildemail WHERE userid=".qnum($userid)." AND buildid=".qnum($buildid)." AND category=".qnum($category));
  $query_array = pdo_fetch_array($query);
  if($query_array[0]>0)
    {
    return true;
    }
      
  return false;
}

/** Send the email to a user */
function send_email_to_user($userid,$emailtext,$Build,$Project)
{
  include("cdash/config.php");
  include_once("cdash/common.php");  
  require_once("models/site.php");
  require_once("models/user.php");

  //print_r($emailtext);
  $serverURI = get_server_URI();
  
  $messagePlainText = "A submission to CDash for the project ".$Project->Name." has ";
  $titleerrors = "(";
    
  $i=0;
  foreach($emailtext['category'] as $key=>$value)
    {
    if($i>0)
       {
       $messagePlainText .= " and ";
       $titleerrors.=", ";
       }
    
    switch($key)
      {
      case 'update_errors': $messagePlainText .= "update errors";$titleerrors.="u=".$value; break;
      case 'configure_errors': $messagePlainText .= "configure errors";$titleerrors.="c=".$value; break;
      case 'build_warnings': $messagePlainText .= "build warnings";$titleerrors.="w=".$value; break;
      case 'build_errors': $messagePlainText .= "build errors";$titleerrors.="b=".$value; break;
      case 'test_errors': $messagePlainText .= "failing tests";$titleerrors.="t=".$value; break;
      }
    
    $i++;
    }  
    
  // Title
  $titleerrors .= "):";
  $title = "FAILED ".$titleerrors." ".$Project->Name;
    
  if($Build->GetSubProjectName())
    {
    $title .= "/".$Build->GetSubProjectName();
    }
  $title .= " - ".$Build->Name." - ".$Build->Type;
    
  //$title = "CDash [".$project_array["name"]."] - ".$site_array["name"];
  //$title .= " - ".$buildname." - ".$buildtype." - ".date(FMT_DATETIMETZ,strtotime($starttime." UTC"));
     
  $messagePlainText .= ".\n";  
  $messagePlainText .= "You have been identified as one of the authors who have checked in changes that are part of this submission ";
  $messagePlainText .= "or you are listed in the default contact list.\n\n";  
  $messagePlainText .= "Details on the submission can be found at ";

  $messagePlainText .= $serverURI;
  $messagePlainText .= "/buildSummary.php?buildid=".$Build->Id;
  $messagePlainText .= "\n\n";
    
  $messagePlainText .= "Project: ".$Project->Name."\n";
  if($Build->GetSubProjectName())
    {
    $messagePlainText .= "SubProject: ".$Build->GetSubProjectName()."\n";
    }
  
  $Site  = new Site();
  $Site->Id = $Build->SiteId;

  $messagePlainText .= "Site: ".$Site->GetName()."\n";
  $messagePlainText .= "Build Name: ".$Build->Name."\n";
  $messagePlainText .= "Build Time: ".date(FMT_DATETIMETZ,strtotime($Build->StartTime." UTC"))."\n";
  $messagePlainText .= "Type: ".$Build->Type."\n";
  
  foreach($emailtext['category'] as $key=>$value)
    {
    switch($key)
      {
      case 'update_errors': $messagePlainText .= "Update errors:".$value."\n"; break;
      case 'configure_errors': $messagePlainText .= "Configure errors:".$value."\n"; break;
      case 'build_warnings': $messagePlainText .= "Warnings:".$value."\n"; break;
      case 'build_errors': $messagePlainText .= "Errors:".$value."\n"; break;
      case 'test_errors': $messagePlainText .= "Tests failing: ".$value."\n"; break;
      }
    }  

  foreach($emailtext['summary'] as $summary)
    {
    $messagePlainText .= $summary;
    }  
  
  $serverName = $CDASH_SERVER_NAME;
  if(strlen($serverName) == 0)
    {
    $serverName = $_SERVER['SERVER_NAME'];
    }
  $messagePlainText .= "\n-CDash on ".$serverName."\n";
  
  // Find the email
  $User = new User();
  $User->Id = $userid;
  $email = $User->GetEmail();
  
  //echo $email."<br>";
  //echo $title."<br>";
  //echo $messagePlainText."<br>";

  // Send the email
  if(mail("$email", $title, $messagePlainText,
     "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" ))
    {
    add_log("email sent to: ".$email,"sendemail ".$Project->Name,LOG_INFO);
    
    // Record that we have send the email
    set_email_sent($userid,$Build->Id,$emailtext);
    }
  else
    {
    add_log("cannot send email to: ".$email,"sendemail ".$Project->Name,LOG_ERR);
    }  
} // end send_email_to_user


/** Main function to send email if necessary */
function sendemail($handler,$projectid)
{
  include("cdash/config.php");
  include_once("cdash/common.php");  
  require_once("cdash/pdo.php");
  require_once("models/build.php");
  require_once("models/project.php");
  require_once("models/buildgroup.php");
  
  $Project = new Project();
  $Project->Id = $projectid;
  $Project->Fill();

  // If we shouldn't sent any emails we stop
  if($Project->EmailBrokenSubmission == 0)
    {
    return;
    }
  
  // Get the build id
  $name = $handler->getBuildName();
  $stamp = $handler->getBuildStamp();
  $sitename = $handler->getSiteName();
  $buildid = get_build_id($name,$stamp,$projectid,$sitename);
  if($buildid<0)
    {
    return;
    }
  
  add_log("Buildid ".$buildid,"sendemail ".$Project->Name,LOG_INFO);
      
  //  Check if the group as no email
  $Build = new Build();
  $Build->Id = $buildid;
  $groupid = $Build->GetGroup();
  
  $BuildGroup = new BuildGroup();
  $BuildGroup->Id = $groupid;

  // If we specified no email we stop here
  if($BuildGroup->GetSummaryEmail()==2)
    {
    return;
    }
  
  $errors = check_email_errors($buildid,$Project->EmailTestTimingChanged,
                               $Project->TestTimeMaxStatus,!$Project->EmailRedundantFailures);
  if(!$errors)
    {
    return;
    }

  // If we should send a summary email    
  if($BuildGroup->GetSummaryEmail()==1)
    {
    // Send the summary email
    sendsummaryemail($projectid,$Project->Name,$dashboarddate,$groupid,$errors,$buildid);
    return;
    } // end summary email

  $Build->FillFromId($Build->Id);
  
  // Get the list of person who should get the email
  $userids = lookup_emails_to_send($errors, $buildid, $projectid,$Build->Type);

  // Loop through the users
  foreach($userids as $userid)
    {
    $emailtext = array();
    $emailtext['nerror'] = 0;
    
    // Check if an email has been sent already for this user
    foreach($errors as $errorkey => $nerrors)
      {
      if($nerrors == 0)
        {
        continue;
        }

      if(!check_email_sent($userid,$buildid,$errorkey))
        {
        $emailtext['summary'][$errorkey] = get_email_summary($buildid,$errors,$errorkey,$Project->EmailMaxItems,
                                                             $Project->EmailMaxChars,$Project->TestTimeMaxStatus,
                                                             $Project->EmailTestTimingChanged);
        $emailtext['category'][$errorkey] = $nerrors;
        $emailtext['nerror'] = 1;
        }
      }
    
    // Send the email
    if($emailtext['nerror'] == 1)
      {
      send_email_to_user($userid,$emailtext,$Build,$Project);
      }
    }
}
?>
