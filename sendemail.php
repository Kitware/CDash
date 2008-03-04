<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: ctestparser.php,v $
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

/** Main function to send email if necessary */
function sendemail($parser,$projectid)
{
  include_once("common.php");
  include_once("config.php");
     
  // We send email at the end of the testing
 $testing = @$parser->index["TESTING"];
  if($testing == "")
    {
   return;
   }
   
 // Check if we should send the email
  $project = mysql_query("SELECT name,emailbrokensubmission  FROM project WHERE id='$projectid'");
  $project_array = mysql_fetch_array($project);
  if($project_array["emailbrokensubmission"] == 0)
  {
  return;
   }

  $site = $parser->index["SITE"];
  $i = $site[0];
  $name = $parser->vals[$i]["attributes"]["BUILDNAME"];
  $stamp = $parser->vals[$i]["attributes"]["BUILDSTAMP"];
  
  // Find the build id
  $buildid = get_build_id($name,$stamp,$projectid);
  if($buildid<0)
    {
    return;
    }
  
  add_log("Start buildid=".$buildid,"sendemail");
  
  // Find if the build has any errors
  $builderror = mysql_query("SELECT count(buildid) FROM builderror WHERE buildid='$buildid' AND type='0'");
  $builderror_array = mysql_fetch_array($builderror);
  $nbuilderrors = $builderror_array[0];
     
  // Find if the build has any warnings
  $buildwarning = mysql_query("SELECT count(buildid) FROM builderror WHERE buildid='$buildid' AND type='1'");
  $buildwarning_array = mysql_fetch_array($buildwarning);
  $nbuildwarnings = $buildwarning_array[0];

  // Find if the build has any test failings
  $nfail_array = mysql_fetch_array(mysql_query("SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='failed'"));
  $nfailingtests = $nfail_array[0];

  // Green build we return
  if($nfailingtests==0 && $nbuildwarnings==0 && $nbuilderrors==0) 
    {
    return;
    }
  
  // Find the previous build
  $build = mysql_query("SELECT * FROM build WHERE id='$buildid'");
  $build_array = mysql_fetch_array($build);
  $buildtype = $build_array["type"];
  $siteid = $build_array["siteid"];
  $buildname = $build_array["name"];
  $starttime = $build_array["starttime"];
  
  $previousbuild = mysql_query("SELECT id FROM build WHERE siteid='$siteid' AND projectid='$projectid' AND type='$buildtype' 
                               AND starttime<'$starttime' ORDER BY starttime DESC  LIMIT 1");
  if(mysql_num_rows($previousbuild) > 0)
    {
    $previousbuild_array = mysql_fetch_array($previousbuild);
    $previousbuildid = $previousbuild_array["id"];
    
    // Find if the build has any errors
    $builderror = mysql_query("SELECT count(buildid) FROM builderror WHERE buildid='$previousbuildid' AND type='0'");
    $builderror_array = mysql_fetch_array($builderror);
    $npreviousbuilderrors = $builderror_array[0];
       
    // Find if the build has any warnings
    $buildwarning = mysql_query("SELECT count(buildid) FROM builderror WHERE buildid='$previousbuildid' AND type='1'");
    $buildwarning_array = mysql_fetch_array($buildwarning);
    $npreviousbuildwarnings = $buildwarning_array[0];
  
    // Find if the build has any test failings
    $nfail_array = mysql_fetch_array(mysql_query("SELECT count(testid) FROM build2test WHERE buildid='$previousbuildid' AND status='failed'"));
    $npreviousfailingtests = $nfail_array[0];
    
    //add_log("previousbuildid=".$previousbuildid,"sendemail");
    //add_log("test=".$npreviousfailingtests."=".$nfailingtests,"sendemail");
    //add_log("warning=".$npreviousbuildwarnings."=".$nbuildwarnings,"sendemail");
    //add_log("error=".$npreviousbuilderrors."=".$nbuilderrors,"sendemail");

    // If we have exactly the same number of (or less) test failing, errors and warnings has the previous build
    // we don't send any emails
    if($npreviousfailingtests>=$nfailingtests
       && $npreviousbuildwarnings>=$nbuildwarnings
       && $npreviousbuilderrors==$nbuilderrors
      ) 
      {
      return;
      }
    }
  
  // We have a test failing so we send emails
  $email = "";
  
  // Find the users
  $authors = mysql_query("SELECT author FROM updatefile WHERE buildid='$buildid'");
  while($authors_array = mysql_fetch_array($authors))
    {
    $author = $authors_array["author"];
   
    if($author=="Local User")
      {
      continue;
      }
    
    // Find a matching name in the database
    $user = mysql_query("SELECT user.email FROM user,user2project WHERE user2project.projectid='$projectid' 
                                 AND user2project.userid=user.id AND user2project.cvslogin='$author'");
    if(mysql_num_rows($user)==0)
      {
      // Should send an email to the project admin to let him know that this user is not registered
      continue;
      }
  
    if($email != "")
      {
      $email .= ", ";
      }
     
    $user_array = mysql_fetch_array($user);
    $email .= $user_array["email"];
    } 
  
  // Select the users who want to receive all emails
 $user = mysql_query("SELECT user.email,user2project.emailtype FROM user,user2project WHERE user2project.projectid='$projectid' 
                       AND user2project.userid=user.id AND user2project.emailtype>1");
 while($user_array = mysql_fetch_array($user))
   {
  // If the user is already in the list we quit
  if(strstr($email,$user_array["email"]) !== FALSE)
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
  else // send the email
    {
   if($email != "")
    {
    $email .= ", ";
    }
    $email .= $user_array["email"];
    }
  }
 
  // Some variables we need for the email
  $site = mysql_query("SELECT name FROM site WHERE id='$siteid'");
  $site_array = mysql_fetch_array($site);

  if($email != "")
    {
    $title = "CDash [".$project_array["name"]."] - ".$site_array["name"];
    $title .= " - ".$buildname." - ".$buildtype." - ".date("Y-m-d H:i:s T",strtotime($starttime." UTC"));
    
    $messagePlainText = "A submission to CDash for the project ".$project_array["name"]." has ";
    
    $i=0;
    if($nbuilderrors>0)
      {
      $messagePlainText .= "build errors";
      $i++;
      }
    
    if($nbuildwarnings>0)
      {
      if($i>0)
         {
         $messagePlainText .= " and ";
         }
      $messagePlainText .= "build warnings";
      $i++;
      } 
      
    if($nfailingtests>0)
      {
      if($i>0)
         {
         $messagePlainText .= " and ";
         }
      $messagePlainText .= "failing tests";
      $i++;
      }
     
    $messagePlainText .= ".\n";  
    $messagePlainText .= "You have been identified as one of the authors who have checked in changes that are part of this submission ";
    $messagePlainText .= "or you are listed in the default contact list.\n\n";  
    $messagePlainText .= "Details on the submission can be found at ";

    $currentPort="";

    if($_SERVER['SERVER_PORT']!=80)
      {
      $currentPort=":".$_SERVER['SERVER_PORT'];
      }
    
    $currentURI =  "http://".$_SERVER['SERVER_NAME'].$currentPort.$_SERVER['REQUEST_URI']; 
    $currentURI = substr($currentURI,0,strrpos($currentURI,"/"));
    $messagePlainText .= $currentURI;
    $messagePlainText .= "/buildSummary.php?buildid=".$buildid;
    $messagePlainText .= "\n\n";
    
    $messagePlainText .= "Project: ".$project_array["name"]."\n";
    $messagePlainText .= "Site: ".$site_array["name"]."\n";
    $messagePlainText .= "BuildName: ".$buildname."\n";
    $messagePlainText .= "Type: ".$buildtype."\n";
    
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
     
    $messagePlainText .= "\n-CDash on ".$_SERVER['SERVER_NAME']."\n";
    
    add_log("sending email","sendemail");
    
    // Send the email
    if(mail("$email", $title, $messagePlainText,
         "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" ))
      {
      add_log("email sent to: ".$email,"sendemail");
      }
    else
      {
      add_log("cannot send email to: ".$email,"sendemail");
      }
    } // end $email!=""
  
   add_log("End buildid=".$buildid,"sendemail");
}
?>
