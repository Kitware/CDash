<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("config.php");
include("common.php");

/** Generate the index table */
function generate_index_table()
{ 
  $noforcelogin = 1;
  include("config.php");
  include('login.php');

  $xml = '<?xml version="1.0"?><cdash>';
  $xml .= add_XML_value("title","CDash");
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
  $xml .= "<hostname>".$_SERVER['SERVER_NAME']."</hostname>";
  $xml .= "<date>".date("r")."</date>";
  
    // User
  if(isset($_SESSION['cdash']))
    {
    $xml .= "<user>";
    $userid = $_SESSION['cdash']['loginid'];
    $user = mysql_query("SELECT admin FROM user WHERE id='$userid'");
    $user_array = mysql_fetch_array($user);
    $xml .= add_XML_value("id",$userid);
    $xml .= add_XML_value("admin",$user_array["admin"]);
    $xml .= "</user>";
    }
        
  $projects = get_projects();
  $row=0;
  foreach($projects as $project)
    {
    $xml .= "<project>";
    $xml .= "<name>".$project['name']."</name>";
      
       if($project['last_build'] == "NA")
         {
            $xml .= "<lastbuild>NA</lastbuild>";
         }
       else
         {
      $xml .= "<lastbuild>".date("Y-m-d H:i:s T",strtotime($project['last_build']. "UTC"))."</lastbuild>";
      }
        $xml .= "<nbuilds>".$project['nbuilds']."</nbuilds>";
    $xml .= "<row>".$row."</row>";
    $xml .= "</project>";
    $row = !$row;
    }
  $xml .= "</cdash>";
  return $xml;
}

