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
      exit();
      }
      
    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid))
      {
      echo "Project not found";
      exit();
      }

    $builds = array();
    $query = pdo_query("SELECT YEAR(starttime) AS y ,MONTH(starttime) AS m,DAY(starttime) AS d,
                AVG(builderrors) AS builderrors,AVG(buildwarnings) AS buildwarnings,
                AVG(testnotrun) AS testnotrun,AVG(testfailed) AS testfailed
                FROM build WHERE projectid=".$projectid." 
                AND starttime<NOW()
                GROUP BY YEAR(starttime),MONTH(starttime),DAY(starttime) 
                ORDER BY YEAR(starttime),MONTH(starttime),DAY(starttime) ASC LIMIT 1000"); // limit the request
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
      exit();
      }
      
    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid))
      {
      echo "Project not found";
      exit();
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
      exit();
      }
      
    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid))
      {
      echo "Project not found";
      exit();
      }   
    
    $query = pdo_query("SELECT s.name AS sitename,b.name,si.totalphysicalmemory,si.processorclockfrequency,
              t.name AS testname
              FROM site AS s, test AS t, build AS b, build2test AS bt, siteinformation AS si
              WHERE b.projectid=".$projectid." AND b.siteid=s.id AND si.siteid=s.id
              AND bt.buildid=b.id AND bt.testid=t.id AND bt.status='failed'
              AND b.starttime<NOW()
                    ORDER BY b.starttime DESC LIMIT 10"); // limit the request
    echo pdo_error();

    $sites = array();
    $tests = array();
    
    while($query_array = pdo_fetch_array($query))
      {
      $sitename = $query_array['sitename'];
      $buildname = $query_array['name'];
        
      // Check if the sites is not already there
      $siteid = false;
      foreach($sites as $key => $site)
        {
        if($site['name']==$sitename && $site['buildname']==$buildname)
          {
          $siteid = $key;
          break;  
          }
        }

      if($siteid === false)
        {
        $site = array();
        $site['name'] = $sitename;
        $site['buildname'] = $buildname;
        $site['cpu'] = $query_array['processorclockfrequency'];
        $site['memory'] = $query_array['totalphysicalmemory'];
        $sites[] = $site;
        $siteid = count($sites)-1;    
        }
      $tests[$siteid][] = $query_array['testname'];
      }

    $returnarray = array();
    $returnarray['sites'] = $sites;
    $returnarray['tests'] = $tests;

    return $returnarray;
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
