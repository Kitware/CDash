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
include_once("cdash/common.php");

set_time_limit(0);

/** Remove the first builds that are at the beginning of the queue */
function removeFirstBuilds($projectid,$days,$maxbuilds,$force=false)
{
  add_log("removeFirstBuilds",$projectid);
    
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
  
  $builds = pdo_query("SELECT id FROM build WHERE starttime<'".$startdate."' AND projectid=".qnum($projectid)." ORDER BY starttime ASC LIMIT ".$maxbuilds);
  add_last_sql_error("dailyupdates::removeFirstBuilds");
  while($builds_array = pdo_fetch_array($builds))
    {
    $buildid = $builds_array["id"];
    add_log("[REMOVE OLD BUILDS] for projectid: ".$projectid,$buildid);
    //remove_build($buildid); 
    }
}

?>