/** Generate the main dashboard XML */
function generate_main_dashboard_XML($projectid,$date)
{
  $noforcelogin = 1;
  include("config.php");
  include('login.php');
    
  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  if(!$db)
    {
    echo "Error connecting to CDash database server<br>\n";
    exit(0);
    }
  if(!mysql_select_db("$CDASH_DB_NAME",$db))
    {
    echo "Error selecting CDash database<br>\n";
    exit(0);
    }
  
  $project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
  if(mysql_num_rows($project)>0)
    {
    $project_array = mysql_fetch_array($project);
    $svnurl = $project_array["cvsurl"];
    $homeurl = $project_array["homeurl"];
    $bugurl = $project_array["bugtrackerurl"];   
    $projectname = $project_array["name"];  
    }
  else
    {
    $projectname = "NA";
    }
    
  $xml = '<?xml version="1.0"?><cdash>';
  $xml .= "<title>CDash - ".$projectname."</title>";
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";

  list ($previousdate, $currentstarttime, $nextdate) = get_dates($date,$project_array["nightlytime"]);
  $logoid = getLogoID($projectid);

  // Main dashboard section 
  $xml .=
  "<dashboard>
  <datetime>".date("l, F d Y H:i:s T",time())."</datetime>
  <date>".$date."</date>
  <unixtimestamp>".$currentstarttime."</unixtimestamp>
  <svn>".$svnurl."</svn>
  <bugtracker>".$bugurl."</bugtracker> 
  <home>".$homeurl."</home>
  <logoid>".$logoid."</logoid> 
  <projectid>".$projectid."</projectid> 
  <projectname>".$projectname."</projectname> 
  <previousdate>".$previousdate."</previousdate> 
  <nextdate>".$nextdate."</nextdate> 
  
  </dashboard>
  ";

  // updates
  $xml .= "<updates>";
  $xml .= "<url>viewChanges.php?project=" . $projectname . "&amp;date=" .gmdate("Ymd", $currentstarttime) . "</url>";
  $xml .= "<timestamp>" . date("Y-m-d H:i:s T", $currentstarttime).
          "</timestamp>";
  $xml .= "</updates>";


  // User
  if(isset($_SESSION['cdash']))
    {
    $xml .= "<user>";
    $userid = $_SESSION['cdash']['loginid'];
    $user2project = mysql_query("SELECT role FROM user2project WHERE userid='$userid' and projectid='$projectid'");
    $user2project_array = mysql_fetch_array($user2project);
    $user = mysql_query("SELECT admin FROM user WHERE id='$userid'");
    $user_array = mysql_fetch_array($user);
    $xml .= add_XML_value("id",$userid);
    $xml .= add_XML_value("admin",$user_array["admin"]);
    $xml .= "</user>";
    }
  
  $totalerrors = 0;
  $totalwarnings = 0;
  $totalconfigure = 0;
  $totalnotrun = 0;
  $totalfail= 0;
  $totalpass = 0; 
  $totalna = 0; 
      
  // Local function to add expected builds
  function add_expected_builds($groupid,$currentstarttime,$received_builds,$rowparity)
    {
    $currentUTCTime =  gmdate("YmdHis",$currentstarttime+3600*24);
    $xml = "";
    $build2grouprule = mysql_query("SELECT g.siteid,g.buildname,g.buildtype,s.name FROM build2grouprule AS g,site as s
                                    WHERE g.expected='1' AND g.groupid='$groupid' AND s.id=g.siteid
                                    AND g.starttime<$currentUTCTime AND (g.endtime>$currentUTCTime OR g.endtime='0000-00-00 00:00:00')
                                    ");
    while($build2grouprule_array = mysql_fetch_array($build2grouprule))
      {
      $key = $build2grouprule_array["name"]."_".$build2grouprule_array["buildname"];
      if(array_search($key,$received_builds) === FALSE) // add only if not found
        {      
        $xml .= "<build>";
              
               if($rowparity%2==0)
                    {
                    $xml .= add_XML_value("rowparity","trodd");
                    }
                else
                    {
                    $xml .= add_XML_value("rowparity","treven");
                    }
                $rowparity++;
                
        $xml .= add_XML_value("site",$build2grouprule_array["name"]);
        $xml .= add_XML_value("siteid",$build2grouprule_array["siteid"]);
        $xml .= add_XML_value("buildname",$build2grouprule_array["buildname"]);
        $xml .= add_XML_value("buildtype",$build2grouprule_array["buildtype"]);
                $xml .= add_XML_value("buildgroupid",$groupid);
        $xml .= add_XML_value("expected","1");

        $divname = $build2grouprule_array["siteid"]."_".$build2grouprule_array["buildname"]; 
        $divname = str_replace("+","_",$divname);
        $divname = str_replace(".","_",$divname);

        $xml .= add_XML_value("expecteddivname",$divname);
        $xml .= add_XML_value("submitdate","No Submission");
        $xml  .= "</build>";
        }
      }
    return $xml;
    }
    
  // Check the builds
 	$beginning_timestamp = $currentstarttime;
	$end_timestamp = $currentstarttime+3600*24;

  $beginning_UTCDate = gmdate("YmdHis",$beginning_timestamp);
  $end_UTCDate = gmdate("YmdHis",$end_timestamp);                                                      
        
  // We shoudln't get any builds for group that have been deleted (otherwise something is wrong)
  $builds = mysql_query("SELECT b.id,b.siteid,b.name,b.type,b.generator,b.starttime,b.endtime,b.submittime,g.name as groupname,gp.position,g.id as groupid 
                         FROM build AS b, build2group AS b2g,buildgroup AS g, buildgroupposition AS gp
                         WHERE b.starttime<$end_UTCDate AND b.starttime>$beginning_UTCDate
                         AND b.projectid='$projectid' AND b2g.buildid=b.id AND gp.buildgroupid=g.id AND b2g.groupid=g.id  
                         AND gp.starttime<$end_UTCDate AND (gp.endtime>$end_UTCDate OR gp.endtime='0000-00-00 00:00:00')
                         ORDER BY gp.position ASC,b.starttime DESC");
  echo mysql_error();
  
  // The SQL results are ordered by group so this should work
  // Group position have to be continuous
  $previousgroupposition = -1;
  
  $received_builds = array();
  $rowparity = 0;
  $dynanalysisrowparity = 0;
  $coveragerowparity = 0;
  
  while($build_array = mysql_fetch_array($builds))
    {
    $groupposition = $build_array["position"];
    if($previousgroupposition != $groupposition)
      {
      $groupname = $build_array["groupname"];  
      if($previousgroupposition != -1)
        {
        $xml .= add_expected_builds($groupid,$currentstarttime,$received_builds,$rowparity);
        $xml .= "</buildgroup>";
        }
      
      // We assume that the group position are continuous in N
      // So we fill in the gap if we are jumping
      $prevpos = $previousgroupposition+1;
      if($prevpos == 0)
        {
        $prevpos = 1;
        }
      for($i=$prevpos;$i<$groupposition;$i++)
        {
        $group = mysql_fetch_array(mysql_query("SELECT g.name,g.id FROM buildgroup AS g,buildgroupposition AS gp WHERE g.id=gp.buildgroupid 
                                                AND gp.position='$i' AND g.projectid='$projectid'
                                                AND gp.starttime<$end_UTCDate AND (gp.endtime>$end_UTCDate  OR gp.endtime='0000-00-00 00:00:00')
                                                "));
        $xml .= "<buildgroup>";
                $rowparity = 0;
        $xml .= add_XML_value("name",$group["name"]);
        $xml .= add_expected_builds($group["id"],$currentstarttime,$received_builds,$rowparity);
        $xml .= "</buildgroup>";  
        }  
             
      $xml .= "<buildgroup>";
            $rowparity = 0;
      $received_builds = array();
      $xml .= add_XML_value("name",$groupname);
      $previousgroupposition = $groupposition;
      }
    $groupid = $build_array["groupid"];
    $buildid = $build_array["id"];
    $configure = mysql_query("SELECT status FROM configure WHERE buildid='$buildid'");
    $nconfigure = mysql_num_rows($configure);
    $siteid = $build_array["siteid"];
    $site_array = mysql_fetch_array(mysql_query("SELECT name FROM site WHERE id='$siteid'"));
    
    // IF NO CONFIGURE WE DON'T DISPLAY
    //if($nconfigure > 0)
    {
    // Get the site name
    $xml .= "<build>";
        
        if($rowparity%2==0)
          {
            $xml .= add_XML_value("rowparity","trodd");
          }
        else
          {
            $xml .= add_XML_value("rowparity","treven");
            }
        $rowparity++;
        
    $xml .= add_XML_value("type",strtolower($build_array["type"]));
    $xml .= add_XML_value("site",$site_array["name"]);
    $xml .= add_XML_value("siteid",$siteid);
    $xml .= add_XML_value("buildname",$build_array["name"]);
    $xml .= add_XML_value("buildid",$build_array["id"]);
    $xml .= add_XML_value("generator",$build_array["generator"]);
    
    $received_builds[] = $site_array["name"]."_".$build_array["name"];
    
    $note = mysql_query("SELECT count(*) FROM note WHERE buildid='$buildid'");
    $note_array = mysql_fetch_row($note);
    if($note_array[0]>0)
      {
      $xml .= add_XML_value("note","1");
      }
      
    $update = mysql_query("SELECT count(*) FROM updatefile WHERE buildid='$buildid'");
    $update_array = mysql_fetch_row($update);
    $xml .= add_XML_value("update",$update_array[0]);
		
    $updatestatus = mysql_query("SELECT status FROM buildupdate WHERE buildid='$buildid'");
    $updatestatus_array = mysql_fetch_array($updatestatus);
    
    if(strlen($updatestatus_array["status"]) > 0 && $updatestatus_array["status"]!="0")
      {
      $xml .= add_XML_value("updateerrors",1);
      }
    else
      {
      $updateerrors = mysql_query("SELECT count(*) FROM updatefile WHERE buildid='$buildid' AND author='Local User' AND revision='-1'");
      $updateerrors_array = mysql_fetch_row($updateerrors);
      $xml .= add_XML_value("updateerrors",$updateerrors_array[0]);
      }
			
    $xml .= "<compilation>";
    
    // Find the number of errors and warnings
    $builderror = mysql_query("SELECT count(*) FROM builderror WHERE buildid='$buildid' AND type='0'");
    $builderror_array = mysql_fetch_array($builderror);
    $nerrors = $builderror_array[0];
    $totalerrors += $nerrors;
    $xml .= add_XML_value("error",$nerrors);
    $buildwarning = mysql_query("SELECT count(*) FROM builderror WHERE buildid='$buildid' AND type='1'");
    $buildwarning_array = mysql_fetch_array($buildwarning);
    $nwarnings = $buildwarning_array[0];
    $totalwarnings += $nwarnings;
    $xml .= add_XML_value("warning",$nwarnings);
    $diff = (strtotime($build_array["endtime"])-strtotime($build_array["starttime"]))/60;
    $xml .= "<time>".$diff."</time>";
    $xml .= "</compilation>";
    
    // Get the Configure options
    $configure = mysql_query("SELECT status FROM configure WHERE buildid='$buildid'");
    if($nconfigure)
      {
      $configure_array = mysql_fetch_array($configure);
      $xml .= add_XML_value("configure",$configure_array["status"]);
      $totalconfigure += $configure_array["status"];
      }
  
    // Get the tests
    $test = mysql_query("SELECT * FROM build2test WHERE buildid='$buildid'");
    if(mysql_num_rows($test)>0)
      {
      $test_array = mysql_fetch_array($test);
      $xml .= "<test>";
      // We might be able to do this in one request
      $nnotrun_array = mysql_fetch_array(mysql_query("SELECT count(*) FROM build2test WHERE buildid='$buildid' AND status='notrun'"));
      $nnotrun = $nnotrun_array[0];
      $nfail_array = mysql_fetch_array(mysql_query("SELECT count(*) FROM build2test WHERE buildid='$buildid' AND status='failed'"));
      $nfail = $nfail_array[0];
      $npass_array = mysql_fetch_array(mysql_query("SELECT count(*) FROM build2test WHERE buildid='$buildid' AND status='passed'"));
      $npass = $npass_array[0];
      $nna_array = mysql_fetch_array(mysql_query("SELECT count(*) FROM build2test WHERE buildid='$buildid' AND status='na'"));
      $nna = $nna_array[0];
      
      $time_array = mysql_fetch_array(mysql_query("SELECT SUM(time) FROM build2test WHERE buildid='$buildid'"));
      $time = $time_array[0];
      
      $totalnotrun += $nnotrun;
      $totalfail += $nfail;
      $totalpass += $npass;
      $totalna += $nna;
      
      $xml .= add_XML_value("notrun",$nnotrun);
      $xml .= add_XML_value("fail",$nfail);
      $xml .= add_XML_value("pass",$npass);
      $xml .= add_XML_value("na",$nna);
      $xml .= add_XML_value("time",round($time/60,1));
      $xml .= "</test>";
      }
        $starttimestamp = strtotime($build_array["starttime"]." UTC");
        $submittimestamp = strtotime($build_array["submittime"]." UTC");
    $xml .= add_XML_value("builddate",date("Y-m-d H:i:s T",$starttimestamp)); // use the default timezone
    /*if($starttimestamp > $submittimestamp)
          {
            $xml .= add_XML_value("clockskew","1");
          }
        else
          {
            $xml .= add_XML_value("clockskew","0");
          }*/
        $xml .= add_XML_value("submitdate",date("Y-m-d H:i:s T",$submittimestamp));// use the default timezone
   $xml .= "</build>";
    } // END IF CONFIGURE
    
    // Coverage
    $coverages = mysql_query("SELECT * FROM coveragesummary WHERE buildid='$buildid'");
    while($coverage_array = mysql_fetch_array($coverages))
      {
      $xml .= "<coverage>";
						if($coveragerowparity%2==0)
        {
        $xml .= add_XML_value("rowparity","trodd");
        }
      else
        {
        $xml .= add_XML_value("rowparity","treven");
        }
      $coveragerowparity++;
																
      $xml .= "  <site>".$site_array["name"]."</site>";
      $xml .= "  <buildname>".$build_array["name"]."</buildname>";
      $xml .= "  <buildid>".$build_array["id"]."</buildid>";
      
      @$percent = round($coverage_array["loctested"]/($coverage_array["loctested"]+$coverage_array["locuntested"])*100,2);
      
      $xml .= "  <percentage>".$percent."</percentage>";
      $xml .= "  <percentagegreen>".$project_array["coveragethreshold"]."</percentagegreen>";
      $xml .= "  <fail>".$coverage_array["locuntested"]."</fail>";
      $xml .= "  <pass>".$coverage_array["loctested"]."</pass>";
      
      $starttimestamp = strtotime($build_array["starttime"]." UTC");
      $submittimestamp = strtotime($build_array["submittime"]." UTC");
      $xml .= add_XML_value("date",date("Y-m-d H:i:s T",$starttimestamp)); // use the default timezone         
      $xml .= add_XML_value("submitdate",date("Y-m-d H:i:s T",$submittimestamp));// use the default timezone
      $xml .= "</coverage>";
      }  // end coverage
    
    // Dynamic Analysis
    $dynanalysis = mysql_query("SELECT checker FROM dynamicanalysis WHERE buildid='$buildid' LIMIT 1");
    while($dynanalysis_array = mysql_fetch_array($dynanalysis))
      {
      $xml .= "<dynamicanalysis>";
						if($dynanalysisrowparity%2==0)
        {
        $xml .= add_XML_value("rowparity","trodd");
        }
      else
        {
        $xml .= add_XML_value("rowparity","treven");
        }
      $dynanalysisrowparity++;
      $xml .= "  <site>".$site_array["name"]."</site>";
      $xml .= "  <buildname>".$build_array["name"]."</buildname>";
      $xml .= "  <buildid>".$build_array["id"]."</buildid>";
      
      $xml .= "  <checker>".$dynanalysis_array["checker"]."</checker>";
      $defect = mysql_query("SELECT count(*) FROM dynamicanalysisdefect AS dd,dynamicanalysis as d 
                                              WHERE d.buildid='$buildid' AND dd.dynamicanalysisid=d.id");
      $defectcount = mysql_fetch_array($defect);
      $xml .= "  <defectcount>".$defectcount[0]."</defectcount>";
      $starttimestamp = strtotime($build_array["starttime"]." UTC");
      $submittimestamp = strtotime($build_array["submittime"]." UTC");
      $xml .= add_XML_value("date",date("Y-m-d H:i:s T",$starttimestamp)); // use the default timezone
      $xml .= add_XML_value("submitdate",date("Y-m-d H:i:s T",$submittimestamp));// use the default timezone
      $xml .= "</dynamicanalysis>";
      }  // end coverage   
    } // end looping through builds
    
  if(mysql_num_rows($builds)>0)
    {
    $xml .= add_expected_builds($groupid,$currentstarttime,$received_builds,$rowparity);
    $xml .= "</buildgroup>";
    }
    
  // Fill in the rest of the info
  $prevpos = $previousgroupposition+1;
  if($prevpos == 0)
    {
    $prevpos = 1;
    }
    
  $groupposition_array = mysql_fetch_array(mysql_query("SELECT gp.position FROM buildgroupposition AS gp,buildgroup AS g 
                                                        WHERE g.projectid='$projectid' AND g.id=gp.buildgroupid 
                                                        AND gp.starttime<$end_UTCDate AND (gp.endtime>$end_UTCDate OR gp.endtime='0000-00-00 00:00:00')
                                                        ORDER BY gp.position DESC LIMIT 1"));
 
  $finalpos = $groupposition_array["position"];
  for($i=$prevpos;$i<=$finalpos;$i++)
    {
    $group = mysql_fetch_array(mysql_query("SELECT g.name,g.id FROM buildgroup AS g,buildgroupposition AS gp WHERE g.id=gp.buildgroupid 
                                                                                     AND gp.position='$i' AND g.projectid='$projectid'
                                                                                     AND gp.starttime<$end_UTCDate AND (gp.endtime>$end_UTCDate  OR gp.endtime='0000-00-00 00:00:00')"));
    $xml .= "<buildgroup>";  
    $xml .= add_XML_value("name",$group["name"]);
    $xml .= add_expected_builds($group["id"],$currentstarttime,$received_builds,$rowparity);
    $xml .= "</buildgroup>";  
    }
 
  $xml .= add_XML_value("totalConfigure",$totalconfigure);
  $xml .= add_XML_value("totalError",$totalerrors);
  $xml .= add_XML_value("totalWarning",$totalwarnings);
 
  $xml .= add_XML_value("totalNotRun",$totalnotrun);
  $xml .= add_XML_value("totalFail",$totalfail);
  $xml .= add_XML_value("totalPass",$totalpass); 
  $xml .= add_XML_value("totalNA",$totalna);
  
  $xml .= "</cdash>";

  return $xml;
} 

// Check if we can connect to the database
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
if(!$db)
  {
  // redirect to the install.php script
  echo "<script language=\"javascript\">window.location='install.php'</script>";
  return;
  }
if(mysql_select_db("$CDASH_DB_NAME",$db) === FALSE)
  {
  echo "<script language=\"javascript\">window.location='install.php'</script>";
  return;
  }

@$projectname = $_GET["project"];

// If we should not generate any XSL
if(isset($NoXSLGenerate))
  {
  return;
  }
    
function microtime_float()
  {
  list($usec, $sec) = explode(" ", microtime());
  return ((float)$usec + (float)$sec);
  }


if(!isset($projectname )) // if the project name is not set we display the table of projects
  {
  $xml = generate_index_table();
  // Now doing the xslt transition
  generate_XSLT($xml,"indextable");
  }
else
  {
    $start = microtime_float();
  $projectid = get_project_id($projectname);
  @$date = $_GET["date"];
  $xml = generate_main_dashboard_XML($projectid,$date);
  // Now doing the xslt transition
  generate_XSLT($xml,"index");
    $end = microtime_float();
    $t = round($end-$start,4);
    echo "<font size=\"1\">Generated in ".$t." seconds.</font>";
  }
?>
