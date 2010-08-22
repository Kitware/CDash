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

/** Check the email preferences for errors */
function checkEmailPreferences($emailcategory,$errors,$fixes=false)
{
  include_once("cdash/common.php");
  
  if($fixes)
    {
    $updates = 0; // for fixes we don't use update 
    $configures=$errors['fixes']['configure_fixes'];
    $builderrors=$errors['fixes']['builderror_fixes'];
    $buildwarnings=$errors['fixes']['buildwarning_fixes'];
    $tests=$errors['fixes']['test_fixes'];  
    }
  else
    {
    $updates=$errors['update_errors'];
    $configures=$errors['configure_errors'];
    $builderrors=$errors['build_errors'];
    $buildwarnings=$errors['build_warnings'];
    $tests=$errors['test_errors'];
    }
  
  if($updates>0 && check_email_category("update",$emailcategory))
    {
    return true;
    }
  if($configures>0 && check_email_category("configure",$emailcategory))
    {
    return true;
    }
  if($buildwarnings>0 && check_email_category("warning",$emailcategory))
    {
    return true;
    }
  if($builderrors>0 && check_email_category("error",$emailcategory))
    {
    return true;
    }
  if($tests>0 && check_email_category("test",$emailcategory))
    {
    return true;
    }
  return false;  
}

/** Given a user check if we should send an email based on labels */
function checkEmailLabel($projectid, $userid, $buildid, $emailcategory=62)
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
  
  $labelarray = array();
  
  if(check_email_category("update",$emailcategory))
    {
    $labelarray['update']['errors']=1;
    }
  if(check_email_category("configure",$emailcategory))
    {
    $labelarray['configure']['errors']=1;
    }
  if(check_email_category("warning",$emailcategory))
    {
    $labelarray['build']['warnings']=1;
    }
  if(check_email_category("error",$emailcategory))
    {
    $labelarray['build']['errors']=1;
    }
  if(check_email_category("test",$emailcategory))
    {
    $labelarray['test']['errors']=1;
    }
    
  $buildlabels = $Build->GetLabels($labelarray);
  if(count(array_intersect($labels, $buildlabels))>0)
    {
    return true;
    }
  return false;
} // end checkEmailLabel

/** Check for errors for a given build. Return false if no errors */
function check_email_errors($buildid,$checktesttimeingchanged,$testtimemaxstatus,$checkpreviouserrors)
{
  // Includes
  require_once("models/buildupdate.php");  
  require_once("models/buildconfigure.php");
  require_once("models/build.php");
  require_once("models/buildtest.php");
  
  $errors = array();  
  $errors['errors'] = true;
  $errors['hasfixes'] = false;

  // Update errors
  $BuildUpdate = new BuildUpdate();
  $BuildUpdate->BuildId = $buildid;
  $errors['update_errors'] = $BuildUpdate->GetNumberOfErrors();

  // Configure errors    
  $BuildConfigure = new BuildConfigure();
  $BuildConfigure->BuildId = $buildid;
  $errors['configure_errors'] = $BuildConfigure->GetNumberOfErrors();
  
  // Build errors and warnings
  $Build = new Build();
  $Build->Id = $buildid;
  $Build->FillFromId($buildid);
  $errors['build_errors'] = $Build->GetNumberOfErrors();
  $errors['build_warnings'] = $Build->GetNumberOfWarnings();

  // Test errors
  $BuildTest = new BuildTest();
  $BuildTest->BuildId = $buildid;
  $errors['test_errors'] = $BuildTest->GetNumberOfFailures($checktesttimeingchanged,$testtimemaxstatus);
    
  // Green build we return
  if( $errors['update_errors'] == 0 
     && $errors['configure_errors'] == 0
     && $errors['build_errors'] == 0
     && $errors['build_warnings'] ==0 
     && $errors['test_errors'] ==0) 
    {
    $errors['errors'] = false;
    }
  
  // look for the previous build
  $previousbuildid = $Build->GetPreviousBuildId();
  if($previousbuildid > 0)
    {
    $error_differences = $Build->GetErrorDifferences($buildid);
    if($errors['errors'] && $checkpreviouserrors)
      { 
      // If the builderroddiff positive and configureerrordiff and testdiff positive are zero we don't send an email
      // we don't send any emails
      if($error_differences['buildwarningspositive']<=0
         && $error_differences['builderrorspositive']<=0
         && $error_differences['configurewarnings']<=0
         && $error_differences['configureerrors']<=0
         && $error_differences['testfailedpositive']<=0
         && $error_differences['testnotrunpositive']<=0
        ) 
        {
        $errors['errors'] = false;
        }
      } // end checking previous errors
     
    if($error_differences['buildwarningsnegative']>0
       || $error_differences['builderrorsnegative']>0
       || $error_differences['configurewarnings']<0
       || $error_differences['configureerrors']<0
       || $error_differences['testfailednegative']>0
       || $error_differences['testnotrunnegative']>0
       ) 
      {
      $errors['hasfixes'] = true;
      $errors['fixes']['configure_fixes'] = $error_differences['configurewarnings']+$error_differences['configureerrors'];
      $errors['fixes']['builderror_fixes'] =  $error_differences['builderrorsnegative'];
      $errors['fixes']['buildwarning_fixes'] = $error_differences['buildwarningsnegative'];
      $errors['fixes']['test_fixes'] = $error_differences['testfailednegative']+$error_differences['testnotrunnegative'];   
      }
    } // end has previous build

  return $errors;
}

