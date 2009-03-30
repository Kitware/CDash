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
include("cdash/config.php");
require_once("cdash/pdo.php");
include("cdash/common.php");
require_once("models/project.php");
require_once("models/buildfailure.php");
require_once("filterdataFunctions.php");


/** Generate the index table */
function generate_index_table()
{ 
  $noforcelogin = 1;
  include("cdash/config.php");
  require_once("cdash/pdo.php");
  include('login.php');
  include('cdash/version.php');
  include_once('models/banner.php');

  $xml = '<?xml version="1.0"?'.'><cdash>';
  $xml .= add_XML_value("title","CDash");
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
  $xml .= "<version>".$CDASH_VERSION."</version>";

  $Banner = new Banner;
  $Banner->SetProjectId(0);
  $text = $Banner->GetText();
  if($text !== false)
    {
    $xml .= "<banner>";
    $xml .= add_XML_value("text",$text);
    $xml .= "</banner>";
    }

  $xml .= "<hostname>".$_SERVER['SERVER_NAME']."</hostname>";
  $xml .= "<date>".date("r")."</date>";

  // Check if the database is up to date
  if(!pdo_query("SELECT emailadministrator FROM project LIMIT 1"))
    {  
    $xml .= "<upgradewarning>1</upgradewarning>";
    }

 $xml .= "<dashboard>
 <googletracker>".$CDASH_DEFAULT_GOOGLE_ANALYTICS."</googletracker>";
 if(isset($CDASH_NO_REGISTRATION) && $CDASH_NO_REGISTRATION==1)
   {
   $xml .= add_XML_value("noregister","1");
   }
 $xml .= "</dashboard> ";
 
 // Show the size of the database
 if(!isset($CDASH_DB_TYPE) || ($CDASH_DB_TYPE == "mysql")) 
   {
   $rows = pdo_query("SHOW table STATUS");
    $dbsize = 0;
    while ($row = pdo_fetch_array($rows)) 
     {
    $dbsize += $row['Data_length'] + $row['Index_length']; 
    }
    
   $ext = "b";
   if($dbsize>1024)
     {
     $dbsize /= 1024;
     $ext = "Kb";
     }
   if($dbsize>1024)
     {
     $dbsize /= 1024;
     $ext = "Mb";
     }
   if($dbsize>1024)
     {
     $dbsize /= 1024;
     $ext = "Gb";
     }
   if($dbsize>1024)
     {
     $dbsize /= 1024;
     $ext = "Tb";
     } 
     
   $xml .= "<database>";
   $xml .= add_XML_value("size",round($dbsize,1).$ext);
   $xml .= "</database>";
   }
  else 
    {
    // no equivalent yet for other databases
    $xml .= "<database>";
    $xml .= add_XML_value("size","NA");
    $xml .= "</database>";
    }

 
  // User
  $userid = 0;
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
        
  $projects = get_projects($userid);
  $row=0;
  foreach($projects as $project)
    {
    $xml .= "<project>";
    $xml .= add_XML_value("name",$project['name']);
    $xml .= add_XML_value("description",$project['description']);
    if($project['last_build'] == "NA")
      {
      $xml .= "<lastbuild>NA</lastbuild>";
      }
    else
      {
      $xml .= "<lastbuild>".date(FMT_DATETIMESTD,strtotime($project['last_build']. "UTC"))."</lastbuild>";
      $xml .= "<lastbuilddate>".date(FMT_DATE,strtotime($project['last_build']. "UTC"))."</lastbuilddate>";
      }
    
    // Display the first build
    if($project['first_build'] == "NA")
      {
      $xml .= "<firstbuild>NA</firstbuild>";
      }
    else
      {
      $xml .= "<firstbuild>".date(FMT_DATETIMETZ,strtotime($project['first_build']. "UTC"))."</firstbuild>";
      }

    $xml .= "<nbuilds>".$project['nbuilds']."</nbuilds>";
    $xml .= "<row>".$row."</row>";
    $xml .= "</project>";
    if($row == 0)
      {
      $row = 1;
      }
    else
      {
      $row = 0;
      }
    }
  $xml .= "</cdash>";
  return $xml;
}


function add_default_buildgroup_sortlist($groupname)
{
  $xml = '';

  // Sort settings should probably be defined by the user as well on the users page
  //
  switch($groupname)
    {
    case 'Continuous':
    case 'Experimental':
      $xml .= add_XML_value("sortlist", "{sortlist: [[14,1]]}"); //build time
      break;
    case 'Nightly':
      $xml .= add_XML_value("sortlist", "{sortlist: [[1,0]]}"); //build name
      break;
    }

  return $xml;
}


function should_collapse_rows($row1, $row2)
{
  if(
       ($row1['name'] == $row2['name'])
    && ($row1['siteid'] == $row2['siteid'])
    && ($row1['position'] == $row2['position'])
    && ($row1['stamp'] == $row2['stamp'])
    )
    {
    return true;
    }

  return false;
}


function get_multiple_builds_hyperlink($build_row)
{
  //
  // This function is closely related to the javascript function
  // filters_create_hyperlink in cdashFilters.js.
  //
  // If you are making changes to this function, look over there and see if
  // similar changes need to be made in javascript...
  //
  // javascript window.location and php $_SERVER['REQUEST_URI'] are equivalent,
  // but the window.location property includes the 'http://server' whereas the
  // $_SERVER['REQUEST_URI'] does not...
  //

  $baseurl = $_SERVER['REQUEST_URI'];

  // If the current REQUEST_URI already has a &filtercount=... (and other
  // filter stuff), trim it off and just use part that comes before that:
  //
  $idx = strpos($baseurl, "&filtercount=");
  if ($idx !== FALSE)
  {
    $baseurl = substr($baseurl, 0, $idx);
  }

  // ...because we are building our own filters URL here:
  //
  return $baseurl .
    '&filtercount=2&showfilters=1&filtercombine=and' .
    '&field1=buildname/string&compare1=61&value1=' . $build_row['name'] .
    '&field2=site/string&compare2=61&value2=' . $build_row['sitename'] .
    '&collapse=0';
}


