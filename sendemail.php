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

/** Main function to send email if necessary */
function sendemail($parser,$projectid)
{
  include_once("common.php");
  include("config.php");
  require_once("pdo.php");
  add_log("sendmail", "$CDASH_USE_HTTPS = $CDASH_USE_HTTPS");   
  // Send email at the end of the testing xml file or the
  // update xml file.  This is because the update file will
  // contain the information on the users that made the commit
  // however, it may never be submitted, in that case users
  // that have registered with CDash to get email for any broken
  // build should still get email.  The down side is that the 
  // registered users will now get two emails.  
  $testing = @$parser->index["TESTING"];
  $update = @$parser->index["UPDATE"];
  if($testing == "" && $update == "")
    {
    return;
    }

  // Check if we should send the email
  $project = pdo_query("SELECT name,emailbrokensubmission,emailmaxitems,
                               emailmaxchars,emailtesttimingchanged,
                               testtimemaxstatus FROM project WHERE id='$projectid'");
  $project_array = pdo_fetch_array($project);
  if($project_array["emailbrokensubmission"] == 0)
    {
    return;
    }

  $site = $parser->index["SITE"];
  if($testing != "")
    {
    $i = $site[0];
    $name = $parser->vals[$i]["attributes"]["BUILDNAME"];
    $stamp = $parser->vals[$i]["attributes"]["BUILDSTAMP"];
    }
  else
    {
    $i = $parser->index["BUILDNAME"][0];
    $name = $parser->vals[$i]["value"];
    $i = $parser->index["BUILDSTAMP"][0];
    $stamp =  $parser->vals[$i]["value"];
    }

  // Find the build id
  $buildid = get_build_id($name,$stamp,$projectid);
  if($buildid<0)
    {
    return;
    }
  add_log("Start buildid=".$buildid,"sendemail");

  // Find if the build has any errors
  $builderror = pdo_query("SELECT count(buildid) FROM builderror WHERE buildid='$buildid' AND type='0'");
  $builderror_array = pdo_fetch_array($builderror);
  $nbuilderrors = $builderror_array[0];
     
  // Find if the build has any warnings
  $buildwarning = pdo_query("SELECT count(buildid) FROM builderror WHERE buildid='$buildid' AND type='1'");
  $buildwarning_array = pdo_fetch_array($buildwarning);
  $nbuildwarnings = $buildwarning_array[0];

  // Find if the build has any test failings
  if($project_emailtesttimingchanged)
    {
    $sql = "SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND (status='failed' OR timestatus>".qnum($project_testtimemaxstatus).")";
    }
  else
    {
    $sql = "SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='failed'";
    }  
    
  $nfail_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='failed'"));
  $nfailingtests = $nfail_array[0];

  // Green build we return
  if($nfailingtests==0 && $nbuildwarnings==0 && $nbuilderrors==0) 
    {
    return;
    }
  
  // Find the previous build
  $build = pdo_query("SELECT * FROM build WHERE id='$buildid'");
  $build_array = pdo_fetch_array($build);
  $buildtype = $build_array["type"];
  $siteid = $build_array["siteid"];
  $buildname = $build_array["name"];
  $starttime = $build_array["starttime"];
  $project_emailtesttimingchanged = $project_array["emailtesttimingchanged"];
  $project_testtimemaxstatus =  $project_array["testtimemaxstatus"];
    
  $previousbuild = pdo_query("SELECT id FROM build WHERE siteid='$siteid' AND projectid='$projectid' 
                               AND name='$buildname' AND type='$buildtype' 
                               AND starttime<'$starttime' ORDER BY starttime DESC  LIMIT 1");
  if(pdo_num_rows($previousbuild) > 0)
    {
    $previousbuild_array = pdo_fetch_array($previousbuild);
    $previousbuildid = $previousbuild_array["id"];
    
    // Find if the build has any errors
    $builderror = pdo_query("SELECT count(buildid) FROM builderror WHERE buildid='$previousbuildid' AND type='0'");
    $builderror_array = pdo_fetch_array($builderror);
    $npreviousbuilderrors = $builderror_array[0];
       
    // Find if the build has any warnings
    $buildwarning = pdo_query("SELECT count(buildid) FROM builderror WHERE buildid='$previousbuildid' AND type='1'");
    $buildwarning_array = pdo_fetch_array($buildwarning);
    $npreviousbuildwarnings = $buildwarning_array[0];
  
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
  
  // Send a summary of the errors/warnings and test failings
  $project_emailmaxitems = $project_array["emailmaxitems"];
  $project_emailmaxchars = $project_array["emailmaxchars"];
  
  $error_information = "";
  if($npreviousbuilderrors<$nbuilderrors)
    {
    $error_query = pdo_query("SELECT sourcefile,text,sourceline,postcontext FROM build2test WHERE buildid=".qnum($buildid)." AND type=0 LIMIT $project_emailmaxitems");
    while($error_array = pdo_fetch_array($error_query))
      {
      if(strlen($error_array["sourcefile"])>0)
        {
        $info = "*Error* in file ".$error_array["sourcefile"]." line ".sourceline."\n";
        $info .= $error_array["text"]."\n";
        }
     else
        {
        $info = "*Error*\n";
        $info .= $error_array["text"]."\n".$error_array["postcontext"]."\n";
        }
      $error_information .= substr($info,0,$project_emailmaxchars);
      }
    }
  
  $warning_information = "";
  if($npreviousbuildwarnings<$nbuildwarnings)
    {
    $error_query = pdo_query("SELECT sourcefile,text,sourceline,postcontext FROM build2test WHERE buildid=".qnum($buildid)." AND type=1 LIMIT $project_emailmaxitems");
    while($error_array = pdo_fetch_array($error_query))
      {
      if(strlen($error_array["sourcefile"])>0)
        {
        $info = "*Warning* in file ".$error_array["sourcefile"]." line ".sourceline."\n";
        $info .= $error_array["text"]."\n";
        }
     else
        {
        $info = "*Warning*\n";
        $info .= $error_array["text"]."\n".$error_array["postcontext"]."\n";;
        }
      $warning_information .= substr($info,0,$project_emailmaxchars);
      }
    }
      
  $test_information = "";
  if($npreviousfailingtests<$nfailingtests)
    {
    $sql = "";
    if($project_emailtesttimingchanged)
      {
      $sql = "OR timestatus>".qnum($project_testtimemaxstatus);
      }
    $test_query = pdo_query("SELECT test.name FROM build2test,test WHERE test.id=build2test.testid AND (build2test.status='failed'".$sql.") LIMIT $project_emailmaxitems");
    while($test_array = pdo_fetch_array($test_query))
      {
      $info = "*Test failing*:".$test_array["name"]."\n";
      $test_information .= substr($info,0,$project_emailmaxchars);
      }
    }

  // We have a test failing so we send emails
  $email = "";
  
  // Find the users
  $authors = pdo_query("SELECT author FROM updatefile WHERE buildid='$buildid'");
  while($authors_array = pdo_fetch_array($authors))
    {
    $author = $authors_array["author"];
    if($author=="Local User")
      {
      continue;
      }
    
    // Find a matching name in the database
    $query = "SELECT user.email FROM user,user2project WHERE user2project.projectid='$projectid' 
                               AND user2project.userid=user.id AND user2project.cvslogin='$author'";
    $user = pdo_query($query);

    if(pdo_num_rows($user)==0)
      {
      // Should send an email to the project admin to let him know that this user is not registered
      continue;
      }
    
    $user_array = pdo_fetch_array($user);  
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
 $user = pdo_query("SELECT user.email,user2project.emailtype FROM user,user2project WHERE user2project.projectid='$projectid' 
                       AND user2project.userid=user.id AND user2project.emailtype>1");
 while($user_array = pdo_fetch_array($user))
   {
   // If the user is already in the list we quit
   if(strpos($email,$user_array["email"]) !== false)
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
     
    // Send the extra information
    $messagePlainText .= $error_information;
    $messagePlainText .= $warning_information;
    $messagePlainText .= $test_information; 
     
     
    $messagePlainText .= "\n-CDash on ".$serverName."\n";
    
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