/** Return the list of user id who should get emails */
function lookup_emails_to_send($errors,$buildid,$projectid,$buildtype,$fixes=false)
{
  require_once("models/userproject.php");
    
  $userids = array();
  
  // Check if we know to whom we should send the email
  $authors = pdo_query("SELECT author FROM updatefile WHERE buildid=".qnum($buildid));
  add_last_sql_error("sendmail",$projectid,$buildid);
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
      // Daily updates send an email to tell adminsitrator that the user is not registered but we log anyway
      add_log("User: ".$author." is not registered (or has no email) for the project ".$projectid,"SendEmail",LOG_WARNING,$projectid,$buildid);
      continue;
      }
 
    // If the user doesn't want to receive email
    if($fixes && !$UserProject->EmailSuccess)
      {
      continue;  
      }
      
    // Check the categories  
    if(!checkEmailPreferences($UserProject->EmailCategory,$errors,$fixes))
      {
      continue;
      }
    
    // Check if the labels are defined for this user
    if(!checkEmailLabel($projectid,$UserProject->UserId, $buildid, $UserProject->EmailCategory))
      {
      continue;
      }
    
    if(!in_array($UserProject->UserId,$userids))
      {
      $userids[] = $UserProject->UserId;
      }
    } 

  // If it's fixes only concerned users should get the email
  if($fixes)
    {
    return $userids;  
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
    if(!checkEmailLabel($projectid,$user_array['userid'],$buildid, $user_array["emailcategory"]))
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
  include("config.php");
  
  $serverURI = get_server_URI();
  // In the case of asynchronous submission, the serverURI contains /cdash
  // we need to remove it
  if($CDASH_ASYNCHRONOUS_SUBMISSION)
    {
    $serverURI = substr($serverURI,0,strrpos($serverURI,"/"));  
    }
    
  $information = "";
  
  // Update information
  if($errorkey == 'update_errors')
    {
    $information = "\n\n*Update*\n";
 
    $update = pdo_query("SELECT command,status FROM buildupdate WHERE buildid=".qnum($buildid));
    $update_array = pdo_fetch_array($update);
  
    $information .= "Status: ".$update_array["status"]." (".$serverURI."/viewUpdate.php?buildid=".$buildid.")\n";
    $information .= "Command: ";
    $information .= substr($update_array["command"],0,$maxchars);
    $information .= "\n";
    } // endconfigure   
  else if($errorkey == 'configure_errors') // Configure information
    {
    $information = "\n\n*Configure*\n";
 
    $configure = pdo_query("SELECT status,log FROM configure WHERE buildid=".qnum($buildid));
    $configure_array = pdo_fetch_array($configure);
  
    $information .= "Status: ".$configure_array["status"]." (".$serverURI."/viewConfigure.php?buildid=".$buildid.")\n";
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
        $info .= $error_array["sourcefile"]." line ".$error_array["sourceline"]." (".$serverURI."/viewBuildError.php?type=0&buildid=".$buildid.")\n";
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
    
    $error_query = pdo_query("SELECT sourcefile,text,sourceline,postcontext FROM builderror 
                              WHERE buildid=".qnum($buildid)." AND type=1 ORDER BY logline LIMIT $maxitems");
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
    $error_query = pdo_query("SELECT sourcefile,stdoutput,stderror FROM buildfailure WHERE buildid=".qnum($buildid).
                             " AND type=1 ORDER BY id LIMIT $maxitems");
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
      $information .= substr($info,0,$maxchars)."\n";
      }
    $information .= "\n";  
    }
 else if($errorkey == 'test_errors')
    {   
    $sql = "";
    if($emailtesttimingchanged)
      {
      $sql = "OR timestatus>".qnum($testtimemaxstatus);
      }
    $test_query = pdo_query("SELECT test.name,test.id FROM build2test,test WHERE build2test.buildid=".qnum($buildid).
                            " AND test.id=build2test.testid AND (build2test.status='failed'".$sql.") LIMIT $maxitems");
    add_last_sql_error("sendmail");
    $numrows = pdo_num_rows($test_query);
    
    if($numrows>0)
      {
      $information .= "\n\n*Tests failing*";
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
      } // end test failing > 0
      
    // Add the tests not run
    $test_query = pdo_query("SELECT test.name,test.id FROM build2test,test WHERE build2test.buildid=".qnum($buildid).
                            " AND test.id=build2test.testid AND (build2test.status='notrun'".$sql.") LIMIT $maxitems");
    add_last_sql_error("sendmail");
    $numrows = pdo_num_rows($test_query);
    
    if($numrows>0)
      {
      $information .= "\n\n*Tests not run*";
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
      } // end test not run > 0
    }

  return $information;
} // end get_email_summary


