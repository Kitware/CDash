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
include_once('build.php');

class BuildGroup
{
  function GetGroupIdFromRule($build)
    {
    $name = $build->Name;
    $type = $build->Type;
    $siteid = $build->SiteId;
    $starttime = $build->StartTime;
    $projectid = $build->ProjectId;
    
    // Insert the build into the proper group
    // 1) Check if we have any build2grouprules for this build
    $build2grouprule = pdo_query("SELECT b2g.groupid FROM build2grouprule AS b2g, buildgroup as bg
                                  WHERE b2g.buildtype='$type' AND b2g.siteid='$siteid' AND b2g.buildname='$name'
                                  AND (b2g.groupid=bg.id AND bg.projectid='$projectid') 
                                  AND '$starttime'>b2g.starttime 
                                  AND ('$starttime'<b2g.endtime OR b2g.endtime='1980-01-01 00:00:00')");
                                        
    if(pdo_num_rows($build2grouprule)>0)
      {
      $build2grouprule_array = pdo_fetch_array($build2grouprule);
      return $build2grouprule_array["groupid"];
      }
    else // we don't have any rules we use the type 
      {
      $buildgroup = pdo_query("SELECT id FROM buildgroup WHERE name='$type' AND projectid='$projectid'");
      $buildgroup_array = pdo_fetch_array($buildgroup);
      return $buildgroup_array["id"];
      }
    }
}
?>

