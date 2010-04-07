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
  
  /** Return the coverage per directory */
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
    $query = pdo_query("SELECT YEAR(starttime) AS y ,MONTH(starttime) AS m,DAY(starttime) AS d,builderrors,buildwarnings,testnotrun,testfailed
                FROM build WHERE projectid=".$projectid." GROUP BY YEAR(starttime),MONTH(starttime),DAY(starttime) ORDER BY YEAR(starttime),MONTH(starttime),DAY(starttime) ASC LIMIT 1000"); // limit the request
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
    } // end function ListProjects
  
  /** Run function */
  function Run()
    {
    switch($this->Parameters['task'])
      {
      case 'defects': return $this->ListDefects();
      }
    } 
}

?>