/** Send a summary email */
function sendsummaryemail($projectid,$dashboarddate,$groupid,$errors,$buildid)
{
  include("config.php");
  require_once("models/userproject.php");
  require_once("models/user.php");
     
  $Project = new Project();
  $Project->Id = $projectid;
  $Project->Fill();
  
  // Check if the email has been sent
  $date = ""; // now
  list ($previousdate, $currentstarttime, $nextdate, $today) = get_dates($date,$Project->NightlyTime);
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
    pdo_query("UPDATE dailyupdate SET status='2' WHERE projectid=".qnum($projectid)." AND date='$dashboarddate'");
    return;
    }
       
  // Find the current updaters from the night using the dailyupdatefile table
  $summaryEmail = "";
  $query = "SELECT ".qid("user").".email,user2project.emailcategory,".qid("user").".id FROM ".qid("user").",user2project,dailyupdate,dailyupdatefile WHERE 
                           user2project.projectid=".qnum($projectid)."
                           AND user2project.userid=".qid("user").".id 
                           AND user2project.cvslogin=dailyupdatefile.author
                           AND dailyupdatefile.dailyupdateid=dailyupdate.id
                           AND dailyupdate.date='$dashboarddate'
                           AND dailyupdate.projectid=".qnum($projectid)."
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
    if(!checkEmailLabel($projectid, $user_array["id"], $buildid, $user_array["emailcategory"]))
      {
      continue;
      }
      
    if($summaryEmail != "")
      {
      $summaryEmail .= ", ";
      }
    $summaryEmail .= $user_array["email"];
    }
  
  // Select the users that are part of this build
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
      continue;
      }
       
    // If the user doesn't want to receive email
    if(!checkEmailPreferences($UserProject->EmailCategory,$errors))
      {
      continue;
      }
    
    // Check if the labels are defined for this user
    if(!checkEmailLabel($projectid,$UserProject->UserId, $buildid, $UserProject->EmailCategory))
      {
      continue;
      }
    
    // Find the email
    $User = new User();
    $User->Id = $UserProject->UserId;
    $useremail = $User->GetEmail();
    
    // If the user is already in the list we quit
    if(strpos($summaryEmail,$useremail) !== false)
       {
       continue;
       }
 
    if($summaryEmail != "")
      {
      $summaryEmail .= ", ";
      }
    $summaryEmail .= $useremail;
    } 
  
    
    
  // In the case of asynchronous submission, the serverURI contains /cdash
  // we need to remove it
  $currentURI = get_server_URI();
  if($CDASH_ASYNCHRONOUS_SUBMISSION)
    {
    $currentURI = substr($currentURI,0,strrpos($currentURI,"/"));  
    }  
    
  // Select the users who want to receive all emails
  $user = pdo_query("SELECT ".qid("user").".email,user2project.emailtype,".qid("user").".id  FROM ".qid("user").",user2project 
                     WHERE user2project.projectid=".qnum($projectid)." 
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

    $title = "CDash [".$Project->Name."] - ".$summaryemail_array["name"]." Failures";
      
    $messagePlainText = "The \"".$summaryemail_array["name"]."\" group has either errors, warnings or test failures.\n";
    $messagePlainText .= "You have been identified as one of the authors who have checked in changes that are part of this submission ";
    $messagePlainText .= "or you are listed in the default contact list.\n\n";  
    
    $messagePlainText .= "To see this dashboard:\n";  
    $messagePlainText .= $currentURI;
    $messagePlainText .= "/index.php?project=".$Project->Name."&date=".$today;
    $messagePlainText .= "\n\n";
    
    $messagePlainText .= "Summary of the first build failure:\n";
    // Check if an email has been sent already for this user
    foreach($errors as $errorkey => $nerrors)
      {
       $messagePlainText .= get_email_summary($buildid,$errors,$errorkey,$Project->EmailMaxItems,
                                             $Project->EmailMaxChars,$Project->TestTimeMaxStatus,
                                             $Project->EmailTestTimingChanged);
      }
    $messagePlainText .= "\n\n";
    
    $serverName = $CDASH_SERVER_NAME;
    if(strlen($serverName) == 0)
      {
      $serverName = $_SERVER['SERVER_NAME'];
      }
    
    $messagePlainText .= "\n-CDash on ".$serverName."\n";
    
    // If this is the testing
    if($CDASH_TESTING_MODE)
      {
      add_log($summaryEmail,"TESTING: EMAIL",LOG_TESTING);
      add_log($title,"TESTING: EMAILTITLE",LOG_TESTING);
      add_log($messagePlainText,"TESTING: EMAILBODY",LOG_TESTING);
      }
    else
      {
      // Send the email
      if(mail("$summaryEmail", $title, $messagePlainText,
           "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" ))
        {
        add_log("summary email sent to: ".$email,"sendemail ".$Project->Name,LOG_INFO);
        return;
        }
      else
        {
        add_log("cannot send summary email to: ".$email,"sendemail ".$Project->Name,LOG_ERR);
        }
      }
    } // end $summaryEmail!=""
}

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
      case 'update_fixes': $category=6; break;
      case 'configure_fixes': $category=7; break;
      case 'buildwarning_fixes': $category=8; break;
      case 'builderror_fixes': $category=9; break;
      case 'test_fixes': $category=10; break;
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
    case 'update_fixes': $category=6; break;
    case 'configure_fixes': $category=7; break;
    case 'buildwarning_fixes': $category=8; break;
    case 'builderror_fixes': $category=9; break;
    case 'test_fixes': $category=10; break;
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

