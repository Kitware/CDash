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
  var $Id;
  var $Name;
  var $StartTime;
  var $EndTime;
  var $Description;
  var $SummaryEmail;
  var $ProjectId;

  function __construct()
    {
    $this->StartTime = '1980-01-01 00:00:00';
    $this->EndTime = '1980-01-01 00:00:00';
    $this->SummaryEmail = 0;
    }
  
  function SetPosition($position)
    {
    $position->GroupId = $this->Id;
    $position->Add();
    }
    
  function AddRule($rule)
    {
    $rule->GroupId = $this->Id;
    $rule->Add();
    }
    
  /** Get the next position available for that group */
  function GetNextPosition()
    {
    $query = pdo_query("SELECT bg.position FROM buildgroupposition as bg,buildgroup as g 
                        WHERE bg.buildgroupid=g.id AND g.projectid='".$this->ProjectId."' 
                        AND bg.endtime='1980-01-01 00:00:00'
                        ORDER BY bg.position DESC LIMIT 1");
    if(pdo_num_rows($query)>0)
      {
      $query_array = pdo_fetch_array($query);
      return $query_array['position']+1;
      }
    return 1;    
    }  
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "NAME": $this->Name = $value;break;
      case "DESCRIPTION": $this->Description = $value;break;
      case "STARTTIME": $this->StartTime = $value;break;
      case "ENDTIME": $this->EndTime = $value;break;
      case "SUMMARYEMAIL": $this->SummaryEmail = $value;break;
      case "PROJECTID": $this->ProjectId = $value;break;  
      }
    }
    
  /** Check if the group already exists */  
  function Exists()
    {
    // If no id specify return false
    if(!$this->Id || !$this->ProjectId)
      {
      return false;    
      }
    
    $query = pdo_query("SELECT count(*) FROM buildgroup WHERE id='".$this->Id."' AND projectid='".$this->ProjectId."'");
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']==0)
      {
      return false;
      }
    
    return true;  
    }
    
  /** Save the group */
  function Save()
    {
    if($this->Exists())
      {
      // Update the project
      $query = "UPDATE buildgroup SET";
      $query .= " name='".$this->Name."'";
      $query .= ",projectid='".$this->ProjectId."'";
      $query .= ",starttime='".$this->StartTime."'";
      $query .= ",endtime='".$this->EndTime."'";
      $query .= ",description='".$this->Description."'";
      $query .= ",summaryemail='".$this->SummaryEmail."'";
      $query .= " WHERE id='".$this->Id."'";
      
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildGroup Update");
        return false;
        }
      }
    else
      {
      $id = "";
      $idvalue = "";
      if($this->Id)
        {
        $id = "id,";
        $idvalue = "'".$this->Id."',";
        }
                                                      
      if(pdo_query("INSERT INTO buildgroup (".$id."name,projectid,starttime,endtime,description)
                     VALUES (".$idvalue."'$this->Name','$this->ProjectId','$this->StartTime','$this->EndTime','$this->Description')"))
         {
         $this->Id = pdo_insert_id("buildgroup");
         }
       else
         {
         add_last_sql_error("Buildgroup Insert");
         return false;
         }
    
      // Insert the default position for this group
      // Find the position for this group
      $position = $this->GetNextPosition();               
      pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime) 
                 VALUES ('".$this->Id."','".$position."','".$this->StartTime."','".$this->EndTime."')");
 
      }  
    } // end function save
  
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
    
  // Return the value of the summary eamil
  function GetSummaryEmail()
    {
    if(!$this->Id)
      {
      echo "BuildGroup GetSummaryEmail(): Id not set";
      return false;
      }
    $summaryemail = pdo_query("SELECT summaryemail FROM buildgroup WHERE id=".qnum($this->Id));
    if(!$summaryemail)
      {
      add_last_sql_error("BuildGroup GetSummaryEmail");
      return false;
      }
      
    $summaryemail_array = pdo_fetch_array($summaryemail);
    return $summaryemail_array["summaryemail"];
    }  
}

?>