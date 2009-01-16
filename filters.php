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
include("config.php");
require_once("pdo.php");
include("common.php");

/** Generate the index table */
function generate_index_table()
{ 
  $noforcelogin = 1;
  include("config.php");
  require_once("pdo.php");
  include('login.php');
  include('version.php');

  $xml = '<?xml version="1.0"?'.'><cdash>';
  $xml .= add_XML_value("title","CDash");
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
  $xml .= "<version>".$CDASH_VERSION."</version>";
  
  $xml .= "<hostname>".$_SERVER['SERVER_NAME']."</hostname>";
  $xml .= "<date>".date("r")."</date>";
  
  // Check if the database is up to date
  if(!pdo_query("SELECT buildid FROM coveragesummarydiff LIMIT 1"))
    {  
    $xml .= "<upgradewarning>The current database shema doesn't match the version of CDash you are running,
    upgrade your database structure in the Administration/CDash maintenance panel of CDash.</upgradewarning>";
    }

  $xml .= "<dashboard>

 <googletracker>".$CDASH_DEFAULT_GOOGLE_ANALYTICS."</googletracker>
 </dashboard> ";
 
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
    $xml .= "<name>".$project['name']."</name>";
      
    if($project['last_build'] == "NA")
      {
      $xml .= "<lastbuild>NA</lastbuild>";
      }
    else
      {
      $xml .= "<lastbuild>".date(FMT_DATETIMETZ,strtotime($project['last_build']. "UTC"))."</lastbuild>";
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


// http://arrakis.kitwarein.com/CDash/filters.php?project=CMake&filtercount=2&filter1=buildname&compare1=65&value1=Linux
//
function php_parameters_to_sql()
{
  $echo_on = 0; // set to 1 for debugging only

  $sql = '';
  $clauses = 0;

  @$filtercount = $_REQUEST['filtercount'];

  @$clear = $_REQUEST['clear'];
  if ($clear == 'Clear')
  {
    if ($echo_on == 1)
    {
      echo 'info: Clear clicked... forcing $filtercount to 0<br/>';
    }

    $filtercount = 0;
  }
  else
  {
    if ($echo_on == 1)
    {
      echo 'info: using $filtercount=' . $filtercount . '<br/>';
    }
  }

  @$filtercombine = strtolower($_REQUEST['filtercombine']);
  if ($filtercombine == 'or')
  {
    $filtercombine = 'OR';
  }
  else
  {
    $filtercombine = 'AND';
  }

  $sql = 'AND (';

  for ($i = 1; $i <= $filtercount; ++$i)
  {
    $filter = $_REQUEST['filter'.$i];
    $compare = $_REQUEST['compare'.$i];
    $value = $_REQUEST['value'.$i];

    //echo 'filter' . $i . ': ' . $filter . '<br/>';
    //echo 'compare' . $i . ': ' . $compare . '<br/>';
    //echo 'value' . $i . ': ' . $value . '<br/>';

    $field_name = '';
    $sql_compare = '';
    $sql_value = '';


    // Translate comparison operation and compare to
    // value to SQL equivalents:
    //
    switch ($compare)
    {
      case 0:
        // explicitly skip adding a clause when $compare == 0 ( "--" in the GUI )
      break;

      case 1:
      {
        // bool is true
        $sql_compare = '!=';
        $sql_value = "0";
      }
      break;

      case 2:
      {
        // bool is false
        $sql_compare = '=';
        $sql_value = "0";
      }
      break;

      case 41:
      {
        // number is equal
        $sql_compare = '=';
        $sql_value = "'$value'";
      }
      break;

      case 42:
      {
        // number is not equal
        $sql_compare = '!=';
        $sql_value = "'$value'";
      }
      break;

      case 43:
      {
        // number is greater
        $sql_compare = '>';
        $sql_value = "'$value'";
      }
      break;

      case 44:
      {
        // number is less
        $sql_compare = '<';
        $sql_value = "'$value'";
      }
      break;

      case 61:
      {
        // string is equal
        $sql_compare = '=';
        $sql_value = "'$value'";
      }
      break;

      case 62:
      {
        // string is not equal
        $sql_compare = '!=';
        $sql_value = "'$value'";
      }
      break;

      case 63:
      {
        // string contains
        $sql_compare = 'LIKE';
        $sql_value = "'%$value%'";
      }
      break;

      case 64:
      {
        // string does not contain
        $sql_compare = 'NOT LIKE';
        $sql_value = "'%$value%'";
      }
      break;

      case 65:
      {
        // string starts with
        $sql_compare = 'LIKE';
        $sql_value = "'$value%'";
      }
      break;

      case 66:
      {
        // string ends with
        $sql_compare = 'LIKE';
        $sql_value = "'%$value'";
      }
      break;

      default:
        // warn php caller : unknown $compare value...
        if ($echo_on == 1)
        {
          echo 'warning: unknown $compare value: ' . $compare . '<br/>';
        }
      break;
    }


//  SELECT b.id,b.siteid,b.name,b.type,b.generator,b.starttime,
//         b.endtime,b.submittime,g.name as groupname,gp.position,g.id as groupid
//         FROM build AS b, build2group AS b2g,buildgroup AS g, buildgroupposition AS gp


    // Translate filter name to SQL field name:
    //
    switch (strtolower($filter))
    {
      case 'buildduration':
      {
        $field_name = "ROUND(TIMESTAMPDIFF(SECOND,b.starttime,b.endtime)/60.0,1)";
      }
      break;

      case 'builderrors':
      {
        $field_name = "(SELECT COUNT(buildid) FROM builderror WHERE buildid=b.id AND type='0')";
      }
      break;

      case 'buildgenerator':
      {
        $field_name = 'b.generator';
      }
      break;

      case 'buildname':
      {
        $field_name = 'b.name';
      }
      break;

      case 'buildtype':
      {
        $field_name = 'b.type';
      }
      break;

      case 'buildwarnings':
      {
        $field_name = "(SELECT COUNT(buildid) FROM builderror WHERE buildid=b.id AND type='1')";
      }
      break;

      case 'configureduration':
      {
        $field_name = "(SELECT ROUND(TIMESTAMPDIFF(SECOND,starttime,endtime)/60.0,1) FROM configure WHERE buildid=b.id)";
      }
      break;

      case 'configureerrors':
      {
        $field_name = "(SELECT COUNT(buildid) FROM configureerror WHERE buildid=b.id AND type='0')";
      }
      break;

      case 'configurewarnings':
      {
        $field_name = "(SELECT COUNT(buildid) FROM configureerror WHERE buildid=b.id AND type='1')";
      }
      break;

      case 'expected':
      {
        $field_name = "IF((SELECT COUNT(expected) FROM build2grouprule WHERE groupid=b2g.groupid AND buildtype=b.type AND buildname=b.name AND siteid=b.siteid)>0,(SELECT COUNT(expected) FROM build2grouprule WHERE groupid=b2g.groupid AND buildtype=b.type AND buildname=b.name AND siteid=b.siteid),0)";
      }
      break;

      case 'groupname':
      {
        $field_name = 'g.name';
      }
      break;

      case 'hasctestnotes':
      {
        $field_name = '(SELECT COUNT(*) FROM build2note WHERE buildid=b.id)';
      }
      break;

      case 'hasusernotes':
      {
        $field_name = '(SELECT COUNT(*) FROM buildnote WHERE buildid=b.id)';
      }
      break;

      case 'site':
      {
        $field_name = "(SELECT name FROM site WHERE site.id=b.siteid)";
      }
      break;

      case 'testsfailed':
      {
        $field_name = "(SELECT COUNT(buildid) FROM build2test WHERE buildid=b.id AND status='failed')";
      }
      break;

      case 'testsnotrun':
      {
        $field_name = "(SELECT COUNT(buildid) FROM build2test WHERE buildid=b.id AND status='notrun')";
      }
      break;

      case 'testspassed':
      {
        $field_name = "(SELECT COUNT(buildid) FROM build2test WHERE buildid=b.id AND status='passed')";
      }
      break;

      case 'testduration':
      {
        $field_name = "IF((SELECT COUNT(buildid) FROM build2test WHERE buildid=b.id)>0,(SELECT ROUND(SUM(time)/60.0,1) FROM build2test WHERE buildid=b.id),0)";
      }
      break;

      case 'testtimestatus':
      {
        $field_name = "(SELECT COUNT(buildid) FROM build2test WHERE buildid=b.id AND timestatus>=(SELECT testtimemaxstatus FROM project WHERE projectid=b.projectid))";
      }
      break;

      case 'updatedfiles':
      {
        $field_name = "(SELECT COUNT(buildid) FROM updatefile WHERE buildid=b.id)";
      }
      break;

      case 'updateduration':
      {
        $field_name = "(SELECT ROUND(TIMESTAMPDIFF(SECOND,starttime,endtime)/60.0,1) FROM buildupdate WHERE buildid=b.id)";
      }
      break;

      case 'updateerrors':
      {
      // this one is pretty complicated... save it for later...
      //  $field_name = "(SELECT COUNT(buildid) FROM buildupdate WHERE buildid=b.id)";
      }
      break;

      case 'updatewarnings':
      {
      // this one is pretty complicated... save it for later...
      //  $field_name = "(SELECT COUNT(buildid) FROM buildupdate WHERE buildid=b.id)";
      }
      break;
    }


    if ($field_name != '' && $sql_compare != '')
    {
      if ($clauses > 0)
      {
        $sql .= ' ' . $filtercombine . ' ';
      }

      $sql .= $field_name . ' ' . $sql_compare . ' ' . $sql_value;

      ++$clauses;
    }
  }

  if ($clauses == 0)
  {
    $sql = '';
  }
  else
  {
    $sql .= ')';
  }

  if ($echo_on == 1)
  {
    echo 'filter sql: ' . $sql . '<br/>';
    echo '<br/>';
  }

  return $sql;
}


/** Generate the main dashboard XML */
function generate_main_dashboard_XML($projectid,$date)
{
  $start = microtime_float();
  $noforcelogin = 1;
  include_once("config.php");
  require_once("pdo.php");
  include('login.php');
  include('version.php');

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
  
  $totalerrors = 0;
  $totalwarnings = 0;
  $totalConfigureError = 0;
  $totalConfigureWarning = 0;
  $totalnotrun = 0;
  $totalfail= 0;
  $totalpass = 0;  
            
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
    
  // Check the builds
  $beginning_timestamp = $currentstarttime;
  $end_timestamp = $currentstarttime+3600*24;

  $beginning_UTCDate = gmdate(FMT_DATETIME,$beginning_timestamp);
  $end_UTCDate = gmdate(FMT_DATETIME,$end_timestamp);                                                      
  
  $filter_sql = php_parameters_to_sql();

  $sql =  "SELECT b.id,b.siteid,b.name,b.type,b.generator,b.starttime,b.endtime,b.submittime,g.name as groupname,gp.position,g.id as groupid 
                         FROM build AS b, build2group AS b2g,buildgroup AS g, buildgroupposition AS gp
                         WHERE b.starttime<'$end_UTCDate' AND b.starttime>='$beginning_UTCDate'
                         AND b.projectid = '$projectid'
                         AND b2g.buildid=b.id AND gp.buildgroupid=g.id AND b2g.groupid=g.id
                         AND gp.starttime<'$end_UTCDate' AND (gp.endtime>'$end_UTCDate' OR gp.endtime='1980-01-01 00:00:00')
                         " . $filter_sql . "
                         ORDER BY gp.position ASC,b.name ASC ";

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


  // Fake build group at the top that always displays the totals:
  //
  $xml .= "<buildgroup>";
  $xml .= "<showtotals>1</showtotals>";
  $xml .= add_XML_value("name", "Grand Totals");
  $xml .= add_XML_value("linkname", "Grand_Totals");
  $xml .= "</buildgroup>";


  while($build_array = pdo_fetch_array($builds))
    {
    $groupposition = $build_array["position"];
    if($previousgroupposition != $groupposition)
      {
      $groupname = $build_array["groupname"];  
      if($previousgroupposition != -1)
        {
        $xml .= add_expected_builds($groupid,$currentstarttime,$received_builds,$rowparity);
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
        $xml .= "<showtotals>0</showtotals>";
        $rowparity = 0;
        $xml .= add_XML_value("name",$group["name"]);
        $xml .= add_XML_value("linkname",str_replace(" ","_",$group["name"]));
        $xml .= add_XML_value("id",$group["id"]);
        $xml .= add_expected_builds($group["id"],$currentstarttime,$received_builds,$rowparity);
        if($previousgroupposition == $lastGroupPosition)
          {
          $xml .= "<last>1</last>";
          }
        $xml .= "</buildgroup>";  
        }  
             
      $xml .= "<buildgroup>";
      
      // Make the default for now
      // This should probably be defined by the user as well on the users page
      if($groupname == "Nightly")
        {
        $xml .= add_XML_value("sortlist","{sortlist: [[1,0]]}"); //build name
        }
      if($groupname == "Continuous")
        {
        $xml .= add_XML_value("sortlist","{sortlist: [[14,1]]}"); //buildtime
        }
      if($groupname == "Experimental")
        {
        $xml .= add_XML_value("sortlist","{sortlist: [[14,1]]}"); //buildtime
        }
      
      $rowparity = 0;
      $received_builds = array();
      $xml .= add_XML_value("name",$groupname);
      $xml .= add_XML_value("linkname",str_replace(" ","_",$groupname));
      $xml .= add_XML_value("id",$build_array["groupid"]);
      $previousgroupposition = $groupposition;
      }
    $groupid = $build_array["groupid"];
    $buildid = $build_array["id"];
    $configure = pdo_query("SELECT status FROM configure WHERE buildid='$buildid'");
    $nconfigure = pdo_num_rows($configure);
    $siteid = $build_array["siteid"];
          
    $site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
    
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
            
    // Search if we have notes for that build
    $buildnote = pdo_query("SELECT count(*) FROM buildnote WHERE buildid='$buildid'");
    $buildnote_array = pdo_fetch_row($buildnote);
    if($buildnote_array[0]>0)
      {
      $xml .= add_XML_value("buildnote","1");
      }
      
    $received_builds[] = $site_array["name"]."_".$build_array["name"];
    
    $note = pdo_query("SELECT count(*) FROM build2note WHERE buildid='$buildid'");
    $note_array = pdo_fetch_row($note);
    if($note_array[0]>0)
      {
      $xml .= add_XML_value("note","1");
      }
      
      
    $xml .= "<update>";  
    $update = pdo_query("SELECT count(*) FROM updatefile WHERE buildid='$buildid'");
    $update_array = pdo_fetch_row($update);
    $xml .= add_XML_value("files",$update_array[0]);
  
    $updatestatus = pdo_query("SELECT status,starttime,endtime FROM buildupdate WHERE buildid='$buildid'");
    $updatestatus_array = pdo_fetch_array($updatestatus);
    
    if(strlen($updatestatus_array["status"]) > 0 && $updatestatus_array["status"]!="0")
      {
      $xml .= add_XML_value("errors",1);
      }
    else
      {
      $updateerrors = pdo_query("SELECT count(*) FROM updatefile WHERE buildid='$buildid' AND author='Local User' AND revision='-1'");
      $updateerrors_array = pdo_fetch_row($updateerrors);
      $xml .= add_XML_value("errors",0);
      if($updateerrors_array[0]>0)
        {
        $xml .= add_XML_value("warning",1);
        }
      }

    $diff = (strtotime($updatestatus_array["endtime"])-strtotime($updatestatus_array["starttime"]))/60;
    $xml .= "<time>".$diff."</time>";

    $xml .= "</update>";  
    $xml .= "<compilation>";
    
    // Find the number of errors and warnings
    $builderror = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$buildid' AND type='0'");
    $builderror_array = pdo_fetch_array($builderror);
    $nerrors = $builderror_array[0];
    $totalerrors += $nerrors;
    $xml .= add_XML_value("error",$nerrors);
    $buildwarning = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$buildid' AND type='1'");
    $buildwarning_array = pdo_fetch_array($buildwarning);
    $nwarnings = $buildwarning_array[0];
    $totalwarnings += $nwarnings;
    $xml .= add_XML_value("warning",$nwarnings);
    $diff = (strtotime($build_array["endtime"])-strtotime($build_array["starttime"]))/60;
    $xml .= "<time>".$diff."</time>";
    
    // Differences between number of errors and warnings
    $builderrordiff = pdo_query("SELECT difference FROM builderrordiff WHERE buildid='$buildid' AND type='0'");
    if(pdo_num_rows($builderrordiff)>0)
      {
      $builderrordiff_array = pdo_fetch_array($builderrordiff);
      $xml .= add_XML_value("nerrordiff",$builderrordiff_array["difference"]);
      }
    $buildwarningdiff = pdo_query("SELECT difference FROM builderrordiff WHERE buildid='$buildid' AND type='1'");
    if(pdo_num_rows($buildwarningdiff)>0)
      {
      $buildwarningdiff_array = pdo_fetch_array($buildwarningdiff);
      $xml .= add_XML_value("nwarningdiff",$buildwarningdiff_array["difference"]);
      }
    $xml .= "</compilation>";

    // Get the Configure options
    $xml .= "<configure>";
    $configure = pdo_query("SELECT status,starttime,endtime FROM configure WHERE buildid='$buildid'");
    if($nconfigure)
      {
      $configure_array = pdo_fetch_array($configure);
      $xml .= add_XML_value("error",$configure_array["status"]);
      $totalConfigureError += $configure_array["status"];

      // Put the configuration warnings here
      $configurewarnings = pdo_query("SELECT count(*) AS num FROM configureerror WHERE buildid='$buildid' AND type='1'");
      $configurewarnings_array = pdo_fetch_array($configurewarnings);
      $nconfigurewarnings = $configurewarnings_array['num'];
      $xml .= add_XML_value("warning",$nconfigurewarnings);
      $totalConfigureWarning += $nconfigurewarnings;
      
      // Add the difference
      $configurewarning = pdo_query("SELECT difference FROM configureerrordiff WHERE buildid='$buildid' AND type='1'");
      if(pdo_num_rows($configurewarning)>0)
        {
        $configurewarning_array = pdo_fetch_array($configurewarning);
        $nconfigurewarning = $configurewarning_array["difference"];
        $xml .= add_XML_value("warningdiff",$nconfigurewarning);
        }
      
      $diff = (strtotime($configure_array["endtime"])-strtotime($configure_array["starttime"]))/60;
      $xml .= "<time>".$diff."</time>";
      }
    $xml .= "</configure>";
     
    // Get the tests
    $test = pdo_query("SELECT * FROM build2test WHERE buildid='$buildid'");
    if(pdo_num_rows($test)>0)
      {
      $test_array = pdo_fetch_array($test);
      $xml .= "<test>";
      // We might be able to do this in one request
      $nnotrun_array = pdo_fetch_array(pdo_query("SELECT count(*) FROM build2test WHERE buildid='$buildid' AND status='notrun'"));
      $nnotrun = $nnotrun_array[0];
      
      // Add the difference
      $notrundiff = pdo_query("SELECT difference FROM testdiff WHERE buildid='$buildid' AND type='0'");
      if(pdo_num_rows($notrundiff)>0)
        {
        $nnotrundiff_array = pdo_fetch_array($notrundiff);
        $nnotrundiff = $nnotrundiff_array["difference"];
        $xml .= add_XML_value("nnotrundiff",$nnotrundiff);
        }
      
      $sql = "SELECT count(*) FROM build2test WHERE buildid='$buildid' AND status='failed'";
      $nfail_array = pdo_fetch_array(pdo_query($sql));
      $nfail = $nfail_array[0];
      
      // Add the difference
      $faildiff = pdo_query("SELECT difference FROM testdiff WHERE buildid='$buildid' AND type='1'");
      if(pdo_num_rows($faildiff)>0)
        {
        $faildiff_array = pdo_fetch_array($faildiff);
        $nfaildiff = $faildiff_array["difference"];
        $xml .= add_XML_value("nfaildiff",$nfaildiff);
        }
        
      $sql = "SELECT count(*) FROM build2test WHERE buildid='$buildid' AND status='passed'";
      $npass_array = pdo_fetch_array(pdo_query($sql));
      $npass = $npass_array[0];
      
      // Add the difference
      $passdiff = pdo_query("SELECT difference FROM testdiff WHERE buildid='$buildid' AND type='2'");
      if(pdo_num_rows($passdiff)>0)
        {
        $passdiff_array = pdo_fetch_array($passdiff);
        $npassdiff = $passdiff_array["difference"];
        $xml .= add_XML_value("npassdiff",$npassdiff);
        }
        
      if($project_array["showtesttime"] == 1)
        {
        $testtimemaxstatus = $project_array["testtimemaxstatus"];
        $sql = "SELECT count(*) FROM build2test WHERE buildid='$buildid' AND timestatus>='$testtimemaxstatus'";
        $ntimestatus_array = pdo_fetch_array(pdo_query($sql));
        $ntimestatus = $ntimestatus_array[0];
        $xml .= add_XML_value("timestatus",$ntimestatus);
        
        // Add the difference
        $timediff = pdo_query("SELECT difference FROM testdiff WHERE buildid='$buildid' AND type='3'");
        if(pdo_num_rows($timediff)>0)
          {
          $timediff_array = pdo_fetch_array($timediff);
          $ntimediff = $timediff_array["difference"];
          $xml .= add_XML_value("ntimediff",$ntimediff);
          }
        
        }  
      $time_array = pdo_fetch_array(pdo_query("SELECT SUM(time) FROM build2test WHERE buildid='$buildid'"));
      $time = $time_array[0];
      
      $totalnotrun += $nnotrun;
      $totalfail += $nfail;
      $totalpass += $npass;
      
      $xml .= add_XML_value("notrun",$nnotrun);
      $xml .= add_XML_value("fail",$nfail);
      $xml .= add_XML_value("pass",$npass);
      $xml .= add_XML_value("time",round($time/60,1));
      $xml .= "</test>";
      }
     
     $starttimestamp = strtotime($build_array["starttime"]." UTC");
     $submittimestamp = strtotime($build_array["submittime"]." UTC");
     $xml .= add_XML_value("builddate",date(FMT_DATETIMETZ,$starttimestamp)); // use the default timezone
     $xml .= add_XML_value("submitdate",date(FMT_DATETIMETZ,$submittimestamp));// use the default timezone
     $xml .= "</build>";
    
    // Coverage
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
                
      $xml .= "  <site>".$site_array["name"]."</site>";
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
      $xml .= "</coverage>";
      }  // end coverage
    
    // Dynamic Analysis
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
      $xml .= "  <site>".$site_array["name"]."</site>";
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
      $xml .= "</dynamicanalysis>";
      }  // end coverage   
    } // end looping through builds
             
  if(pdo_num_rows($builds)>0)
    {
    $xml .= add_expected_builds($groupid,$currentstarttime,$received_builds,$rowparity);
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
    $group = pdo_fetch_array(pdo_query("
      SELECT g.name,g.id FROM buildgroup AS g,buildgroupposition AS gp WHERE g.id=gp.buildgroupid 
      AND gp.position='$i' AND g.projectid='$projectid'
      AND gp.starttime<'$end_UTCDate' AND (gp.endtime>'$end_UTCDate'  OR gp.endtime='1980-01-01 00:00:00')"));

    $xml .= "<buildgroup>";  
    $xml .= add_XML_value("id",$group["id"]);
    $xml .= add_XML_value("name",$group["name"]);
    $xml .= add_XML_value("linkname",str_replace(" ","_",$group["name"]));
    $xml .= add_expected_builds($group["id"],$currentstarttime,$received_builds,$rowparity);
    if($i == $lastGroupPosition)
      {
      $xml .= "<last>1</last>";
      }
    $xml .= "</buildgroup>";  
    }

  $xml .= add_XML_value("totalConfigureError",$totalConfigureError);
  $xml .= add_XML_value("totalConfigureWarning",$totalConfigureWarning);
  
  $xml .= add_XML_value("totalError",$totalerrors);
  $xml .= add_XML_value("totalWarning",$totalwarnings);
 
  $xml .= add_XML_value("totalNotRun",$totalnotrun);
  $xml .= add_XML_value("totalFail",$totalfail);
  $xml .= add_XML_value("totalPass",$totalpass); 
   
  $end = microtime_float();
  $xml .= "<generationtime>".round($end-$start,3)."</generationtime>";
  $xml .= "</cdash>";

  return $xml;
} 


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

  $xml = generate_main_dashboard_XML($projectid,$date);

  // Now doing the xslt transition
  generate_XSLT($xml,"filters");
  }
?>