/** Send the email to the user when he fixed something */
function send_email_fix_to_user($userid,$emailtext,$Build,$Project)
{
  include("cdash/config.php");
  include_once("cdash/common.php");  
  require_once("models/site.php");
  require_once("models/user.php");

  $serverURI = get_server_URI();
  // In the case of asynchronous submission, the serverURI contains /cdash
  // we need to remove it
  if($CDASH_ASYNCHRONOUS_SUBMISSION)
    {
    $serverURI = substr($serverURI,0,strrpos($serverURI,"/"));  
    }
   
  $messagePlainText = "Congratulations, a submission to CDash for the project ".$Project->Name." has ";
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
      case 'update_fixes': $messagePlainText .= "fixed update errors";$titleerrors.="u=".$value; break;
      case 'configure_fixes': $messagePlainText .= "fixed configure errors";$titleerrors.="c=".$value; break;
      case 'buildwarning_fixes': $messagePlainText .= "fixed build warnings";$titleerrors.="w=".$value; break;
      case 'builderror_fixes': $messagePlainText .= "fixed build errors";$titleerrors.="b=".$value; break;
      case 'test_fixes': $messagePlainText .= "fixed failing tests";$titleerrors.="t=".$value; break;
      }
    
    $i++;
    }  
    
  // Title
  $titleerrors .= "):";
  $title = "PASSED ".$titleerrors." ".$Project->Name;
    
  if($Build->GetSubProjectName())
    {
    $title .= "/".$Build->GetSubProjectName();
    }
  $title .= " - ".$Build->Name." - ".$Build->Type;
         
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
      case 'update_fixes': $messagePlainText .= "Update error fixed: ".$value."\n"; break;
      case 'configure_fixes': $messagePlainText .= "Configure error fixed: ".$value."\n"; break;
      case 'buildwarning_fixes': $messagePlainText .= "Warning fixed: ".$value."\n"; break;
      case 'builderror_fixes': $messagePlainText .= "Error fixed: ".$value."\n"; break;
      case 'test_fixes': $messagePlainText .= "Tests fixed: ".$value."\n"; break;
      }
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

  // If this is the testing
  if($CDASH_TESTING_MODE)
    {
    add_log($email,"TESTING: EMAIL",LOG_TESTING);
    add_log($title,"TESTING: EMAILTITLE",LOG_TESTING);
    add_log($messagePlainText,"TESTING: EMAILBODY",LOG_TESTING);
    // Record that we have send the email
    set_email_sent($userid,$Build->Id,$emailtext);
    }
  else
    {
    // Send the email
    if(mail("$email", $title, $messagePlainText,
     "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" ))
      {
      add_log("email sent to: ".$email." with fixes ".$titleerrors." for build ".$Build->Id,"sendemail ".$Project->Name,LOG_INFO);
    
      // Record that we have send the email
      set_email_sent($userid,$Build->Id,$emailtext);
      }
    else
      {
      add_log("cannot send email to: ".$email,"sendemail ".$Project->Name,LOG_ERR);
      }
    } // end if testing
} // end send_email_fix_to_user