/** Generate the main dashboard XML */
function generate_main_dashboard_XML($projectid,$date)
{
  $start = microtime_float();
  $noforcelogin = 1;
  include_once("cdash/config.php");
  require_once("cdash/pdo.php");
  include('login.php');
  include('cdash/version.php');
  include_once("models/banner.php");
  include_once("models/subproject.php");

  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  if(!$db)
    {
    echo "Error connecting to CDash database server<br>\n";
    exit(0);
    }
  if(!pdo_select_db("$CDASH_DB_NAME",$db))
    {
    echo "Error selecting CDash database<br>\n";
    exit(0);
    }

  $project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
  if(pdo_num_rows($project)>0)
    {
    $project_array = pdo_fetch_array($project);
    $svnurl = make_cdash_url(htmlentities($project_array["cvsurl"]));
    $homeurl = make_cdash_url(htmlentities($project_array["homeurl"]));
    $bugurl = make_cdash_url(htmlentities($project_array["bugtrackerurl"]));
    $googletracker = htmlentities($project_array["googletracker"]);  
    $docurl = make_cdash_url(htmlentities($project_array["documentationurl"]));
    $projectpublic =  $project_array["public"]; 
    $projectname = $project_array["name"];
    }
  else
    {
    $projectname = "NA";
    }

  checkUserPolicy(@$_SESSION['cdash']['loginid'],$project_array["id"]);
    
  $xml = '<?xml version="1.0"?'.'><cdash>';
  $xml .= "<title>CDash - ".$projectname."</title>";
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
  $xml .= "<version>".$CDASH_VERSION."</version>";

  $Banner = new Banner;
  $Banner->SetProjectId(0);
  $text = $Banner->GetText();
  if($text !== false)
    {
    $xml .= "<banner>";
    $xml .= add_XML_value("text",$text);
    $xml .= "</banner>";
    }

  $Banner->SetProjectId($projectid);
  $text = $Banner->GetText();
  if($text !== false)
    {
    $xml .= "<banner>";
    $xml .= add_XML_value("text",$text);
    $xml .= "</banner>";
    }

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
  <googletracker>".$googletracker."</googletracker> 
  <documentation>".$docurl."</documentation>
  <home>".$homeurl."</home>
  <logoid>".$logoid."</logoid> 
  <projectid>".$projectid."</projectid> 
  <projectname>".$projectname."</projectname> 
  <previousdate>".$previousdate."</previousdate> 
  <projectpublic>".$projectpublic."</projectpublic> 
  <nextdate>".$nextdate."</nextdate>";
  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/models/proProject.php"))
    {
    include_once("local/models/proProject.php");
    $pro= new proProject($projectid); 
    if($pro->IsActif()!=false)
      {
      if(strtotime($pro->GetEnd()<strtotime(date("r"))))
        {
        $pro->SetStatus(0, 0, 0);
        }
      $xml.="<prostatus>".$pro->GetStatus()."</prostatus>";
      }
    }
 
  if($currentstarttime>time()) 
   {
   $xml .= "<future>1</future>";
    }
  else
  {
  $xml .= "<future>0</future>";
  }
  $xml .= "</dashboard>";

  // Menu definition
  $xml .= "<menu>";
  if(!isset($date) || strlen($date)<8 || date(FMT_DATE, $currentstarttime)==date(FMT_DATE))
    {
    $xml .= add_XML_value("nonext","1");
    }
  $xml .= "</menu>";

  // Check the builds
  $beginning_timestamp = $currentstarttime;
  $end_timestamp = $currentstarttime+3600*24;

  $beginning_UTCDate = gmdate(FMT_DATETIME,$beginning_timestamp);
  $end_UTCDate = gmdate(FMT_DATETIME,$end_timestamp);   
  
  // Add the extra url if necessary
  if(isset($_GET["display"]) && $_GET["display"]=="project")
    {
    $xml .= add_XML_value("extraurl","&display=project");
    }
    
  // If we have a subproject
  if(isset($_GET["subproject"]))
    {
    // Add an extra URL argument for the menu
    $xml .= add_XML_value("extraurl","&subproject=".$_GET["subproject"]);
    $xml .= add_XML_value("subprojectname",$_GET["subproject"]);
    
    $xml .= "<subproject>";
    
    $SubProject = new SubProject();
    $SubProject->Name = $_GET["subproject"];
    $SubProject->ProjectId = $projectid;
    $subprojectid = $SubProject->GetIdFromName();
    
    $xml .= add_XML_value("name",$SubProject->Name );
     
    $rowparity = 0;
    $dependencies = $SubProject->GetDependencies();
    foreach($dependencies as $dependency)
      {
      $xml .= "<dependency>";
      $DependProject = new SubProject();
      $DependProject->Id = $dependency;
      $xml .= add_XML_value("rowparity",$rowparity);
      $xml .= add_XML_value("name",$DependProject->GetName());
      $xml .= add_XML_value("nbuilderror",$DependProject->GetNumberOfErrorBuilds($beginning_UTCDate,$end_UTCDate));
      $xml .= add_XML_value("nbuildwarning",$DependProject->GetNumberOfWarningBuilds($beginning_UTCDate,$end_UTCDate)); 
      $xml .= add_XML_value("nbuildpass",$DependProject->GetNumberOfPassingBuilds($beginning_UTCDate,$end_UTCDate));
      $xml .= add_XML_value("nconfigureerror",$DependProject->GetNumberOfErrorConfigures($beginning_UTCDate,$end_UTCDate));
      $xml .= add_XML_value("nconfigurewarning",$DependProject->GetNumberOfWarningConfigures($beginning_UTCDate,$end_UTCDate));
      $xml .= add_XML_value("nconfigurepass",$DependProject->GetNumberOfPassingConfigures($beginning_UTCDate,$end_UTCDate));
      $xml .= add_XML_value("ntestpass",$DependProject->GetNumberOfPassingTests($beginning_UTCDate,$end_UTCDate));
      $xml .= add_XML_value("ntestfail",$DependProject->GetNumberOfFailingTests($beginning_UTCDate,$end_UTCDate));
      $xml .= add_XML_value("ntestnotrun",$DependProject->GetNumberOfNotRunTests($beginning_UTCDate,$end_UTCDate));
      if(strlen($DependProject->GetLastSubmission()) == 0)
        {
        $xml .= add_XML_value("lastsubmission","NA");
        }
      else
        {  
        $xml .= add_XML_value("lastsubmission",$DependProject->GetLastSubmission());
        }
      $rowparity = ($rowparity==1) ? 0:1;  
      $xml .= "</dependency>";
      }
    $xml .= "</subproject>";
    } // end isset(subproject)

  // updates
  $xml .= "<updates>";
  
  $gmdate = gmdate(FMT_DATE, $currentstarttime);
  $xml .= "<url>viewChanges.php?project=" . $projectname . "&amp;date=" .$gmdate. "</url>";
  
  $dailyupdate = pdo_query("SELECT id FROM dailyupdate 
                            WHERE dailyupdate.date='$gmdate' and projectid='$projectid'");
  
  if(pdo_num_rows($dailyupdate)>0)
    {
    $dailupdate_array = pdo_fetch_array($dailyupdate);
    $dailyupdateid = $dailupdate_array["id"];
    $dailyupdatefile = pdo_query("SELECT count(*) FROM dailyupdatefile
                                    WHERE dailyupdateid='$dailyupdateid'");
    $dailyupdatefile_array = pdo_fetch_array($dailyupdatefile);
    $nchanges = $dailyupdatefile_array[0]; 
    
    $dailyupdateauthors = pdo_query("SELECT author FROM dailyupdatefile
                                       WHERE dailyupdateid='$dailyupdateid'
                                       GROUP BY author");
    $nauthors = pdo_num_rows($dailyupdateauthors);   
    $xml .= "<nchanges>".$nchanges."</nchanges>";
    $xml .= "<nauthors>".$nauthors."</nauthors>";
    }
  else
   {
   $xml .= "<nchanges>-1</nchanges>";
   }    
  $xml .= "<timestamp>" . date(FMT_DATETIMETZ, $currentstarttime)."</timestamp>";
  $xml .= "</updates>";

  // User
  if(isset($_SESSION['cdash']))
    {
    $xml .= "<user>";
    $userid = $_SESSION['cdash']['loginid'];
    $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' and projectid='$projectid'");
    $user2project_array = pdo_fetch_array($user2project);
    $user = pdo_query("SELECT admin FROM ".qid("user")."  WHERE id='$userid'");
    $user_array = pdo_fetch_array($user);
    $xml .= add_XML_value("id",$userid);
    $isadmin=0;
    if($user2project_array["role"]>1 || $user_array["admin"])
      {
      $isadmin=1;
       }
    $xml .= add_XML_value("admin",$isadmin);
    $xml .= "</user>";
    }

  // Filters:
  //
  $filterdata = get_filterdata_from_request();
  $filter_sql = $filterdata['sql'];
  $xml .= $filterdata['xml'];

  // Statistics:
  //
  $totalUpdatedFiles = 0;
  $totalUpdateError = 0;
  $totalUpdateWarning = 0;
  $totalUpdateDuration = 0;
  $totalConfigureError = 0;
  $totalConfigureWarning = 0;
  $totalConfigureDuration = 0;
  $totalerrors = 0;
  $totalwarnings = 0;
  $totalBuildDuration = 0;
  $totalnotrun = 0;
  $totalfail= 0;
  $totalpass = 0;  
  $totalTestsDuration = 0;

  // Local function to add expected builds
  function add_expected_builds($groupid,$currentstarttime,$received_builds,$rowparity)
    {
    $currentUTCTime =  gmdate(FMT_DATETIME,$currentstarttime+3600*24);
    $xml = "";
    $build2grouprule = pdo_query("SELECT g.siteid,g.buildname,g.buildtype,s.name FROM build2grouprule AS g,site as s
                                  WHERE g.expected='1' AND g.groupid='$groupid' AND s.id=g.siteid
                                  AND g.starttime<'$currentUTCTime' AND (g.endtime>'$currentUTCTime' OR g.endtime='1980-01-01 00:00:00')
                                  ");                      
    while($build2grouprule_array = pdo_fetch_array($build2grouprule))
      {
      $key = $build2grouprule_array["name"]."_".$build2grouprule_array["buildname"];
      if(array_search($key,$received_builds) === FALSE) // add only if not found
        {      
        $xml .= "<build>";                
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
 
  // add a request for the subproject
  $subprojectsql = "";
  $subprojecttablesql = "";
  if(isset($_GET["subproject"]))
    {
    $subprojecttablesql = ",subproject2build AS sp2b";
    $subprojectsql = " AND sp2b.buildid=b.id AND sp2b.subprojectid=".$subprojectid;
    }   
                                                   
  
  // Use this as the default date clause, but if $filterdata has a date clause,
  // then cancel this one out:
  //
  $date_clause = "b.starttime<'$end_UTCDate' AND b.starttime>='$beginning_UTCDate' AND ";

  if($filterdata['hasdateclause'])
    {
    $date_clause = '';
    }
    
  $build_rows = array();

  $sql =  "SELECT b.id,b.siteid,b.stamp,b.name,b.type,b.generator,b.starttime,b.endtime,b.submittime,g.name as groupname,gp.position,g.id as groupid
                         FROM build AS b, build2group AS b2g,buildgroup AS g, buildgroupposition AS gp ".$subprojecttablesql."
                         WHERE ".$date_clause."
                         b.projectid='$projectid' AND b2g.buildid=b.id AND gp.buildgroupid=g.id AND b2g.groupid=g.id  
                         AND gp.starttime<'$end_UTCDate' AND (gp.endtime>'$end_UTCDate' OR gp.endtime='1980-01-01 00:00:00')
                         ".$subprojectsql." ".$filter_sql." ORDER BY gp.position ASC,b.name ASC ";
                         
  // We shoudln't get any builds for group that have been deleted (otherwise something is wrong)
  $builds = pdo_query($sql);
  echo pdo_error();
  
  // The SQL results are ordered by group so this should work
  // Group position have to be continuous
  $previousgroupposition = -1;
  
  $received_builds = array();
  $rowparity = 0;
  $dynanalysisrowparity = 0;
  $coveragerowparity = 0;

  // Find the last position of the group
  $groupposition_array = pdo_fetch_array(pdo_query("SELECT gp.position FROM buildgroupposition AS gp,buildgroup AS g 
                                                        WHERE g.projectid='$projectid' AND g.id=gp.buildgroupid 
                                                        AND gp.starttime<'$end_UTCDate' AND (gp.endtime>'$end_UTCDate' OR gp.endtime='1980-01-01 00:00:00')
                                                        ORDER BY gp.position DESC LIMIT 1"));
                                                        
  $lastGroupPosition = $groupposition_array["position"];


  // Fetch all the rows of builds into a php array.
  // Compute additional fields for each row that we'll need to generate the xml.
  //
  $build_rows = array();
  while($build_row = pdo_fetch_array($builds))
    {
    // Fields that come from the initial query:
    //  id
    //  stamp
    //  name
    //  siteid
    //  type
    //  generator
    //  starttime
    //  endtime
    //  submittime
    //  groupname
    //  position
    //  groupid
    //
    // Fields that we add by doing further queries based on buildid:
    //  buildids (array of buildids for summary rows)
    //  sitename
    //  countbuildnotes (added by users)
    //  countnotes (sent with ctest -A)
    //  labels
    //  countupdatefiles
    //  updatestatus
    //  updateduration
    //  countupdateerrors
    //  countupdatewarnings
    //  buildduration
    //  countbuilderrors
    //  countbuildwarnings
    //  countbuilderrordiff
    //  countbuildwarningdiff
    //  configurestatus
    //  hasconfigurestatus
    //  countconfigureerrors
    //  countconfigurewarnings
    //  countconfigurewarningdiff
    //  configureduration
    //  test
    //  counttestsnotrun
    //  counttestsnotrundiff
    //  counttestsfailed
    //  counttestsfaileddiff
    //  counttestspassed
    //  counttestspasseddiff
    //  countteststimestatusfailed
    //  countteststimestatusfaileddiff
    //  testsduration
    //

    $buildid = $build_row['id'];
    $groupid = $build_row['groupid'];
    $siteid = $build_row['siteid'];

    $build_row['buildids'][] = $buildid;

    $site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
    $build_row['sitename'] = $site_array['name'];

    $buildnote = pdo_query("SELECT count(*) FROM buildnote WHERE buildid='$buildid'");
    $buildnote_array = pdo_fetch_row($buildnote);
    $build_row['countbuildnotes'] = $buildnote_array[0];

    $note = pdo_query("SELECT count(*) FROM build2note WHERE buildid='$buildid'");
    $note_array = pdo_fetch_row($note);
    $build_row['countnotes'] = $note_array[0];

    $build_row['labels'] = array();
    $label_rows = pdo_all_rows_query(
      "SELECT text FROM label, label2build ".
      "WHERE label2build.buildid='$buildid' ".
      "AND label2build.labelid=label.id");
    foreach($label_rows as $label_row)
      {
      $build_row['labels'][] = $label_row['text'];
      }

    $update = pdo_query("SELECT count(*) FROM updatefile WHERE buildid='$buildid'");
    $update_array = pdo_fetch_row($update);
    $build_row['countupdatefiles'] = $update_array[0];

    $updatestatus = pdo_query("SELECT status,starttime,endtime FROM buildupdate WHERE buildid='$buildid'");
    $updatestatus_array = pdo_fetch_array($updatestatus);
    $build_row['updatestatus'] = $updatestatus_array;

    $build_row['updateduration'] = round((strtotime($updatestatus_array["endtime"])-strtotime($updatestatus_array["starttime"]))/60,1);

    if(strlen($updatestatus_array["status"]) > 0 &&
       $updatestatus_array["status"]!="0")
      {
      $build_row['countupdateerrors'] = 1;
      }
    else
      {
      $build_row['countupdateerrors'] = 0;
      }

    $updatewarnings = pdo_query("SELECT count(*) FROM updatefile WHERE buildid='$buildid' AND author='Local User' AND revision='-1'");
    $updatewarnings_array = pdo_fetch_row($updatewarnings);
    $build_row['countupdatewarnings'] = $updatewarnings_array[0];

    $build_row['buildduration'] = round((strtotime($build_row["endtime"])-strtotime($build_row["starttime"]))/60,1);

    $builderror = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$buildid' AND type='0'");
    $builderror_array = pdo_fetch_array($builderror);
    $nerrors = $builderror_array[0];
    $builderror = pdo_query("SELECT count(*) FROM buildfailure WHERE buildid='$buildid' AND type='0'");
    $builderror_array = pdo_fetch_array($builderror);
    $nerrors += $builderror_array[0];
    $build_row['countbuilderrors'] = $nerrors;

    $buildwarning = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$buildid' AND type='1'");
    $buildwarning_array = pdo_fetch_array($buildwarning);
    $nwarnings = $buildwarning_array[0];
    $buildwarning = pdo_query("SELECT count(*) FROM buildfailure WHERE buildid='$buildid' AND type='1'");
    $buildwarning_array = pdo_fetch_array($buildwarning);
    $nwarnings += $buildwarning_array[0];
    $build_row['countbuildwarnings'] = $nwarnings;

    $diff = 0;
    $builderrordiff = pdo_query("SELECT difference FROM builderrordiff WHERE buildid='$buildid' AND type='0'");
    if(pdo_num_rows($builderrordiff)>0)
      {
      $builderrordiff_array = pdo_fetch_array($builderrordiff);
      $diff = $builderrordiff_array["difference"];
      }
    $build_row['countbuilderrordiff'] = $diff;

    $diff = 0;
    $buildwarningdiff = pdo_query("SELECT difference FROM builderrordiff WHERE buildid='$buildid' AND type='1'");
    if(pdo_num_rows($buildwarningdiff)>0)
      {
      $buildwarningdiff_array = pdo_fetch_array($buildwarningdiff);
      $diff = $buildwarningdiff_array["difference"];
      }
    $build_row['countbuildwarningdiff'] = $diff;

    $configure = pdo_query("SELECT status,starttime,endtime FROM configure WHERE buildid='$buildid'");
    $configure_array = pdo_fetch_array($configure);
    $build_row['configurestatus'] = $configure_array;

    $build_row['hasconfigurestatus'] = 0;
    $build_row['countconfigureerrors'] = 0;
    $build_row['countconfigurewarnings'] = 0;
    $build_row['countconfigurewarningdiff'] = 0;
    $build_row['configureduration'] = 0;

    if(!empty($configure_array))
      {
      $build_row['hasconfigurestatus'] = 1;

      $build_row['countconfigureerrors'] = $configure_array['status'];

      $configurewarnings = pdo_query("SELECT count(*) FROM configureerror WHERE buildid='$buildid' AND type='1'");
      $configurewarnings_array = pdo_fetch_array($configurewarnings);
      $build_row['countconfigurewarnings'] = $configurewarnings_array[0];

      $configurewarning = pdo_query("SELECT difference FROM configureerrordiff WHERE buildid='$buildid' AND type='1'");
      if(pdo_num_rows($configurewarning)>0)
        {
        $configurewarning_array = pdo_fetch_array($configurewarning);
        $build_row['countconfigurewarningdiff'] = $configurewarning_array["difference"];
        }

      $build_row['configureduration'] = round((strtotime($configure_array["endtime"])-strtotime($configure_array["starttime"]))/60, 1);
      }

    $test = pdo_query("SELECT * FROM build2test WHERE buildid='$buildid'");
    $test_array = pdo_fetch_array($test);
    $build_row['test'] = $test_array;

    $build_row['hastest'] = 0;
    $build_row['counttestsnotrun'] = 0;
    $build_row['counttestsnotrundiff'] = 0;
    $build_row['counttestsfailed'] = 0;
    $build_row['counttestsfaileddiff'] = 0;
    $build_row['counttestspassed'] = 0;
    $build_row['counttestspasseddiff'] = 0;
    $build_row['countteststimestatusfailed'] = 0;
    $build_row['countteststimestatusfaileddiff'] = 0;
    if(!empty($test_array))
      {
      $build_row['hastest'] = 1;

      $nnotrun_array = pdo_fetch_array(pdo_query("SELECT count(*) FROM build2test WHERE buildid='$buildid' AND status='notrun'"));
      $build_row['counttestsnotrun'] = $nnotrun_array[0];

      $notrundiff = pdo_query("SELECT difference FROM testdiff WHERE buildid='$buildid' AND type='0'");
      if(pdo_num_rows($notrundiff)>0)
        {
        $nnotrundiff_array = pdo_fetch_array($notrundiff);
        $build_row['counttestsnotrundiff'] = $nnotrundiff_array['difference'];
        }

      $sql = "SELECT count(*) FROM build2test WHERE buildid='$buildid' AND status='failed'";
      $nfail_array = pdo_fetch_array(pdo_query($sql));
      $build_row['counttestsfailed'] = $nfail_array[0];

      $faildiff = pdo_query("SELECT difference FROM testdiff WHERE buildid='$buildid' AND type='1'");
      if(pdo_num_rows($faildiff)>0)
        {
        $faildiff_array = pdo_fetch_array($faildiff);
        $build_row['counttestsfaileddiff'] = $faildiff_array["difference"];
        }

      $sql = "SELECT count(*) FROM build2test WHERE buildid='$buildid' AND status='passed'";
      $npass_array = pdo_fetch_array(pdo_query($sql));
      $build_row['counttestspassed'] = $npass_array[0];

      $passdiff = pdo_query("SELECT difference FROM testdiff WHERE buildid='$buildid' AND type='2'");
      if(pdo_num_rows($passdiff)>0)
        {
        $passdiff_array = pdo_fetch_array($passdiff);
        $build_row['counttestspasseddiff'] = $passdiff_array["difference"];
        }

      if($project_array["showtesttime"] == 1)
        {
        $testtimemaxstatus = $project_array["testtimemaxstatus"];
        $sql = "SELECT count(*) FROM build2test WHERE buildid='$buildid' AND timestatus>='$testtimemaxstatus'";
        $ntimestatus_array = pdo_fetch_array(pdo_query($sql));
        $build_row['countteststimestatusfailed'] = $ntimestatus_array[0];

        $timediff = pdo_query("SELECT difference FROM testdiff WHERE buildid='$buildid' AND type='3'");
        if(pdo_num_rows($timediff)>0)
          {
          $timediff_array = pdo_fetch_array($timediff);
          $build_row['countteststimestatusfaileddiff'] = $timediff_array["difference"];
          }
        }
      }

    $time_array = pdo_fetch_array(pdo_query("SELECT SUM(time) FROM build2test WHERE buildid='$buildid'"));
    $build_row['testsduration'] = round($time_array[0]/60,1);

    //  Save the row in '$build_rows'
    //
    $build_rows[] = $build_row;
    }


  // By default, do not collapse rows. But if the project has subprojects, then
  // collapse rows with common build name, site and group. (But different
  // subprojects/labels...)
  //
  // Look for a '&collapse=0' or '&collapse=1' to override the default.
  //
  $collapse = 0;

  global $Project;
    // warning: tightly coupled to global $Project defined at the bottom of
    // this script -- it would be better to refactor and pass $Project in to
    // this function as a parameter...

  if ($Project->GetNumberOfSubProjects()>0)
    {
    $collapse = 1;
    }
  if (array_key_exists('collapse', $_REQUEST))
    {
    $collapse = $_REQUEST['collapse'];
    }


  // This loop assumes that only build rows that are originally next to each
  // other in $build_rows are candidates for collapsing...
  //
  if($collapse)
    {
    $build_rows_collapsed = array();

    foreach($build_rows as $build_row)
      {
      $idx = count($build_rows_collapsed) - 1;

      if (($idx >= 0) &&
        should_collapse_rows($build_rows_collapsed[$idx], $build_row))
        {
        // Append to existing last row, $build_rows_collapsed[$idx]:
        //

    //  id
    //  name
    //  siteid
    //  type
    //  generator
        if ($build_row['starttime'] < $build_rows_collapsed[$idx]['starttime'])
          {
          $build_rows_collapsed[$idx]['starttime'] = $build_row['starttime'];
          }
    //  endtime
    //  submittime
    //  groupname
    //  position
    //  groupid
    //  buildids (array of buildids for summary rows)
        $build_rows_collapsed[$idx]['buildids'][] = $build_row['id'];
    //  sitename
        $build_rows_collapsed[$idx]['countbuildnotes'] += $build_row['countbuildnotes'];
        $build_rows_collapsed[$idx]['countnotes'] += $build_row['countnotes'];
        $build_rows_collapsed[$idx]['labels'] = array_merge($build_rows_collapsed[$idx]['labels'], $build_row['labels']);

        $build_rows_collapsed[$idx]['countupdatefiles'] += $build_row['countupdatefiles'];
    //  updatestatus
        $build_rows_collapsed[$idx]['updateduration'] += $build_row['updateduration'];
        $build_rows_collapsed[$idx]['countupdateerrors'] += $build_row['countupdateerrors'];
        $build_rows_collapsed[$idx]['countupdatewarnings'] += $build_row['countupdatewarnings'];

        $build_rows_collapsed[$idx]['buildduration'] += $build_row['buildduration'];
        $build_rows_collapsed[$idx]['countbuilderrors'] += $build_row['countbuilderrors'];
        $build_rows_collapsed[$idx]['countbuildwarnings'] += $build_row['countbuildwarnings'];
        $build_rows_collapsed[$idx]['countbuilderrordiff'] += $build_row['countbuilderrordiff'];
        $build_rows_collapsed[$idx]['countbuildwarningdiff'] += $build_row['countbuildwarningdiff'];

        $build_rows_collapsed[$idx]['hasconfigurestatus'] += $build_row['hasconfigurestatus'];
        $build_rows_collapsed[$idx]['countconfigureerrors'] += $build_row['countconfigureerrors'];
        $build_rows_collapsed[$idx]['countconfigurewarnings'] += $build_row['countconfigurewarnings'];
        $build_rows_collapsed[$idx]['countconfigurewarningdiff'] += $build_row['countconfigurewarningdiff'];
        $build_rows_collapsed[$idx]['configureduration'] += $build_row['configureduration'];

        //  test
        $build_rows_collapsed[$idx]['hastest'] += $build_row['hastest'];
        $build_rows_collapsed[$idx]['counttestsnotrun'] += $build_row['counttestsnotrun'];
        $build_rows_collapsed[$idx]['counttestsnotrundiff'] += $build_row['counttestsnotrundiff'];
        $build_rows_collapsed[$idx]['counttestsfailed'] += $build_row['counttestsfailed'];
        $build_rows_collapsed[$idx]['counttestsfaileddiff'] += $build_row['counttestsfaileddiff'];
        $build_rows_collapsed[$idx]['counttestspassed'] += $build_row['counttestspassed'];
        $build_rows_collapsed[$idx]['counttestspasseddiff'] += $build_row['counttestspasseddiff'];
        $build_rows_collapsed[$idx]['countteststimestatusfailed'] += $build_row['countteststimestatusfailed'];
        $build_rows_collapsed[$idx]['countteststimestatusfaileddiff'] += $build_row['countteststimestatusfaileddiff'];
        $build_rows_collapsed[$idx]['testsduration'] += $build_row['testsduration'];
        }
      else
        {
        // Add new row:
        //
        $build_rows_collapsed[] = $build_row;
        }
      }

    $build_rows = $build_rows_collapsed;
    }


  // Generate the xml from the (possibly collapsed) rows of builds:
  //
  foreach($build_rows as $build_array)
    {
    $groupposition = $build_array["position"];

    if($previousgroupposition != $groupposition)
      {
      $groupname = $build_array["groupname"];
      if($previousgroupposition != -1)
        {
        if (!$filter_sql)
          {
          $xml .= add_expected_builds($groupid,$currentstarttime,$received_builds,$rowparity);
          }

        if($previousgroupposition == $lastGroupPosition)
          {
          $xml .= "<last>1</last>";
          }
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
        $group = pdo_fetch_array(pdo_query("SELECT g.name,g.id FROM buildgroup AS g,buildgroupposition AS gp WHERE g.id=gp.buildgroupid 
                                                AND gp.position='$i' AND g.projectid='$projectid'
                                                AND gp.starttime<'$end_UTCDate' AND (gp.endtime>'$end_UTCDate'  OR gp.endtime='1980-01-01 00:00:00')
                                                "));
        $xml .= "<buildgroup>";
        $xml .= add_default_buildgroup_sortlist($group['name']);
        $rowparity = 0;
        $xml .= add_XML_value("name",$group["name"]);
        $xml .= add_XML_value("linkname",str_replace(" ","_",$group["name"]));
        $xml .= add_XML_value("id",$group["id"]);
        if (!$filter_sql)
          {
          $xml .= add_expected_builds($group["id"],$currentstarttime,$received_builds,$rowparity);
          }
        if($previousgroupposition == $lastGroupPosition)
          {
          $xml .= "<last>1</last>";
          }
        $xml .= "</buildgroup>";  
        }  


      $xml .= "<buildgroup>";
      $xml .= add_default_buildgroup_sortlist($groupname);
      $xml .= add_XML_value("name",$groupname);
      $xml .= add_XML_value("linkname",str_replace(" ","_",$groupname));
      $xml .= add_XML_value("id",$build_array["groupid"]);

      $rowparity = 0;
      $received_builds = array();

      $previousgroupposition = $groupposition;
      }


    $xml .= "<build>";

    $received_builds[] = $build_array["sitename"]."_".$build_array["name"];

    $buildid = $build_array["id"];
    $groupid = $build_array["groupid"];
    $siteid = $build_array["siteid"];

    if($rowparity%2==0)
      {
      $xml .= add_XML_value("rowparity","trodd");
      }
    else
      {
      $xml .= add_XML_value("rowparity","treven");
      }
    $rowparity++;

    $countbuildids = count($build_array['buildids']);
    $xml .= add_XML_value("countbuildids", $countbuildids);

    if ($countbuildids>1)
      {
      $xml .= add_XML_value("multiplebuildshyperlink", get_multiple_builds_hyperlink($build_array));
      }

    $xml .= add_XML_value("type", strtolower($build_array["type"]));
    $xml .= add_XML_value("site", $build_array["sitename"]);
    $xml .= add_XML_value("siteid", $siteid);
    //$xml .= add_XML_value("buildname", $build_array["name"] . ' ' . $build_array['stamp']);
    $xml .= add_XML_value("buildname", $build_array["name"]);
    $xml .= add_XML_value("buildid", $build_array["id"]);
    $xml .= add_XML_value("generator", $build_array["generator"]);

    if($build_array['countbuildnotes']>0)
      {
      $xml .= add_XML_value("buildnote","1");
      }

    if($build_array['countnotes']>0)
      {
      $xml .= add_XML_value("note","1");
      }


    // Are there labels for this build?
    //
    $labels_array = $build_array['labels'];
    if(!empty($labels_array))
      {
      $xml .= '<labels>';
      foreach($labels_array as $label)
        {
        $xml .= add_XML_value("label",$label);
        }
      $xml .= '</labels>';
      }


    $xml .= "<update>";

    $countupdatefiles = $build_array['countupdatefiles'];
    $totalUpdatedFiles += $countupdatefiles;
    $xml .= add_XML_value("files", $countupdatefiles);

    if($build_array['countupdateerrors']>0)
      {
      $xml .= add_XML_value("errors", 1);
      $totalUpdateError += 1;
      }
    else
      {
      $xml .= add_XML_value("errors", 0);

      if($build_array['countupdatewarnings']>0)
        {
        $xml .= add_XML_value("warning", 1);
        $totalUpdateWarning += 1;
        }
      }

    $duration = $build_array['updateduration'];
    $totalUpdateDuration += $duration;
    $xml .= add_XML_value("time", $duration);

    $xml .= "</update>";


    $xml .= "<compilation>";

    $nerrors = $build_array['countbuilderrors'];
    $totalerrors += $nerrors;
    $xml .= add_XML_value("error", $nerrors);

    $nwarnings = $build_array['countbuildwarnings'];
    $totalwarnings += $nwarnings;
    $xml .= add_XML_value("warning", $nwarnings);

    $duration = $build_array['buildduration'];
    $totalBuildDuration += $duration;
    $xml .= add_XML_value("time", $duration);

    $diff = $build_array['countbuilderrordiff'];
    if($diff>0)
      {
      $xml .= add_XML_value("nerrordiff", $diff);
      }

    $diff = $build_array['countbuildwarningdiff'];
    if($diff>0)
      {
      $xml .= add_XML_value("nwarningdiff", $diff);
      }

    $xml .= "</compilation>";


    $xml .= "<configure>";

    if($build_array['hasconfigurestatus'] != 0)
      {
      $xml .= add_XML_value("error", $build_array['countconfigureerrors']);
      $totalConfigureError += $build_array['countconfigureerrors'];

      $nconfigurewarnings = $build_array['countconfigurewarnings'];
      $xml .= add_XML_value("warning", $nconfigurewarnings);
      $totalConfigureWarning += $nconfigurewarnings;

      $diff = $build_array['countconfigurewarningdiff'];
      if($diff>0)
        {
        $xml .= add_XML_value("warningdiff", $diff);
        }

      $duration = $build_array['configureduration'];
      $totalConfigureDuration += $duration;
      $xml .= add_XML_value("time", $duration);
      }

      $xml .= "</configure>";


    if($build_array['hastest'] != 0)
      {
      $xml .= "<test>";

      $nnotrun = $build_array['counttestsnotrun'];

      if( $build_array['counttestsnotrundiff']>0)
        {
        $xml .= add_XML_value("nnotrundiff",$nnotrundiff);
        }

      $nfail = $build_array['counttestsfailed'];

      if($build_array['counttestsfaileddiff']>0)
        {
        $xml .= add_XML_value("nfaildiff", $build_array['counttestsfaileddiff']);
        }
        
      $npass = $build_array['counttestspassed'];

      if($build_array['counttestspasseddiff']>0)
        {
        $xml .= add_XML_value("npassdiff", $build_array['counttestspasseddiff']);
        }

      if($project_array["showtesttime"] == 1)
        {
        $xml .= add_XML_value("timestatus", $build_array['countteststimestatusfailed']);

        if($build_array['countteststimestatusfaileddiff']>0)
          {
          $xml .= add_XML_value("ntimediff", $build_array['countteststimestatusfaileddiff']);
          }
        }

      $totalnotrun += $nnotrun;
      $totalfail += $nfail;
      $totalpass += $npass;

      $xml .= add_XML_value("notrun",$nnotrun);
      $xml .= add_XML_value("fail",$nfail);
      $xml .= add_XML_value("pass",$npass);

      $duration = $build_array['testsduration'];
      $totalTestsDuration += $duration;
      $xml .= add_XML_value("time", $duration);

      $xml .= "</test>";
      }


    $starttimestamp = strtotime($build_array["starttime"]." UTC");
    $submittimestamp = strtotime($build_array["submittime"]." UTC");
    $xml .= add_XML_value("builddate",date(FMT_DATETIMETZ,$starttimestamp)); // use the default timezone
    $xml .= add_XML_value("submitdate",date(FMT_DATETIMETZ,$submittimestamp));// use the default timezone
    $xml .= "</build>";


    // Coverage
    //
    $coverages = pdo_query("SELECT * FROM coveragesummary WHERE buildid='$buildid'");
    while($coverage_array = pdo_fetch_array($coverages))
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
                
      $xml .= "  <site>".$build_array["sitename"]."</site>";
      $xml .= "  <buildname>".$build_array["name"]."</buildname>";
      $xml .= "  <buildid>".$build_array["id"]."</buildid>";

      @$percent = round($coverage_array["loctested"]/($coverage_array["loctested"]+$coverage_array["locuntested"])*100,2);

      $xml .= "  <percentage>".$percent."</percentage>";
      $xml .= "  <percentagegreen>".$project_array["coveragethreshold"]."</percentagegreen>";
      $xml .= "  <fail>".$coverage_array["locuntested"]."</fail>";
      $xml .= "  <pass>".$coverage_array["loctested"]."</pass>";

      // Compute the diff
      $coveragediff = pdo_query("SELECT * FROM coveragesummarydiff WHERE buildid='$buildid'");
      if(pdo_num_rows($coveragediff) >0)
        {
        $coveragediff_array = pdo_fetch_array($coveragediff);
        $loctesteddiff = $coveragediff_array['loctested'];
        $locuntesteddiff = $coveragediff_array['locuntested'];
        @$previouspercent = round(($coverage_array["loctested"]-$loctesteddiff)/($coverage_array["loctested"]-$loctesteddiff+$coverage_array["locuntested"]-$locuntesteddiff)*100,2);
        $percentdiff = round($percent-$previouspercent,2);
        $xml .= "<percentagediff>".$percentdiff."</percentagediff>";
        $xml .= "<faildiff>".$locuntesteddiff."</faildiff>";
        $xml .= "<passdiff>".$loctesteddiff."</passdiff>";
        }

      $starttimestamp = strtotime($build_array["starttime"]." UTC");
      $submittimestamp = strtotime($build_array["submittime"]." UTC");
      $xml .= add_XML_value("date",date(FMT_DATETIMETZ,$starttimestamp)); // use the default timezone
      $xml .= add_XML_value("submitdate",date(FMT_DATETIMETZ,$submittimestamp));// use the default timezone

      // Are there labels for this build?
      //
      if(!empty($labels_array))
        {
        $xml .= '<labels>';
        foreach($labels_array as $label)
          {
          $xml .= add_XML_value("label",$label);
          }
        $xml .= '</labels>';
        }

      $xml .= "</coverage>";
      }  // end coverage


    // Dynamic Analysis
    //
    $dynanalysis = pdo_query("SELECT checker FROM dynamicanalysis WHERE buildid='$buildid' LIMIT 1");
    while($dynanalysis_array = pdo_fetch_array($dynanalysis))
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
      $xml .= "  <site>".$build_array["sitename"]."</site>";
      $xml .= "  <buildname>".$build_array["name"]."</buildname>";
      $xml .= "  <buildid>".$build_array["id"]."</buildid>";
      
      $xml .= "  <checker>".$dynanalysis_array["checker"]."</checker>";
      $defect = pdo_query("SELECT sum(dd.value) FROM dynamicanalysisdefect AS dd,dynamicanalysis as d 
                                              WHERE d.buildid='$buildid' AND dd.dynamicanalysisid=d.id");
      $defectcount = pdo_fetch_array($defect);
      if(!isset($defectcount[0]))
        {
        $defectcounts = 0;
        }
      else
        { 
        $defectcounts = $defectcount[0];
        }
      $xml .= "  <defectcount>".$defectcounts."</defectcount>";
      $starttimestamp = strtotime($build_array["starttime"]." UTC");
      $submittimestamp = strtotime($build_array["submittime"]." UTC");
      $xml .= add_XML_value("date",date(FMT_DATETIMETZ,$starttimestamp)); // use the default timezone
      $xml .= add_XML_value("submitdate",date(FMT_DATETIMETZ,$submittimestamp));// use the default timezone

      // Are there labels for this build?
      //
      if(!empty($labels_array))
        {
        $xml .= '<labels>';
        foreach($labels_array as $label)
          {
          $xml .= add_XML_value("label",$label);
          }
        $xml .= '</labels>';
        }

      $xml .= "</dynamicanalysis>";
      }  // end dynamicanalysis
    } // end looping through builds


  if(pdo_num_rows($builds)>0)
    {
    if (!$filter_sql)
      {
      $xml .= add_expected_builds($groupid,$currentstarttime,$received_builds,$rowparity);
      }
    if($previousgroupposition == $lastGroupPosition)
      {
      $xml .= "<last>1</last>";
      }
    $xml .= "</buildgroup>";
    }


  // Fill in the rest of the info
  $prevpos = $previousgroupposition+1;
  if($prevpos == 0)
    {
    $prevpos = 1;
    }

  for($i=$prevpos;$i<=$lastGroupPosition;$i++)
    {
    $group = pdo_fetch_array(pdo_query("SELECT g.name,g.id FROM buildgroup AS g,buildgroupposition AS gp WHERE g.id=gp.buildgroupid 
                                                                                     AND gp.position='$i' AND g.projectid='$projectid'
                                                                                     AND gp.starttime<'$end_UTCDate' AND (gp.endtime>'$end_UTCDate'  OR gp.endtime='1980-01-01 00:00:00')"));
    
    $xml .= "<buildgroup>";
    $xml .= add_default_buildgroup_sortlist($group['name']);
    $xml .= add_XML_value("id",$group["id"]);
    $xml .= add_XML_value("name",$group["name"]);
    $xml .= add_XML_value("linkname",str_replace(" ","_",$group["name"]));
    if (!$filter_sql)
      {
      $xml .= add_expected_builds($group["id"],$currentstarttime,$received_builds,$rowparity);
      }
    if($i == $lastGroupPosition)
      {
      $xml .= "<last>1</last>";
      }
    $xml .= "</buildgroup>";  
    }

  $xml .= add_XML_value("totalUpdatedFiles",$totalUpdatedFiles);
  $xml .= add_XML_value("totalUpdateError",$totalUpdateError);
  $xml .= add_XML_value("totalUpdateWarning",$totalUpdateWarning);
  $xml .= add_XML_value("totalUpdateDuration",$totalUpdateDuration);

  $xml .= add_XML_value("totalConfigureDuration",$totalConfigureDuration);
  $xml .= add_XML_value("totalConfigureError",$totalConfigureError);
  $xml .= add_XML_value("totalConfigureWarning",$totalConfigureWarning);

  $xml .= add_XML_value("totalError",$totalerrors);
  $xml .= add_XML_value("totalWarning",$totalwarnings);
  $xml .= add_XML_value("totalBuildDuration",$totalBuildDuration);

  $xml .= add_XML_value("totalNotRun",$totalnotrun);
  $xml .= add_XML_value("totalFail",$totalfail);
  $xml .= add_XML_value("totalPass",$totalpass); 
  $xml .= add_XML_value("totalTestsDuration",$totalTestsDuration);

  $xml .= add_XML_value("enableTestTiming",$project_array["showtesttime"]);

  $end = microtime_float();
  $xml .= "<generationtime>".round($end-$start,3)."</generationtime>";
  $xml .= "</cdash>";

  return $xml;
} // end generate_main_dashboard_XML


/** Generate the subprojects dashboard */
function generate_subprojects_dashboard_XML($projectid,$date)
{
  $start = microtime_float();
  $noforcelogin = 1;
  include_once("cdash/config.php");
  require_once("cdash/pdo.php");
  include('login.php');
  include('cdash/version.php');
  include_once("models/banner.php");
  include_once("models/subproject.php");
      
  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  if(!$db)
    {
    echo "Error connecting to CDash database server<br>\n";
    exit(0);
    }
  if(!pdo_select_db("$CDASH_DB_NAME",$db))
    {
    echo "Error selecting CDash database<br>\n";
    exit(0);
    }
  
  $Project = new Project();
  $Project->Id = $projectid;
  $Project->Fill();
  
  $homeurl = make_cdash_url(htmlentities($Project->HomeUrl));

  checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);
    
  $xml = '<?xml version="1.0"?'.'><cdash>';
  $xml .= "<title>CDash - ".$Project->Name."</title>";
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
  $xml .= "<version>".$CDASH_VERSION."</version>";

  $Banner = new Banner;
  $Banner->SetProjectId(0);
  $text = $Banner->GetText();
  if($text !== false)
    {
    $xml .= "<banner>";
    $xml .= add_XML_value("text",$text);
    $xml .= "</banner>";
    }

  $Banner->SetProjectId($projectid);
  $text = $Banner->GetText();
  if($text !== false)
    {
    $xml .= "<banner>";
    $xml .= add_XML_value("text",$text);
    $xml .= "</banner>";
    }

  list ($previousdate, $currentstarttime, $nextdate) = get_dates($date,$Project->NightlyTime);
  
  // Main dashboard section 
  $xml .=
  "<dashboard>
  <datetime>".date("l, F d Y H:i:s T",time())."</datetime>
  <date>".$date."</date>
  <unixtimestamp>".$currentstarttime."</unixtimestamp>
  <svn>".$Project->CvsUrl."</svn>
  <bugtracker>".$Project->BugTrackerUrl."</bugtracker> 
  <googletracker>".$Project->GoogleTracker."</googletracker> 
  <documentation>".$Project->DocumentationUrl."</documentation>
  <home>".$Project->HomeUrl."</home>
  <logoid>".$Project->getLogoID()."</logoid> 
  <projectid>".$projectid."</projectid> 
  <projectname>".$Project->Name."</projectname> 
  <previousdate>".$previousdate."</previousdate> 
  <projectpublic>".$Project->Public."</projectpublic> 
  <nextdate>".$nextdate."</nextdate>";
  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/models/proProject.php"))
    {
    include_once("local/models/proProject.php");
    $pro= new proProject($projectid); 
    if($pro->isActif()!=false)
      {
      if(strtotime($pro->getEnd()<strtotime(date("r"))))
        {
        $pro->setStatus(0, 0, 0);
        }
      $xml.="<prostatus>".$pro->getStatus()."</prostatus>";
      }
    }
 
  if($currentstarttime>time()) 
   {
   $xml .= "<future>1</future>";
    }
  else
  {
  $xml .= "<future>0</future>";
  }
  $xml .= "</dashboard>";

  // Menu definition
  $xml .= "<menu>";
  if(!isset($date) || strlen($date)<8 || date(FMT_DATE, $currentstarttime)==date(FMT_DATE))
    {
    $xml .= add_XML_value("nonext","1");
    }
  $xml .= "</menu>";

  $beginning_timestamp = $currentstarttime;
  $end_timestamp = $currentstarttime+3600*24;

  $beginning_UTCDate = gmdate(FMT_DATETIME,$beginning_timestamp);
  $end_UTCDate = gmdate(FMT_DATETIME,$end_timestamp);                                                      


  // User
  if(isset($_SESSION['cdash']))
    {
    $xml .= "<user>";
    $userid = $_SESSION['cdash']['loginid'];
    $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' and projectid='$projectid'");
    $user2project_array = pdo_fetch_array($user2project);
    $user = pdo_query("SELECT admin FROM ".qid("user")."  WHERE id='$userid'");
    $user_array = pdo_fetch_array($user);
    $xml .= add_XML_value("id",$userid);
    $isadmin=0;
    if($user2project_array["role"]>1 || $user_array["admin"])
      {
      $isadmin=1;
       }
    $xml .= add_XML_value("admin",$isadmin);
    $xml .= "</user>";
    }
     
  // Get some information about the project
  $xml .= "<project>";
  $xml .= add_XML_value("nbuilderror",$Project->GetNumberOfErrorBuilds($beginning_UTCDate,$end_UTCDate));
  $xml .= add_XML_value("nbuildwarning",$Project->GetNumberOfWarningBuilds($beginning_UTCDate,$end_UTCDate)); 
  $xml .= add_XML_value("nbuildpass",$Project->GetNumberOfPassingBuilds($beginning_UTCDate,$end_UTCDate));
  $xml .= add_XML_value("nconfigureerror",$Project->GetNumberOfErrorConfigures($beginning_UTCDate,$end_UTCDate));
  $xml .= add_XML_value("nconfigurewarning",$Project->GetNumberOfWarningConfigures($beginning_UTCDate,$end_UTCDate));
  $xml .= add_XML_value("nconfigurepass",$Project->GetNumberOfPassingConfigures($beginning_UTCDate,$end_UTCDate));
  $xml .= add_XML_value("ntestpass",$Project->GetNumberOfPassingTests($beginning_UTCDate,$end_UTCDate));
  $xml .= add_XML_value("ntestfail",$Project->GetNumberOfFailingTests($beginning_UTCDate,$end_UTCDate));
  $xml .= add_XML_value("ntestnotrun",$Project->GetNumberOfNotRunTests($beginning_UTCDate,$end_UTCDate));
  if(strlen($Project->GetLastSubmission()) == 0)
    {
    $xml .= add_XML_value("lastsubmission","NA");
    }
  else
    {  
    $xml .= add_XML_value("lastsubmission",$Project->GetLastSubmission());
    }  
  $xml .= "</project>";
  
  // Look for the subproject
  $row=0;
  $subprojectids = $Project->GetSubProjects();
  foreach($subprojectids as $subprojectid)
    {
    $SubProject = new SubProject();
    $SubProject->Id = $subprojectid;
    $xml .= "<subproject>";
    $xml .= add_XML_value("row",$row);
    $xml .= add_XML_value("name",$SubProject->GetName());
    
    $xml .= add_XML_value("nbuilderror",$SubProject->GetNumberOfErrorBuilds($beginning_UTCDate,$end_UTCDate));
    $xml .= add_XML_value("nbuildwarning",$SubProject->GetNumberOfWarningBuilds($beginning_UTCDate,$end_UTCDate)); 
    $xml .= add_XML_value("nbuildpass",$SubProject->GetNumberOfPassingBuilds($beginning_UTCDate,$end_UTCDate));
    $xml .= add_XML_value("nconfigureerror",$SubProject->GetNumberOfErrorConfigures($beginning_UTCDate,$end_UTCDate));
    $xml .= add_XML_value("nconfigurewarning",$SubProject->GetNumberOfWarningConfigures($beginning_UTCDate,$end_UTCDate));
    $xml .= add_XML_value("nconfigurepass",$SubProject->GetNumberOfPassingConfigures($beginning_UTCDate,$end_UTCDate));
    $xml .= add_XML_value("ntestpass",$SubProject->GetNumberOfPassingTests($beginning_UTCDate,$end_UTCDate));
    $xml .= add_XML_value("ntestfail",$SubProject->GetNumberOfFailingTests($beginning_UTCDate,$end_UTCDate));
    $xml .= add_XML_value("ntestnotrun",$SubProject->GetNumberOfNotRunTests($beginning_UTCDate,$end_UTCDate));
    if(strlen($SubProject->GetLastSubmission()) == 0)
      {
      $xml .= add_XML_value("lastsubmission","NA");
      }
    else
      {  
      $xml .= add_XML_value("lastsubmission",$SubProject->GetLastSubmission());
      }
    $xml .= "</subproject>";
    
    if($row == 1)
      {
      $row=0;
      }
    else
      {
      $row=1;
      }  
    } // end for each subproject
 
   
  $end = microtime_float();
  $xml .= "<generationtime>".round($end-$start,3)."</generationtime>";
  $xml .= "</cdash>";

  return $xml;
} // end




// Check if we can connect to the database
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
if(!$db)
  {
  // redirect to the install.php script
  echo "<script language=\"javascript\">window.location='install.php'</script>";
  return;
  }
if(pdo_select_db("$CDASH_DB_NAME",$db) === FALSE)
  {
  echo "<script language=\"javascript\">window.location='install.php'</script>";
  return;
  }
if(pdo_query("SELECT id FROM ".qid("user")." LIMIT 1",$db) === FALSE)
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

if(!isset($projectname )) // if the project name is not set we display the table of projects
  {
  $xml = generate_index_table();
  // Now doing the xslt transition
  generate_XSLT($xml,"indextable");
  }
else
  {
  $projectid = get_project_id($projectname);
  @$date = $_GET["date"];

  // Check if the project has any subproject 
  $Project = new Project();
  $Project->Id = $projectid;
  $displayProject = false;
  if(isset($_GET["display"]) && $_GET["display"]=="project")
    {
    $displayProject = true;
    }

  if(!$displayProject && !isset($_GET["subproject"]) && $Project->GetNumberOfSubProjects() > 0)
    {  
    $xml = generate_subprojects_dashboard_XML($projectid,$date);
    // Now doing the xslt transition
    generate_XSLT($xml,"indexsubproject");
    }
  else
    {
    $xml = generate_main_dashboard_XML($projectid,$date);
    // Now doing the xslt transition
    generate_XSLT($xml,"index");
    }
  }


?>
