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
function checkEmailPreferences($emailcategory,$nconfigures,$nwarnings,$nerrors,$nfailingtests)
{
  include_once("cdash/common.php");
  if($nconfigures>0 && check_email_category("configure",$emailcategory))
    {
    return true;
    }
  if($nwarnings>0 && check_email_category("warning",$emailcategory))
    {
    return true;
    }
  if($nerrors>0 && check_email_category("error",$emailcategory))
    {
    return true;
    }
  if($nfailingtests>0 && check_email_category("test",$emailcategory))
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
function sendsummaryemail($projectid,$projectname,$dashboarddate,$groupid,
                          $nconfigures,$nbuildwarnings,$nbuilderrors,$nfailingtests,$buildid)
{
  include("config.php");
  
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
  if(strlen(pdo_error())>0)
    {
    add_log($query."\n".pdo_error(),"sendemail ".$projectname);
    }
      
  // Loop through the users and add them to the email array  
  while($user_array = pdo_fetch_array($user))
    {
    // If the user is already in the list we quit
    if(strpos($summaryEmail,$user_array["email"]) !== false)
      {
      continue;
      }
        
    // If the user doesn't want to receive email
    if(!checkEmailPreferences($user_array["emailcategory"],$nconfigures,$nbuildwarnings,$nbuilderrors,$nfailingtests))
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
    
    
    $currentPort="";
    $httpprefix="http://";
    if($_SERVER['SERVER_PORT']!=80)
      {
      $currentPort=":".$_SERVER['SERVER_PORT'];
      if($_SERVER['SERVER_PORT']!=80 )
        {
        $httpprefix = "https://";
        }
      }
    if($CDASH_USE_HTTPS === true)
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
    
    $messagePlainText .= "To see this dashboard:\n";  
    $messagePlainText .= $currentURI;
    $messagePlainText .= "/index.php?project=".$projectname."&date=".$dashboarddate;
    $messagePlainText .= "\n\n";
    
    $messagePlainText .= "\n-CDash on ".$serverName."\n";
      
    // Send the email
    if(mail("$summaryEmail", $title, $messagePlainText,
         "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" ))
      {
      add_log("email sent to: ".$email,"sendemail ".$projectname);
      return;
      }
    else
      {
      add_log("cannot send email to: ".$email,"sendemail ".$projectname);
      }
    } // end $summaryEmail!=""
}
                          
/** Main function to send email if necessary */
function sendemail($handler,$projectid)
{
  
  include_once("cdash/common.php");
  include("cdash/config.php");
  require_once("cdash/pdo.php");
  require_once("models/build.php");
  
  // Check if we should send the email
  $project = pdo_query("SELECT name,emailbrokensubmission,emailredundantfailures,emailmaxitems,
                               emailmaxchars,emailtesttimingchanged,nightlytime,
                               testtimemaxstatus FROM project WHERE id='$projectid'");
  $project_array = pdo_fetch_array($project);
  $project_emailtesttimingchanged = $project_array["emailtesttimingchanged"];
  $project_testtimemaxstatus =  $project_array["testtimemaxstatus"];
  $projectname = $project_array["name"];
  
  if($project_array["emailbrokensubmission"] == 0)
    {
    return;
    }
  
  $name = $handler->getBuildName();
  $stamp = $handler->getBuildStamp();
  $sitename = $handler->getSiteName();

  // Find the build id
  $buildid = get_build_id($name,$stamp,$projectid,$sitename);
  if($buildid<0)
    {
    return;
    }
  add_log("Buildid ".$buildid,"sendemail ".$projectname);

  // Get the buildgroup
  $buildgroup_array = pdo_fetch_array(pdo_query("SELECT groupid FROM build2group WHERE buildid=$buildid"));
  add_last_sql_error("sendmail");
  $groupid = $buildgroup_array["groupid"];
  
  // Check if the group as summaryemail enable
  $summaryemail_array = pdo_fetch_array(pdo_query("SELECT summaryemail FROM buildgroup WHERE id=$groupid"));
  add_last_sql_error("sendmail");
  
  // If we specified no email we stop here
  if($summaryemail_array["summaryemail"]==2)
    {
    return;
    }
    
  // Find if the build has any configure errors
  $configure = pdo_query("SELECT status FROM configure WHERE buildid='$buildid'");
  add_last_sql_error("sendemail ".$projectname);
  $nconfigures = 0;
  if(pdo_num_rows($configure)>0)
    {
    $configure_array = pdo_fetch_array($configure);
    if($configure_array["status"]>0)
      {
      $nconfigures = 1;
      }
    }

  // Find if the build has any errors
  $builderror = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$buildid' AND type='0'");
  $builderror_array = pdo_fetch_array($builderror);
  $nbuilderrors = $builderror_array[0];
  $builderror = pdo_query("SELECT count(*) FROM buildfailure WHERE buildid='$buildid' AND type='0'");
  $builderror_array = pdo_fetch_array($builderror);
  $nbuilderrors += $builderror_array[0];
     
  // Find if the build has any warnings
  $buildwarning = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$buildid' AND type='1'");
  $buildwarning_array = pdo_fetch_array($buildwarning);
  $nbuildwarnings = $buildwarning_array[0];
  $buildwarning = pdo_query("SELECT count(*) FROM buildfailure WHERE buildid='$buildid' AND type='1'");
  $buildwarning_array = pdo_fetch_array($buildwarning);
  $nbuildwarnings += $buildwarning_array[0];
    
    
  // Find if the build has any test failings
  if($project_emailtesttimingchanged)
    {
    $sql = "SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND (status='failed' OR timestatus>".qnum($project_testtimemaxstatus).")";
    }
  else
    {
    $sql = "SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='failed'";
    }  
    
  $nfail_array = pdo_fetch_array(pdo_query($sql));
  $nfailingtests = $nfail_array[0];

  // Green build we return
  if($nconfigures == 0 && $nfailingtests==0 && $nbuildwarnings==0 && $nbuilderrors==0) 
    {
    return;
    }
    
  $build = pdo_query("SELECT * FROM build WHERE id='$buildid'");
  $build_array = pdo_fetch_array($build);
  $buildtype = $build_array["type"];
  $siteid = $build_array["siteid"];
  $buildname = $build_array["name"];
  $starttime = $build_array["starttime"];
  
  // look for the previous build only if necessary
  if($project_array["emailredundantfailures"]==0)
    {
    // Find the previous build
    $previousbuild = pdo_query("SELECT id FROM build WHERE siteid='$siteid' AND projectid='$projectid' 
                                 AND name='$buildname' AND type='$buildtype' 
                                 AND starttime<'$starttime' ORDER BY starttime DESC  LIMIT 1");
                                 
    add_last_sql_error("sendemail ".$projectname);
                                 
    if(pdo_num_rows($previousbuild) > 0)
      {
      $previousbuild_array = pdo_fetch_array($previousbuild);
      $previousbuildid = $previousbuild_array["id"];
      
      $configure = pdo_query("SELECT status FROM configure WHERE buildid='$previousbuildid'");
      add_last_sql_error("sendemail ".$projectname);
      $npreviousconfigures = 0;
      if(pdo_num_rows($configure)>0)
        {
        $configure_array = pdo_fetch_array($configure);
        if($configure_array["status"]>0)
          {
          $npreviousconfigures = 1;
          }
        }
      
      // Find if the build has any errors
      $builderror = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$previousbuildid' AND type='0'");
      add_last_sql_error("sendemail ".$projectname);
      $builderror_array = pdo_fetch_array($builderror);
      $npreviousbuilderrors = $builderror_array[0];
      $builderror = pdo_query("SELECT count(*) FROM buildfailure WHERE buildid='$previousbuildid' AND type='0'");
      $builderror_array = pdo_fetch_array($builderror);
      $npreviousbuilderrors += $builderror_array[0];
         
      // Find if the build has any warnings
      $buildwarning = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$previousbuildid' AND type='1'");
      add_last_sql_error("sendemail ".$projectname);
      $buildwarning_array = pdo_fetch_array($buildwarning);
      $npreviousbuildwarnings = $buildwarning_array[0];
      $buildwarning = pdo_query("SELECT count(*) FROM buildfailure WHERE buildid='$previousbuildid' AND type='1'");
      add_last_sql_error("sendemail ".$projectname);
       $buildwarning_array = pdo_fetch_array($buildwarning);
       $npreviousbuildwarnings += $buildwarning_array[0];

    
      // Find if the build has any test failings
      if($project_emailtesttimingchanged)
        {
        $sql = "SELECT count(testid) FROM build2test WHERE buildid='$previousbuildid' AND (status='failed' OR timestatus>".qnum($project_testtimemaxstatus).")";
        }
      else
        {
        $sql = "SELECT count(testid) FROM build2test WHERE buildid='$previousbuildid' AND status='failed'";
        }
      $nfail_array = pdo_fetch_array(pdo_query($sql));
      add_last_sql_error("sendmail");
      $npreviousfailingtests = $nfail_array[0];
      
      
      //add_log("previousbuildid=".$previousbuildid,"sendemail ".$projectname);
      //add_log("test=".$npreviousfailingtests."=".$nfailingtests,"sendemail ".$projectname);
      //add_log("warning=".$npreviousbuildwarnings."=".$nbuildwarnings,"sendemail ".$projectname);
      //add_log("error=".$npreviousbuilderrors."=".$nbuilderrors,"sendemail ".$projectname);
  
      // If we have exactly the same number of (or less) test failing, errors and warnings has the previous build
      // we don't send any emails
      if($npreviousconfigures>=$nconfigures
         && $npreviousfailingtests>=$nfailingtests
         && $npreviousbuildwarnings>=$nbuildwarnings
         && $npreviousbuilderrors==$nbuilderrors
        ) 
        {
        add_log("returning","sendemail ".$projectname);
        return;
        }
      }
    } // end emailredundantfailures
 
     
  // Current URI of the dashboard
  $currentPort="";
  $httpprefix="http://";
  if($_SERVER['SERVER_PORT']!=80)
    {
    $currentPort=":".$_SERVER['SERVER_PORT'];
    if($_SERVER['SERVER_PORT']!=80 )
      {
      $httpprefix = "https://";
      }
    }
  if($CDASH_USE_HTTPS === true)
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
  
    
  if($summaryemail_array["summaryemail"]==1)
    {
    // Check if the email has been sent
    $date = ""; // now
    list ($previousdate, $currentstarttime, $nextdate, $today) = get_dates($date,$project_array["nightlytime"]);
    $dashboarddate = gmdate(FMT_DATE, $currentstarttime);

    // If we already have it we return
    if(pdo_num_rows(pdo_query("SELECT buildid FROM summaryemail WHERE date='$dashboarddate' AND groupid=$groupid"))==1)
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
    $dailyupdatequery = pdo_query("SELECT status FROM dailyupdate WHERE projectid='$projectid' AND date='$dashboarddate'");
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

    // Send the summary email
    sendsummaryemail($projectid,$projectname,$dashboarddate,$groupid,
                     $nconfigures,$nbuildwarnings,$nbuilderrors,$nfailingtests,$buildid);
    return;
    } // end summary email

  // Send a summary of the errors/warnings and test failings
  $project_emailmaxitems = $project_array["emailmaxitems"];
  $project_emailmaxchars = $project_array["emailmaxchars"];
  
  // Configure information
  $configure_information = "";
  if($nconfigures>0)
    {
    $configure_information .= "\n\n*Configure*\n";
 
    $configure = pdo_query("SELECT status,log FROM configure WHERE buildid=".qnum($buildid));
    $configure_array = pdo_fetch_array($configure);
  
    $configure_information .= "Status: ".$configure_array["status"]." (".$currentURI."/viewConfigure.phpbuildid=".$buildid.")\n";
    $configure_information .= "Output: ";
    $configure_information .= substr($configure_array["log"],0,$project_emailmaxchars);
    $configure_information .= "\n";
    } // end $nconfigures > 0
      
  $error_information = "";
  if($nbuilderrors>0)
    {
    $info = "";
    $error_information .= "\n\n*Error*\n";
    
    // Old error format
    $error_query = pdo_query("SELECT sourcefile,text,sourceline,postcontext FROM builderror WHERE buildid=".qnum($buildid)." AND type=0 LIMIT $project_emailmaxitems");
    add_last_sql_error("sendmail");
    while($error_array = pdo_fetch_array($error_query))
      {
      if(strlen($error_array["sourcefile"])>0)
        {
        $info .= $error_array["sourcefile"]." line ".sourceline." (".$currentURI."/viewBuildError.php?type=0&buildid=".$buildid.")\n";
        $info .= $error_array["text"]."\n";
        }
      else
        {
        $info .= $error_array["text"]."\n".$error_array["postcontext"]."\n";
        }
      $error_information .= substr($info,0,$project_emailmaxchars);
      }
      
    // New error format
    $info = "";
    $error_query = pdo_query("SELECT sourcefile,stdoutput,stderror FROM buildfailure WHERE buildid=".qnum($buildid)." AND type=0 LIMIT $project_emailmaxitems");
    add_last_sql_error("sendmail");
    while($error_array = pdo_fetch_array($error_query))
      {
      if(strlen($error_array["sourcefile"])>0)
        {
        $info .= $error_array["sourcefile"]." (".$currentURI."/viewBuildError.php?type=0&buildid=".$buildid.")\n";
        }
      if(strlen($error_array["stdoutput"])>0)
        {  
        $info .= $error_array["stdoutput"]."\n";
        }
      if(strlen($error_array["stderror"])>0)
        {  
        $info .= $error_array["stderror"]."\n";
        }
      $error_information .= substr($info,0,$project_emailmaxchars);
      }
    $error_information .= "\n";
    }
  
  $warning_information = "";
  if($nbuildwarnings>0)
    {
    $info = "";
    $warning_information .= "\n\n*Warnings*\n";
    $error_query = pdo_query("SELECT sourcefile,text,sourceline,postcontext FROM builderror WHERE buildid=".qnum($buildid)." AND type=1 LIMIT $project_emailmaxitems");
    add_last_sql_error("sendmail");
    while($error_array = pdo_fetch_array($error_query))
      {
      if(strlen($error_array["sourcefile"])>0)
        {
        $info .= $error_array["sourcefile"]." line ".$error_array["sourceline"]." (".$currentURI."/viewBuildError.php?type=1&buildid=".$buildid.")\n";
        $info .= $error_array["text"]."\n";
        }
      else
        {
        $info .= $error_array["text"]."\n".$error_array["postcontext"]."\n";
        }
      $warning_information .= substr($info,0,$project_emailmaxchars);
      }
    
    // New error format
    $info = "";
    $error_query = pdo_query("SELECT sourcefile,stdoutput,stderror FROM buildfailure WHERE buildid=".qnum($buildid)." AND type=1 LIMIT $project_emailmaxitems");
    add_last_sql_error("sendmail");
    while($error_array = pdo_fetch_array($error_query))
      {
      if(strlen($error_array["sourcefile"])>0)
        {
        $info .= $error_array["sourcefile"]." (".$currentURI."/viewBuildError.php?type=1&buildid=".$buildid.")\n";
        }
      if(strlen($error_array["stdoutput"])>0)
        {  
        $info .= $error_array["stdoutput"]."\n";
        }
      if(strlen($error_array["stderror"])>0)
        {  
        $info .= $error_array["stderror"]."\n";
        }
      $warning_information .= substr($info,0,$project_emailmaxchars);
      }
    $warning_information .= "\n";  
    }
      
  $test_information = "";
  if($nfailingtests>0)
    {
    $test_information .= "\n\n*Tests failing*\n";
    $sql = "";
    if($project_emailtesttimingchanged)
      {
      $sql = "OR timestatus>".qnum($project_testtimemaxstatus);
      }
    $test_query = pdo_query("SELECT test.name,test.id FROM build2test,test WHERE build2test.buildid=".qnum($buildid)." AND test.id=build2test.testid AND (build2test.status='failed'".$sql.") LIMIT $project_emailmaxitems");
    add_last_sql_error("sendmail");
    while($test_array = pdo_fetch_array($test_query))
      {
      $info = $test_array["name"]."(".$currentURI."/testDetails.php?test=".$test_array["id"]."&build=".$buildid.")\n";
      $test_information .= substr($info,0,$project_emailmaxchars);
      }
    $test_information .= "\n";
    }

  // We have a test failing so we send emails
  $email = "";
  
  // Find the users
  $authors = pdo_query("SELECT author FROM updatefile WHERE buildid='$buildid'");
  add_last_sql_error("sendmail");
  while($authors_array = pdo_fetch_array($authors))
    {
    $author = $authors_array["author"];
    if($author=="Local User")
      {
      continue;
      }
    
    // Find a matching name in the database
    $query = "SELECT ".qid("user").".email,user2project.emailcategory,".qid("user").".id
                     FROM ".qid("user").",user2project WHERE user2project.projectid='$projectid' 
                     AND user2project.userid=".qid("user").".id AND user2project.cvslogin='$author'
                     AND user2project.emailtype>0
                     ";
    $user = pdo_query($query);
    add_last_sql_error("sendmail");
    
    if(pdo_num_rows($user)==0)
      {
      // Should send an email to the project admin to let him know that this user is not registered
      continue;
      }
    
    $user_array = pdo_fetch_array($user);  
    
    // If the user doesn't want to receive email
    if(!checkEmailPreferences($user_array["emailcategory"],$nconfigures,$nbuildwarnings,$nbuilderrors,$nfailingtests))
      {
      continue;
      }
    
    // Check if the labels are defined for this user
    if(!checkEmailLabel($projectid, $user_array["id"], $buildid))
      {
      continue;
      }
       
    // don't add the same user twice
    if(strpos($email,$user_array["email"]) !== false)
     {
     continue;
     }
  
    if($email != "")
      {
      $email .= ", ";
      }
    $email .= $user_array["email"];
    } 

 // Select the users who want to receive all emails
 $user = pdo_query("SELECT ".qid("user").".email,user2project.emailtype,user2project.emailcategory,".qid("user").".id FROM ".qid("user").",user2project WHERE user2project.projectid='$projectid' 
                    AND user2project.userid=".qid("user").".id AND user2project.emailtype>1");
 add_last_sql_error("sendmail");
 while($user_array = pdo_fetch_array($user))
   {
   // If the user is already in the list we quit
   if(strpos($email,$user_array["email"]) !== false)
     {
     continue;
     }
  
   // If the user doesn't want to receive email
   if(!checkEmailPreferences($user_array["emailcategory"],$nconfigures,$nbuildwarnings,$nbuilderrors,$nfailingtests))
     {
     continue;
     }
  
   // Check if the labels are defined for this user
   if(!checkEmailLabel($projectid, $user_array["id"], $buildid))
      {
      continue;
      }
      
  // Nightly build notification
  if($user_array["emailtype"] == 2 && $buildtype=="Nightly")
    {
    if($email != "")
      {
      $email .= ", ";
      }
    $email .= $user_array["email"];
    }
  else if($user_array["emailtype"] == 3) // want to receive all emails
    {
    if($email != "")
      {
      $email .= ", ";
      }
    $email .= $user_array["email"];
    }
  }
  
  // Some variables we need for the email
  $site = pdo_query("SELECT name FROM site WHERE id='$siteid'");
  $site_array = pdo_fetch_array($site);

  if($email != "")
    {
    $Build = new Build();
    $Build->Id = $buildid;

    $messagePlainText = "A submission to CDash for the project ".$project_array["name"]." has ";
    $titleerrors = "(";
    
    $i=0;
    if($nconfigures>0)
      {
      $messagePlainText .= "configure errors";
      $titleerrors.="c=".$nconfigures;
      $i++;
      }
      
    if($nbuilderrors>0)
      {
      if($i>0)
         {
         $messagePlainText .= " and ";
         $titleerrors.=", ";
         }
      $messagePlainText .= "build errors";
      $titleerrors.="b=".$nbuilderrors;
      $i++;
      }
    
    if($nbuildwarnings>0)
      {
      if($i>0)
         {
         $messagePlainText .= " and ";
         $titleerrors.=", ";
         }
      $messagePlainText .= "build warnings";
      $titleerrors.="w=".$nbuildwarnings;
      $i++;
      } 

    if($nfailingtests>0)
      {
      if($i>0)
         {
         $messagePlainText .= " and ";
         $titleerrors.=", ";
         }
      $messagePlainText .= "failing tests";
      $titleerrors.="t=".$nfailingtests;
      $i++;
      }
    
    // Title
    $titleerrors .= "):";
    $title = "FAILED ".$titleerrors." ".$project_array["name"];
    
    if($Build->GetSubProjectName())
      {
      $title .= "/".$Build->GetSubProjectName();
      }
    $title .= " - ".$buildname."-".$buildtype;
    
    //$title = "CDash [".$project_array["name"]."] - ".$site_array["name"];
    //$title .= " - ".$buildname." - ".$buildtype." - ".date(FMT_DATETIMETZ,strtotime($starttime." UTC"));
     
    $messagePlainText .= ".\n";  
    $messagePlainText .= "You have been identified as one of the authors who have checked in changes that are part of this submission ";
    $messagePlainText .= "or you are listed in the default contact list.\n\n";  
    $messagePlainText .= "Details on the submission can be found at ";

    $messagePlainText .= $currentURI;
    $messagePlainText .= "/buildSummary.php?buildid=".$buildid;
    $messagePlainText .= "\n\n";
    
    $messagePlainText .= "Project: ".$project_array["name"]."\n";
    $messagePlainText .= "Site: ".$site_array["name"]."\n";
    $messagePlainText .= "Build Name: ".$buildname."\n";
    $messagePlainText .= "Build Time: ".date(FMT_DATETIMETZ,strtotime($starttime." UTC"))."\n";
    $messagePlainText .= "Type: ".$buildtype."\n";

    if($nconfigures>0)
      {
      $messagePlainText .= "Configure errors: ".$nconfigures."\n";
      }
      
    if($nbuilderrors>0)
      {
      $messagePlainText .= "Errors: ".$nbuilderrors."\n";
      }
      
    if($nbuildwarnings>0)
      {  
      $messagePlainText .= "Warnings: ".$nbuildwarnings."\n";
      }
      
    if($nfailingtests>0)
      {
      $messagePlainText .= "Tests failing: ".$nfailingtests."\n";
      }
     
    // Send the extra information
    $messagePlainText .= $configure_information;
    $messagePlainText .= $error_information;
    $messagePlainText .= $warning_information;
    $messagePlainText .= $test_information; 
      
    $messagePlainText .= "\n-CDash on ".$serverName."\n";
        
    // Send the email
    if(mail("$email", $title, $messagePlainText,
         "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" ))
      {
      add_log("email sent to: ".$email,"sendemail ".$projectname);
      }
    else
      {
      add_log("cannot send email to: ".$email,"sendemail ".$projectname);
      }

    } // end $email!=""
}
?>