/** Send the email to a user */
function send_email_to_user($userid,$emailtext,$Build,$Project)
{
  include("cdash/config.php");
  include_once("cdash/common.php");  
  require_once("models/site.php");
  require_once("models/user.php");

  $serverURI = get_server_URI();
  // In the case of asynchronous submission, the serverURI contains /cdash
  // we need to remove it
  if($CDASH_ASYNCHRONOUS_SUBMISSION)
    {
    $serverURI = substr($serverURI,0,strrpos($serverURI,"/"));  
    }
    
  $messagePlainText = "A submission to CDash for the project ".$Project->Name." has ";
  $titleerrors = "(";

  $i=0;
  foreach($emailtext['category'] as $key=>$value)
    {
    if($key != 'update_errors'
       && $key != 'configure_errors'
       && $key != 'build_warnings'
       && $key != 'build_errors'
       && $key != 'test_errors')
      {
      continue;  
      }
      
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

  // Nothing to send we stop  
  if($i==0)
    {
    return;  
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
      case 'update_errors': $messagePlainText .= "Update errors: ".$value."\n"; break;
      case 'configure_errors': $messagePlainText .= "Configure errors: ".$value."\n"; break;
      case 'build_warnings': $messagePlainText .= "Warnings: ".$value."\n"; break;
      case 'build_errors': $messagePlainText .= "Errors: ".$value."\n"; break;
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

  // If this is the testing
  if($CDASH_TESTING_MODE)
    {
    add_log($email,"TESTING: EMAIL",LOG_TESTING);
    add_log($title,"TESTING: EMAILTITLE",LOG_TESTING);
    add_log($messagePlainText,"TESTING: EMAILBODY",LOG_TESTING);
    // Record that we have send the email
    set_email_sent($userid,$Build->Id,$emailtext);
    }
  else
    {
    // Send the email
    if(mail("$email", $title, $messagePlainText,
     "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" ))
      {
      add_log("email sent to: ".$email." with errors ".$titleerrors." for build ".$Build->Id,"sendemail ".$Project->Name,LOG_INFO);
    
      // Record that we have send the email
      set_email_sent($userid,$Build->Id,$emailtext);
      }
    else
      {
      add_log("cannot send email to: ".$email,"sendemail ".$Project->Name,LOG_ERR);
      }
    } // end if testing
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
  
  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/sendemail.php"))
    {
    include_once("local/sendemail.php");
    $sendEmail = new SendEmail();
    $sendEmail->SetProjectId($projectid);
    }

  // If we shouldn't sent any emails we stop
  if($Project->EmailBrokenSubmission == 0)
    {
    return;
    }
  
  // If the handler has a buildid (it should), we use it
  if(isset($handler->BuildId) && $handler->BuildId>0)
    {
    $buildid = $handler->BuildId;
    }
  else
    {
    // Get the build id
    $name = $handler->getBuildName();
    $stamp = $handler->getBuildStamp();
    $sitename = $handler->getSiteName();
    $buildid = get_build_id($name,$stamp,$projectid,$sitename);
    }
    
  if($buildid<0)
    {
    return;
    }
  
  //add_log("Buildid ".$buildid,"sendemail ".$Project->Name,LOG_INFO);
      
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
  
  // We have some fixes                               
  if($errors['hasfixes'])
    {
    $Build->FillFromId($Build->Id);
    // Get the list of person who should get the email
    $userids = lookup_emails_to_send($errors, $buildid, $projectid,$Build->Type,true);
    foreach($userids as $userid)
      {
      $emailtext = array();
      $emailtext['nfixes'] = 0;
    
      // Check if an email has been sent already for this user
      foreach($errors['fixes'] as $fixkey => $nfixes)
        {
        if($nfixes == 0)
          {
          continue;
          }
  
        if(!check_email_sent($userid,$buildid,$fixkey))
          {
          $emailtext['category'][$fixkey] = $nfixes;
          $emailtext['nfixes'] = 1;
          }
        }
      
      // Send the email
      if($emailtext['nfixes'] == 1)
        {
        send_email_fix_to_user($userid,$emailtext,$Build,$Project);
        }
      }
    } 
                               
  // No error we return
  if(!$errors['errors'])
    {
    return;
    }
  
  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/sendemail.php"))
    {
    $sendEmail->BuildId = $Build->Id;
    $sendEmail->Errors = $errors;
    }
        
  // If we should send a summary email    
  if($BuildGroup->GetSummaryEmail()==1)
    {
    // Send the summary email
    sendsummaryemail($projectid,$dashboarddate,$groupid,$errors,$buildid);
    
    if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/sendemail.php"))
      {
      $sendEmail->SendSummary();
      }
    
    return;
    } // end summary email

  $Build->FillFromId($Build->Id);
    
  // Send build error
  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/sendemail.php"))
    {
    $sendEmail->SendBuildError();
    }
  
  // Get the list of person who should get the email
  $userids = lookup_emails_to_send($errors, $buildid, $projectid,$Build->Type);
  
  // Loop through the users
  foreach($userids as $userid)
    {
    $emailtext = array();
    $emailtext['nerror'] = 0;

    // Tune the error array based on the preferences of the user to make sure he
    // doesn't get email that are unecessary
    $UserProject = new UserProject();
    $UserProject->UserId = $userid;
    $UserProject->ProjectId = $projectid;
    $useremailcategory = $UserProject->GetEmailCategory();

    // Check if an email has been sent already for this user
    foreach($errors as $errorkey => $nerrors)
      {
      if($nerrors == 0 || $errorkey=='errors')
        {
        continue;
        }
        
      // If the user doesn't want to get the email
      $stop = false;
      switch($errorkey)
        {
        case 'update_errors': if(!check_email_category("update",$useremailcategory)) {$stop=true;} break;
        case 'configure_errors': if(!check_email_category("configure",$useremailcategory)) {$stop=true;} break;
        case 'build_errors': if(!check_email_category("error",$useremailcategory)) {$stop=true;} break;
        case 'build_warnings': if(!check_email_category("warning",$useremailcategory)) {$stop=true;} break;
        case 'test_errors': if(!check_email_category("test",$useremailcategory)) {$stop=true;} break;
        }
        
      if($stop)
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
      
      if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/sendemail.php"))
        {
        $sendEmail->UserId = $userid;
        $sendEmail->Text = $emailtext;
        $sendEmail->SendToUser();
        }
      }
    }
}
?>
