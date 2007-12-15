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
function sendemail($vals,$projectid)
{
  include_once("common.php");
			
  // We send email at the end of the testing
  if($vals[1]["tag"] != "TESTING")
    {
				return;
				}

  $name = $xmlarray[0]["attributes"]["BUILDNAME"];
  $stamp = $xmlarray[0]["attributes"]["BUILDSTAMP"];

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
		
		add_log("Stage1 buildid=".$buildid,"sendemail");

		// Find the previous build
		$build = mysql_query("SELECT * FROM build WHERE id='$buildid'");
  $build_array = mysql_fetch_array($build);
		$buildtype = $build_array["type"];
		$siteid = $build_array["siteid"];
		$buildname = $build_array["buildname"];
		$starttime = $build_array["starttime"];
		
		$previousbuild = mysql_query("SELECT id FROM build WHERE siteid='$siteid' AND projectid='$projectid' AND type='$buildtype' 
		                                ORDER BY starttime DESC WHERE starttime<'$starttime' LIMIT 1");
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

    // If we have exactly the same number of test failing, errors and warnings has the previous build
				// we don't send any emails
				if($npreviousfailingtests==$nfailingtests
				   && $npreviousbuildwarnings==$nbuildwarnings
							&& $npreviousbuilderrors==$nbuilderrors
						) 
				  {
						return;
				  }
				}
		
		// We have a test failing so we send emails
		add_log("Stage2 buildid=".$buildid,"sendemail");

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
	
	 $email = "jomier@unc.edu"; // to test
	
	 // Some variables we need for the email
 	$project = mysql_query("SELECT name FROM project WHERE id='$projectid'");
		$project_array = mysql_fetch_array($project);
		
		$site = mysql_query("SELECT name FROM site WHERE id='$siteid'");
		$site_array = mysql_fetch_array($site);

		add_log("Stage3 buildid=".$buildid,"sendemail");

	 if($email != "")
		  {
    $title = "CDash [".$project_array["name"]."] - ".$site_array["name"];
				$title .= " - ".$buildname." - ".$buildtype." - ".date("Y-m-d H:i:s T",strtotime($starttime." UTC"));
		  
				$messagePlainText = "A submission to CDash for project ".$project_array["name"]." ";
				
				$i=0;
				if($nbuilderrors>0)
				  {
						$messagePlainText .= "has build errors";
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
				$messagePlainText .= "You have been identified as one of the authors who have checked in
																										changes that are part of this submission or you are listed in the
																										default contact list.  Details on the submission can be found at";

    $currentURI =  "http://".$_SERVER['SERVER_NAME'] .$_SERVER['REQUEST_URI']; 
				$currentURI = substr($currentURI,0,strrpos($currentURI,"/"));
    $messagePlainText .= $currentURI;
    $messagePlainText .= "/buildSummary.php?buildid=".$buildid;
    $messagePlainText .= "\n";
				
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
					
    $messagePlainText .= "\n- CDash on ".$_SERVER['SERVER_NAME']."\n";
				
				add_log("Stage4 buildid=".$buildid,"sendemail");
				
			 // Send the email
				$email = "jomier@unc.edu";
		  mail("$email", $title, $messagePlainText,
         "From: $From\nReply-To: $CDASH_EMAILADMIN\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" );
				} // end $email!=""
		
			add_log("End buildid=".$buildid,"sendemail");
}
?>
