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

/** Remove builds by their group-specific auto-remove timeframe setting */
function removeBuildsGroupwise($projectid, $maxbuilds)
{
  require_once('cdash/config.php');
  require_once('cdash/pdo.php');
  require_once('cdash/common.php');
  
  set_time_limit(0);

  $buildgroups = pdo_query('SELECT id,autoremovetimeframe FROM buildgroup WHERE projectid='.qnum($projectid));

  $buildids = array();
  while($buildgroup = pdo_fetch_array($buildgroups))
    {
    $days = $buildgroup['autoremovetimeframe'];
    
    if($days < 2)
      {
      continue;
      }
    $groupid = $buildgroup['id'];

    $cutoff = time()-3600*24*$days;
    $cutoffdate = date(FMT_DATETIME,$cutoff);

    $builds = pdo_query("SELECT build.id AS id FROM build,build2group WHERE build.starttime<'$cutoffdate'
      AND build2group.buildid=build.id AND build2group.groupid=".qnum($groupid).
      "ORDER BY build.starttime ASC LIMIT $maxbuilds");
    add_last_sql_error("autoremove::removeBuildsGroupwise");

    while($build = pdo_fetch_array($builds))
      {
      $buildids[] = $build['id'];
      }
    }

   $s = 'removing old buildids for projectid: '.$projectid;
   add_log($s, 'removeBuildsGroupwise');
   print "  -- " . $s . "\n";
   remove_build($buildids);  
}

/** Remove the first builds that are at the beginning of the queue */
function removeFirstBuilds($projectid, $days, $maxbuilds, $force=false)
{
  require("cdash/config.php");
  require_once("cdash/pdo.php");
  require_once("cdash/common.php");

  set_time_limit(0);

  if(!$force && !isset($CDASH_AUTOREMOVE_BUILDS))
    {
    return;
    }

  if(!$force && $CDASH_AUTOREMOVE_BUILDS!='1')
    {
    return;
    }

  if($days < 2)
    {
    return;
    }

  // First remove the builds with the wrong date
  $currentdate = time()-3600*24*$days; 
  $startdate = date(FMT_DATETIME,$currentdate);

  add_log('about to query for builds to remove', 'removeFirstBuilds');
  $builds = pdo_query("SELECT id FROM build WHERE starttime<'".$startdate."' AND projectid=".qnum($projectid)." ORDER BY starttime ASC LIMIT ".$maxbuilds);
  add_last_sql_error("dailyupdates::removeFirstBuilds");

  $buildids = array();
  while($builds_array = pdo_fetch_array($builds))
    {
    $buildids[] = $builds_array["id"];
    //$s = 'removing old buildid: '.$buildid.' projectid: '.$projectid;
    //remove_build($buildid);
    }

  add_log($s, 'removeFirstBuilds');
  print "  -- " . $s . "\n"; // for "interactive" command line feedback
  remove_build($buildids);    
}

?>
