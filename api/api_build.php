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
// Return a tree of coverage directory with the number of line covered
// and not covered
include_once('api.php');

class BuildAPI extends CDashAPI
{
  
  /** Return the defects: builderrors, buildwarnings, testnotrun, testfailed. */
  private function ListDefects()
    { 
    include_once('../cdash/common.php');  
    if(!isset($this->Parameters['project']))  
      {
      echo "Project not set";
      return;
      }
      
    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid))
      {
      echo "Project not found";
      return;
      }

    $builds = array();
    
    if($CDASH_DB_TYPE == "pgsql") 
      {
      $query = pdo_query("SELECT EXTRACT(YEAR FROM starttime) AS y ,
                              EXTRACT(MONTH FROM starttime) AS m,
                              EXTRACT(DAY FROM starttime) AS d,
                  AVG(builderrors) AS builderrors,AVG(buildwarnings) AS buildwarnings,
                  AVG(testnotrun) AS testnotrun,AVG(testfailed) AS testfailed
                  FROM build WHERE projectid=".$projectid." 
                  AND starttime<NOW()
                  GROUP BY y,m,d 
                  ORDER BY y,m,d ASC LIMIT 1000"); // limit the request  
      }
    else 
      { 
      $query = pdo_query("SELECT YEAR(starttime) AS y ,MONTH(starttime) AS m,DAY(starttime) AS d,
                  AVG(builderrors) AS builderrors,AVG(buildwarnings) AS buildwarnings,
                  AVG(testnotrun) AS testnotrun,AVG(testfailed) AS testfailed
                  FROM build WHERE projectid=".$projectid." 
                  AND starttime<NOW()
                  GROUP BY YEAR(starttime),MONTH(starttime),DAY(starttime) 
                  ORDER BY YEAR(starttime),MONTH(starttime),DAY(starttime) ASC LIMIT 1000"); // limit the request
      }
      
    echo pdo_error();
     
    while($query_array = pdo_fetch_array($query))
      {
      $build['month'] = $query_array['m'];
      $build['day'] = $query_array['d'];
      $build['year'] = $query_array['y'];
      $build['time'] = strtotime($query_array['y'].'-'.$query_array['m'].'-'.$query_array['d']);
      
      $build['builderrors'] = 0;
      if($query_array['builderrors']>=0)
        {
        $build['builderrors'] = $query_array['builderrors'];
        }
      $build['buildwarnings'] = 0;
      if($query_array['buildwarnings']>=0)
        {
        $build['buildwarnings'] = $query_array['buildwarnings'];
        }
      $build['testnotrun'] = 0;
      if($query_array['testnotrun']>=0)
        {
        $build['testnotrun'] = $query_array['testnotrun'];
        }
      $build['testfailed'] = 0;
      if($query_array['testfailed']>=0)
        {
        $build['testfailed'] = $query_array['testfailed'];
        }
      $builds[] = $build;  
      }
    return $builds;
    } // end function ListDefects
  
    
  /** Return the number of defects per number of checkins */
  private function ListCheckinsDefects()
    { 
    include_once('../cdash/common.php');  
    if(!isset($this->Parameters['project']))  
      {
      echo "Project not set";
      return;
      }
      
    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid))
      {
      echo "Project not found";
      return;
      }

    $builds = array();
    $query = pdo_query("SELECT nfiles, builderrors, buildwarnings, testnotrun, testfailed
                FROM build,buildupdate WHERE build.projectid=".$projectid." 
                AND buildupdate.buildid=build.id
                AND nfiles>0
                AND build.starttime<NOW()
                ORDER BY build.starttime DESC LIMIT 1000"); // limit the request
    echo pdo_error();
    
    while($query_array = pdo_fetch_array($query))
      {
      $build['nfiles'] = $query_array['nfiles'];   
      $build['builderrors'] = 0;
      if($query_array['builderrors']>=0)
        {
        $build['builderrors'] = $query_array['builderrors'];
        }
      $build['buildwarnings'] = 0;
      if($query_array['buildwarnings']>=0)
        {
        $build['buildwarnings'] = $query_array['buildwarnings'];
        }
      $build['testnotrun'] = 0;
      if($query_array['testnotrun']>=0)
        {
        $build['testnotrun'] = $query_array['testnotrun'];
        }
      $build['testfailed'] = 0;
      if($query_array['testfailed']>=0)
        {
        $build['testfailed'] = $query_array['testfailed'];
        }
      $builds[] = $build;  
      }
    return $builds;
    } // end function ListCheckinsDefects 
    
    
  /** Return an array with two sub arrays:
   *  array1: id, buildname, os, bits, memory, frequency 
   *  array2: array1_id, test_fullname */
  private function ListSiteTestFailure()
    { 
    include_once('../cdash/common.php');  
    if(!isset($this->Parameters['project']))  
      {
      echo "Project not set";
      return;
      }
      
    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid))
      {
      echo "Project not found";
      return;
      }   

    $group = 'Nightly';      
    if(isset($this->Parameters['dsfdsgroup']))
      {
      $group = pdo_real_escape_string($this->Parameters['group']);
      }
      
    // Get first all the unique builds for today's dashboard and group
    $query = pdo_query("SELECT nightlytime FROM project WHERE id=".qnum($projectid));
    $project_array = pdo_fetch_array($query);
    
    $date = date("Y-m-d");
    list ($previousdate, $currentstarttime, $nextdate) = get_dates($date,$project_array["nightlytime"]); 


    // Get all the unique builds for the section of the dashboard
    $query = pdo_query("SELECT max(b.id) AS buildid,CONCAT(s.name,'-',b.name) AS fullname,s.name AS sitename,b.name,
               si.totalphysicalmemory,si.processorclockfrequency
               FROM build AS b, site AS s, siteinformation AS si, buildgroup AS bg, build2group AS b2g
               WHERE b.projectid=".$projectid." AND b.siteid=s.id AND si.siteid=s.id 
               AND bg.name='".$group."' AND b.testfailed>0 AND b2g.buildid=b.id AND b2g.groupid=bg.id
               AND b.starttime>$currentstarttime AND b.starttime<NOW() GROUP BY fullname ORDER BY buildid");

    $sites = array();
    $buildids = '';
    while($query_array = pdo_fetch_array($query))
      {
      if($buildids != '')
        {
        $buildids.=",";
        }  
      $buildids .= $query_array['buildid'];    
      $site = array();
      $site['name'] = $query_array['sitename'];
      $site['buildname'] = $query_array['name'];
      $site['cpu'] = $query_array['processorclockfrequency'];
      $site['memory'] = $query_array['totalphysicalmemory'];
      $sites[$query_array['buildid']] = $site;
      } 

    $query = pdo_query("SELECT bt.buildid AS buildid,t.name AS testname,t.id AS testid
              FROM build2test AS bt,test as t
              WHERE bt.buildid IN (".$buildids.") AND bt.testid=t.id AND bt.status='failed'");

    $tests = array();
    
    while($query_array = pdo_fetch_array($query))
      {
      $test = array();
      $test['id'] = $query_array['testid']; 
      $test['name'] = $query_array['testname'];  
      $sites[$query_array['buildid']]['tests'][] = $test;
      }

    return $sites;
    } // end function ListCheckinsDefects 
    
    
  /** Run function */
  function Run()
    {
    switch($this->Parameters['task'])
      {
      case 'defects': return $this->ListDefects();
      case 'checkinsdefects': return $this->ListCheckinsDefects();
      case 'sitetestfailures': return $this->ListSiteTestFailure();
      }
    } 
}

?>
