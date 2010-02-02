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
  if(!pdo_query("SELECT emailmissingsites FROM user2project LIMIT 1") )
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
    $xml .= add_XML_value("name_encoded",urlencode($project['name']));
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

    // Display if the project is considered active or not
    $dayssincelastsubmission = $CDASH_ACTIVE_PROJECT_DAYS+1;
    if($project['last_build'] != 'NA')
      {
      $dayssincelastsubmission = (time()-strtotime($project['last_build']))/86400;
      }
      
    if($dayssincelastsubmission > $CDASH_ACTIVE_PROJECT_DAYS)
      {
      $xml .= "<active>0</active>";
      }
    else
      {
      $xml .= "<active>1</active>";
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
  //
  // The SQL query should return results that are ordered by these fields
  // such that adjacent rows from the query results are candidates for
  // collapsing according to this function. (i.e. - the "ORDER BY" clause of
  // the SQL query is coupled to this function in terms of whether the
  // "collapse" feature will work as expected...)
  //
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


function get_multiple_builds_hyperlink($build_row, $filterdata)
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

  // Use the exact date range known from the times in build_row to add two
  // build time clauses that more likely get the set of builds represented by
  // $build_row... Use +/- 1 second for use with the "is before" and "is after"
  // date comparison operators.
  //
  $minstarttime = date(FMT_DATETIMETZ, strtotime($build_row['starttime'].' UTC-1 second'));
  $maxstarttime = date(FMT_DATETIMETZ, strtotime($build_row['maxstarttime'].' UTC+1 second'));

  // Gather up string repersenting existing filters so that we simply apply
  // some extra filters for buildname, site and buildstarttime to get the page
  // that shows the builds the user expects. (Match his existing filter
  // criteria in addition to adding our extra fields here. Do not allow user
  // to override the fields we need to specify...)
  //
  // This can only be done effectively with the current filters implementation
  // when the filtercombine parameter is 'and' -- hence the != 'or' test...
  // (Because to specify our 4 filter parameters, we need to use 'and'...)
  //
  $existing_filter_params = '';
  $n = 4;
  if (strtolower($filterdata['filtercombine']) != 'or')
  {
    $count = count($filterdata['filters']);
    for ($i = 0; $i<$count; $i++)
    {
      $filter = $filterdata['filters'][$i];

      if ($filter['field'] != 'buildname' &&
          $filter['field'] != 'site' &&
          $filter['field'] != 'buildstarttime' &&
          $filter['compare'] != 0 &&
          $filter['compare'] != 20 &&
          $filter['compare'] != 40 &&
          $filter['compare'] != 60 &&
          $filter['compare'] != 80)
      {
        $n++;

        $existing_filter_params .= 
          '&field' . $n . '=' . $filter['field'] . '/' . $filter['fieldtype'] .
          '&compare' . $n . '=' . $filter['compare'] .
          '&value' . $n . '=' . htmlspecialchars($filter['value']);
      }
    }
  }

  return $baseurl .
    '&filtercount=' . $n . '&showfilters=1&filtercombine=and' .
    '&field1=buildname/string&compare1=61&value1=' . htmlspecialchars($build_row['name']) .
    '&field2=site/string&compare2=61&value2=' . htmlspecialchars($build_row['sitename']) .
    '&field3=buildstarttime/date&compare3=83&value3=' . htmlspecialchars($minstarttime) .
    '&field4=buildstarttime/date&compare4=84&value4=' . htmlspecialchars($maxstarttime) .
    $existing_filter_params .
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
  <projectname_encoded>".urlencode($projectname)."</projectname_encoded> 
  <previousdate>".$previousdate."</previousdate> 
  <projectpublic>".$projectpublic."</projectpublic> 
  <displaylabels>".$project_array["displaylabels"]."</displaylabels> 
  <nextdate>".$nextdate."</nextdate>";
  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/models/proProject.php"))
    {
    include_once("local/models/proProject.php");
    $pro= new proProject; 
    $pro->ProjectId=$projectid;
    $xml.="<proedition>".$pro->GetEdition(1)."</proedition>";
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
  if(!has_next_date($date, $currentstarttime))
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
  $xml .= "<url>viewChanges.php?project=".urlencode($projectname)."&amp;date=".$gmdate."</url>";
  
  $dailyupdate = pdo_query("SELECT count(ds.dailyupdateid),count(distinct ds.author) 
                            FROM dailyupdate AS d LEFT JOIN dailyupdatefile AS ds ON (ds.dailyupdateid = d.id)
                            WHERE d.date='$gmdate' and d.projectid='$projectid' GROUP BY ds.dailyupdateid");
  
  if(pdo_num_rows($dailyupdate)>0)
    {
    $dailupdate_array = pdo_fetch_array($dailyupdate);
    $xml .= "<nchanges>".$dailupdate_array[0]."</nchanges>";
    $xml .= "<nauthors>".$dailupdate_array[1]."</nauthors>";
    }
  else
   {
   $xml .= "<nchanges>-1</nchanges>";
   }    
  $xml .= add_XML_value("timestamp",date("l, F d Y H:i:s T",$currentstarttime));
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
        $site = $build2grouprule_array["name"];
        $siteid = $build2grouprule_array["siteid"];
        $buildtype = $build2grouprule_array["buildtype"];
        $buildname = $build2grouprule_array["buildname"];
        $xml .= "<build>";
        $xml .= add_XML_value("site",$site);
        $xml .= add_XML_value("siteid",$siteid);
        $xml .= add_XML_value("buildname",$buildname);
        $xml .= add_XML_value("buildtype",$buildtype);
        $xml .= add_XML_value("buildgroupid",$groupid);
        $xml .= add_XML_value("expected","1");
           
        // compute historical average to get approximate expected time
        $query = pdo_query("SELECT AVG(TIME_TO_SEC(TIME(submittime))) FROM build,build2group
                              WHERE build2group.buildid=build.id AND siteid='$siteid' AND name='$buildname'
                              AND type='$buildtype' AND build2group.groupid='$groupid'
                              ORDER BY id DESC LIMIT 5");
        
        $query_array = pdo_fetch_array($query);
        $time = $query_array[0]; 
        $hours = floor($time/3600);
        $time = ($time%3600);
        $minutes = floor($time/60);
        $seconds = ($time%60);

        $nextExpected = strtotime($hours.":".$minutes.":".$seconds);

        $divname = $build2grouprule_array["siteid"]."_".$build2grouprule_array["buildname"]; 
        $divname = str_replace("+","_",$divname);
        $divname = str_replace(".","_",$divname);

        $xml .= add_XML_value("expecteddivname",$divname);
        $xml .= add_XML_value("submitdate","No Submission");
        $xml .= add_XML_value("expectedstarttime",gmdate(FMT_TIME,$nextExpected));
        $xml .= "</build>";
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

  // If the user is logged in we display if the build has some changes for him
  $userupdatesql = "";
  if(isset($_SESSION['cdash']))
    {
    $userupdatesql = "(SELECT count(updatefile.buildid) FROM updatefile,user2project
                      WHERE buildid=b.id AND user2project.projectid=b.projectid 
                      AND user2project.userid='".$_SESSION['cdash']['loginid']."'
                      AND user2project.cvslogin=updatefile.author) AS userupdates,";
    }
           
  $sql =  "SELECT b.id,b.siteid,
                  bn.buildid AS countbuildnotes,
                  bu.status AS updatestatus,
                  bu.starttime AS updatestarttime,
                  bu.endtime AS updateendtime, 
                  bu.nfiles AS countupdatefiles,
                  bu.warnings AS countupdatewarnings,
                  c.status AS configurestatus,
                  c.starttime AS configurestarttime,
                  c.endtime AS configureendtime,
                  c.warnings AS countconfigurewarnings,
                  be_diff.difference_positive AS countbuilderrordiffp,
                  be_diff.difference_negative AS countbuilderrordiffn,
                  bw_diff.difference_positive AS countbuildwarningdiffp,
                  bw_diff.difference_negative AS countbuildwarningdiffn,
                  ce_diff.difference AS countconfigurewarningdiff,
                  btt.time AS testsduration,
                  tnotrun_diff.difference_positive AS counttestsnotrundiffp,
                  tnotrun_diff.difference_negative AS counttestsnotrundiffn,
                  tfailed_diff.difference_positive AS counttestsfaileddiffp,
                  tfailed_diff.difference_negative AS counttestsfaileddiffn,
                  tpassed_diff.difference_positive AS counttestspasseddiffp,
                  tpassed_diff.difference_negative AS counttestspasseddiffn,
                  tstatusfailed_diff.difference_positive AS countteststimestatusfaileddiffp,
                  tstatusfailed_diff.difference_negative AS countteststimestatusfaileddiffn,
                  (SELECT count(buildid) FROM build2note WHERE buildid=b.id)  AS countnotes,"
                  .$userupdatesql."
                  s.name AS sitename,
                  b.stamp,b.name,b.type,b.generator,b.starttime,b.endtime,b.submittime,
                  b.builderrors AS countbuilderrors,
                  b.buildwarnings AS countbuildwarnings,
                  b.testnotrun AS counttestsnotrun,
                  b.testfailed AS counttestsfailed,
                  b.testpassed AS counttestspassed,
                  b.testtimestatusfailed AS countteststimestatusfailed,
                  g.name as groupname,gp.position,g.id as groupid
                  FROM site AS s, build2group AS b2g,buildgroup AS g, buildgroupposition AS gp ".$subprojecttablesql.",
                  build AS b
                  LEFT JOIN buildnote AS bn ON (bn.buildid=b.id)
                  LEFT JOIN buildupdate AS bu ON (bu.buildid=b.id)
                  LEFT JOIN configure AS c ON (c.buildid=b.id)
                  LEFT JOIN builderrordiff AS be_diff ON (be_diff.buildid=b.id AND be_diff.type=0)
                  LEFT JOIN builderrordiff AS bw_diff ON (bw_diff.buildid=b.id AND bw_diff.type=1)
                  LEFT JOIN configureerrordiff AS ce_diff ON (ce_diff.buildid=b.id AND ce_diff.type=1)
                  LEFT JOIN buildtesttime AS btt ON (btt.buildid=b.id)
                  LEFT JOIN testdiff AS tnotrun_diff ON (tnotrun_diff.buildid=b.id AND tnotrun_diff.type=0)
                  LEFT JOIN testdiff AS tfailed_diff ON (tfailed_diff.buildid=b.id AND tfailed_diff.type=1)
                  LEFT JOIN testdiff AS tpassed_diff ON (tpassed_diff.buildid=b.id AND tpassed_diff.type=2)
                  LEFT JOIN testdiff AS tstatusfailed_diff ON (tstatusfailed_diff.buildid=b.id AND tstatusfailed_diff.type=3)
                  WHERE s.id=b.siteid AND ".$date_clause."
                   b.projectid='$projectid' AND b2g.buildid=b.id AND gp.buildgroupid=g.id AND b2g.groupid=g.id  
                   AND gp.starttime<'$end_UTCDate' AND (gp.endtime>'$end_UTCDate' OR gp.endtime='1980-01-01 00:00:00')
                  ".$subprojectsql." ".$filter_sql." ORDER BY gp.position ASC,b.name ASC,b.siteid ASC,b.stamp DESC";

      
  // We shouldn't get any builds for group that have been deleted (otherwise something is wrong)
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
    //  sitename
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
    //  maxstarttime
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
    $build_row['maxstarttime'] = $build_row['starttime'];   
    
    // One more query for the labels
    $build_row['labels'] = array();
    $label_rows = pdo_all_rows_query(
      "SELECT text FROM label, label2build ".
      "WHERE label2build.buildid='$buildid' ".
      "AND label2build.labelid=label.id");
    foreach($label_rows as $label_row)
      {
      $build_row['labels'][] = $label_row['text'];
      }

    // Updates
    if(!empty($build_row['updatestarttime']))
      {
      $build_row['updateduration'] = round((strtotime($build_row['updateendtime'])-strtotime($build_row['updatestarttime']))/60,1);
      }
    else
     {
     $build_row['updateduration'] = 0;  
     }
      
      
    if(strlen($build_row["updatestatus"]) > 0 &&
       $build_row["updatestatus"]!="0")
      {
      $build_row['countupdateerrors'] = 1;
      }
    else
      {
      $build_row['countupdateerrors'] = 0;
      }

    $build_row['buildduration'] = round((strtotime($build_row['endtime'])-strtotime($build_row['starttime']))/60,1);
    
    // Error/Warnings differences
    if(empty($build_row['countbuilderrordiffp']))
      {
      $build_row['countbuilderrordiffp'] = 0;
      } 
    if(empty($build_row['countbuilderrordiffn']))
      {
      $build_row['countbuilderrordiffn'] = 0;
      } 
         
    if(empty($build_row['countbuildwarningdiffp']))
      {
      $build_row['countbuildwarningdiffp'] = 0;
      }
    if(empty($build_row['countbuildwarningdiffn']))
      {
      $build_row['countbuildwarningdiffn'] = 0;
      }
      
    $build_row['hasconfigurestatus'] = 0;
    $build_row['countconfigureerrors'] = 0;
    $build_row['configureduration'] = 0;
        
    if(strlen($build_row['configurestatus'])>0)
      {
      $build_row['hasconfigurestatus'] = 1;
      $build_row['countconfigureerrors'] = $build_row['configurestatus'];
      $build_row['configureduration'] = round((strtotime($build_row["configureendtime"])-strtotime($build_row["configurestarttime"]))/60, 1);
      }

    if(empty($build_row['countconfigurewarnings']))
      {
      $build_row['countconfigurewarnings'] = 0;
      } 
    
    if(empty($build_row['countconfigurewarningdiff']))
      {
      $build_row['countconfigurewarningdiff'] = 0;
      } 

      
    $build_row['hastest'] = 0;
    if($build_row['counttestsfailed']!=-1)
      {
      $build_row['hastest'] = 1;
      }
      
    if(empty($build_row['testsduration']))
      {
      $time_array = pdo_fetch_array(pdo_query("SELECT SUM(time) FROM build2test WHERE buildid='$buildid'"));
      $build_row['testsduration'] = round($time_array[0]/60,1);
      }
    else
      {
      $build_row['testsduration'] = round($build_row['testsduration'],1); //already in minutes
      } 
    
    //  Save the row in '$build_rows'
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
        if ($build_row['maxstarttime'] > $build_rows_collapsed[$idx]['maxstarttime'])
          {
          $build_rows_collapsed[$idx]['maxstarttime'] = $build_row['maxstarttime'];
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
        $build_rows_collapsed[$idx]['countbuilderrordiffp'] += $build_row['countbuilderrordiffp'];
        $build_rows_collapsed[$idx]['countbuilderrordiffn'] += $build_row['countbuilderrordiffn'];
        $build_rows_collapsed[$idx]['countbuildwarningdiffp'] += $build_row['countbuildwarningdiffp'];
        $build_rows_collapsed[$idx]['countbuildwarningdiffn'] += $build_row['countbuildwarningdiffn'];
        
        $build_rows_collapsed[$idx]['hasconfigurestatus'] += $build_row['hasconfigurestatus'];
        $build_rows_collapsed[$idx]['countconfigureerrors'] += $build_row['countconfigureerrors'];
        $build_rows_collapsed[$idx]['countconfigurewarnings'] += $build_row['countconfigurewarnings'];
        $build_rows_collapsed[$idx]['countconfigurewarningdiff'] += $build_row['countconfigurewarningdiff'];
        $build_rows_collapsed[$idx]['configureduration'] += $build_row['configureduration'];

        //  test
        $build_rows_collapsed[$idx]['hastest'] += $build_row['hastest'];
        $build_rows_collapsed[$idx]['counttestsnotrun'] += $build_row['counttestsnotrun'];
        $build_rows_collapsed[$idx]['counttestsnotrundiffp'] += $build_row['counttestsnotrundiffp'];
        $build_rows_collapsed[$idx]['counttestsnotrundiffn'] += $build_row['counttestsnotrundiffn'];
        $build_rows_collapsed[$idx]['counttestsfailed'] += $build_row['counttestsfailed'];
        $build_rows_collapsed[$idx]['counttestsfaileddiffp'] += $build_row['counttestsfaileddiffp'];
        $build_rows_collapsed[$idx]['counttestsfaileddiffn'] += $build_row['counttestsfaileddiffn'];
        $build_rows_collapsed[$idx]['counttestspassed'] += $build_row['counttestspassed'];
        $build_rows_collapsed[$idx]['counttestspasseddiffp'] += $build_row['counttestspasseddiffp'];
        $build_rows_collapsed[$idx]['counttestspasseddiffn'] += $build_row['counttestspasseddiffn'];
        $build_rows_collapsed[$idx]['countteststimestatusfailed'] += $build_row['countteststimestatusfailed'];
        $build_rows_collapsed[$idx]['countteststimestatusfaileddiffp'] += $build_row['countteststimestatusfaileddiffp'];
        $build_rows_collapsed[$idx]['countteststimestatusfaileddiffn'] += $build_row['countteststimestatusfaileddiffn'];
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
        $xml .= "</buildgroup>";
        }  


      $xml .= "<buildgroup>";
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
      $xml .= add_XML_value("multiplebuildshyperlink", get_multiple_builds_hyperlink($build_array, $filterdata));
      }

    $xml .= add_XML_value("type", strtolower($build_array["type"]));
    $xml .= add_XML_value("site", $build_array["sitename"]);
    $xml .= add_XML_value("siteid", $siteid);
    $xml .= add_XML_value("buildname", $build_array["name"]);
    if(isset($build_array["userupdates"]))
      {
      $xml .= add_XML_value("userupdates", $build_array["userupdates"]);    
      } 
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
    if(!empty($build_array['updatestarttime']))
      {
      $xml .= add_XML_value("defined",1);

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
      } // end if we have an update
    $xml .= "</update>";


    $xml .= "<compilation>";

    if($build_array['countbuilderrors']>=0)
      {
      $nerrors = $build_array['countbuilderrors'];
      $totalerrors += $nerrors;
      $xml .= add_XML_value("error", $nerrors);
    
      $nwarnings = $build_array['countbuildwarnings'];
      $totalwarnings += $nwarnings;
      $xml .= add_XML_value("warning", $nwarnings);
      $duration = $build_array['buildduration'];
      $totalBuildDuration += $duration;
      $xml .= add_XML_value("time", $duration);
      
      $diff = $build_array['countbuilderrordiffp'];
      if($diff!=0)
        {
        $xml .= add_XML_value("nerrordiffp", $diff);
        }
      $diff = $build_array['countbuilderrordiffn'];
      if($diff!=0)
        {
        $xml .= add_XML_value("nerrordiffn", $diff);
        }
        
      $diff = $build_array['countbuildwarningdiffp'];
      if($diff!=0)
        {
        $xml .= add_XML_value("nwarningdiffp", $diff);
        }
      $diff = $build_array['countbuildwarningdiffn'];
      if($diff!=0)
        {
        $xml .= add_XML_value("nwarningdiffn", $diff);
        }  
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
      if($diff!=0)
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

      if($build_array['counttestsnotrundiffp']!=0)
        {
        $xml .= add_XML_value("nnotrundiffp",$build_array['counttestsnotrundiffp']);
        }
      if($build_array['counttestsnotrundiffn']!=0)
        {
        $xml .= add_XML_value("nnotrundiffn",$build_array['counttestsnotrundiffn']);
        }
 
      $nfail = $build_array['counttestsfailed'];

      if($build_array['counttestsfaileddiffp']!=0)
        {
        $xml .= add_XML_value("nfaildiffp", $build_array['counttestsfaileddiffp']);
        }
      if($build_array['counttestsfaileddiffn']!=0)
        {
        $xml .= add_XML_value("nfaildiffn", $build_array['counttestsfaileddiffn']);
        }
          
      $npass = $build_array['counttestspassed'];

      if($build_array['counttestspasseddiffp']!=0)
        {
        $xml .= add_XML_value("npassdiffp", $build_array['counttestspasseddiffp']);
        }
      if($build_array['counttestspasseddiffn']!=0)
        {
        $xml .= add_XML_value("npassdiffn", $build_array['counttestspasseddiffn']);
        }
        
      if($project_array["showtesttime"] == 1)
        {
        $xml .= add_XML_value("timestatus", $build_array['countteststimestatusfailed']);

        if($build_array['countteststimestatusfaileddiffp']!=0)
          {
          $xml .= add_XML_value("ntimediffp", $build_array['countteststimestatusfaileddiffp']);
          }
        if($build_array['countteststimestatusfaileddiffn']!=0)
          {
          $xml .= add_XML_value("ntimediffn", $build_array['countteststimestatusfaileddiffn']);
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
    $xml .= "</buildgroup>";  
    }

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
  
  
  $svnurl = make_cdash_url(htmlentities($Project->CvsUrl));
  $homeurl = make_cdash_url(htmlentities($Project->HomeUrl));
  $bugurl = make_cdash_url(htmlentities($Project->BugTrackerUrl));
  $googletracker = htmlentities($Project->GoogleTracker);  
  $docurl = make_cdash_url(htmlentities($Project->DocumentationUrl));  
  
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
  <logoid>".$Project->getLogoID()."</logoid> 
  <projectid>".$projectid."</projectid> 
  <projectname>".$Project->Name."</projectname> 
  <projectname_encoded>".urlencode($Project->Name)."</projectname_encoded> 
  <previousdate>".$previousdate."</previousdate> 
  <projectpublic>".$Project->Public."</projectpublic> 
  <nextdate>".$nextdate."</nextdate>";
  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/models/proProject.php"))
    {
    include_once("local/models/proProject.php");
    $pro= new proProject; 
    $pro->ProjectId=$projectid;
    $xml.="<proedition>".$pro->GetEdition(1)."</proedition>";
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
  if(!has_next_date($date, $currentstarttime))
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
if(!$db
   || pdo_select_db("$CDASH_DB_NAME",$db) === FALSE
   || pdo_query("SELECT id FROM ".qid("user")." LIMIT 1",$db) === FALSE)
  {
  // redirect to the install.php script
  if($CDASH_PRODUCTION_MODE)
    {
    echo "CDash cannot connect to the database.";
    return;
    }
  else  
    {
    echo "<script language=\"javascript\">window.location='install.php'</script>";
    }
  return;
  }

@$projectname = $_GET["project"];

// If we should not generate any XSL
if(isset($NoXSLGenerate))
  {
  return;
  }

if(!isset($projectname)) // if the project name is not set we display the table of projects
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
