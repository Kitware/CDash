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

class UserAPI extends CDashAPI
{
  /** List Defects */ 
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
 
    // We need multiple queries (4 to be exact) 
    // First for the build failures/fixes
    $users = array();
    $query = pdo_query("SELECT SUM(errors) AS nerrors,SUM(fixes) AS nfixes,SUM(nfiles) AS nfiles,author FROM(
            SELECT b.id,bed.difference_positive AS errors,bed.difference_negative AS fixes,u.author,
            COUNT(u.author) AS nfiles, COUNT(DISTINCT u.author) AS dauthor
            FROM build2group AS b2g, buildgroup AS bg,updatefile AS u,builderrordiff AS bed, build AS b
            WHERE b.projectid=1 AND u.buildid=b.id AND b2g.buildid=b.id AND b2g.groupid=bg.id AND bg.name!='Experimental'
            AND bed.buildid=b.id AND ((bed.difference_positive>0 AND bed.difference_negative!=bed.difference_positive) 
            OR (bed.difference_negative>0 AND bed.difference_positive<bed.difference_negative))
            AND b.starttime<NOW()
            GROUP BY b.id HAVING dauthor=1) AS q GROUP BY author");
     echo pdo_error();
     
     while($query_array = pdo_fetch_array($query))
       {
       $users[$query_array['author']]['builderrors'] = $query_array['nerrors'];
       $users[$query_array['author']]['buildfixes'] = $query_array['nfixes'];
       $users[$query_array['author']]['buildnfiles'] = $query_array['nfiles'];
       }
      
     // Then for the test failures
     $query = pdo_query("SELECT SUM(testerrors) AS ntesterrors,SUM(nfiles) AS nfiles,author FROM(SELECT b.id, td.difference_positive AS testerrors,
              u.author,COUNT(u.author) AS nfiles, COUNT(DISTINCT u.author) AS dauthor
              FROM build2group AS b2g, buildgroup AS bg,updatefile AS u, build AS b, testdiff AS td
              WHERE b.projectid=1 AND u.buildid=b.id AND b2g.buildid=b.id AND b2g.groupid=bg.id AND bg.name!='Experimental'
              AND td.buildid=b.id AND td.difference_positive>0 AND td.type=1
              AND b.starttime<NOW()
              GROUP BY b.id HAVING dauthor=1) AS q GROUP BY author");
     echo pdo_error();
     while($query_array = pdo_fetch_array($query))
       {
       $users[$query_array['author']]['testerrors'] = $query_array['ntesterrors'];
       $users[$query_array['author']]['testerrorsnfiles'] = $query_array['nfiles'];
       }
       
     // Then for the test fixes        
     $query = pdo_query("SELECT SUM(testfixes) AS ntestfixes,SUM(nfiles) AS nfiles,author FROM(SELECT b.id, td.difference_positive AS testfixes,
              u.author,COUNT(u.author) AS nfiles, COUNT(DISTINCT u.author) AS dauthor
              FROM build2group AS b2g, buildgroup AS bg,updatefile AS u, build AS b, testdiff AS td
              WHERE b.projectid=1 AND u.buildid=b.id AND b2g.buildid=b.id AND b2g.groupid=bg.id AND bg.name!='Experimental'
              AND td.buildid=b.id AND td.difference_positive>0 AND td.type=2 AND td.difference_negative=0
              AND b.starttime<NOW()
              GROUP BY b.id HAVING dauthor=1) AS q GROUP BY author");
     echo pdo_error();
     while($query_array = pdo_fetch_array($query))
       {
       $users[$query_array['author']]['testfixes'] = $query_array['ntestfixes'];
       $users[$query_array['author']]['testfixesnfiles'] = $query_array['nfiles'];
       }
               
     // Another select for neutral
     $query = pdo_query("SELECT b.id, bed.difference_positive AS errors,
          u.author AS author,count(*) AS nfiles
         FROM build2group AS b2g, buildgroup AS bg,updatefile AS u, build AS b
         LEFT JOIN builderrordiff AS bed ON (bed.buildid=b.id AND difference_positive!=difference_negative)
         LEFT JOIN testdiff AS t ON (t.buildid=b.id)
         WHERE b.projectid=1 AND u.buildid=b.id AND b2g.buildid=b.id AND b2g.groupid=bg.id AND bg.name!='Experimental'
         AND bed.difference_positive IS NULL
         AND t.difference_positive IS NULL
         AND b.starttime<NOW() GROUP BY u.author");
    echo pdo_error();       
    
    while($query_array = pdo_fetch_array($query))
       {
       $users[$query_array['author']]['neutralnfiles'] = $query_array['nfiles'];
       }        
    return $users;
    } // end function ListDefects 
    
    
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
