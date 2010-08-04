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

// To be able to access files in this CDash installation regardless
// of getcwd() value:
//
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

// Return a tree of coverage directory with the number of line covered
// and not covered
include_once('api.php');

class ProjectAPI extends CDashAPI
{
  
  /** Return the coverage per directory */
  private function ListProjects()
    {
    include_once('../cdash/common.php');  
    $query = pdo_query("SELECT id,name FROM project WHERE public=1 ORDER BY name ASC"); 
    while($query_array = pdo_fetch_array($query))
      {
      $project['id'] = $query_array['id'];
      $project['name'] = $query_array['name'];
      $projects[] = $project;  
      }
    return $projects;
    } // end function ListProjects
  
  /** Run function */
  function Run()
    {
    switch($this->Parameters['task'])
      {
      case 'list': return $this->ListProjects();
      }
    } 
}

?>
