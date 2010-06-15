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
class DailyUpdate
{
  var $Id;
  var $Date;
  var $Command;
  var $Type;
  var $Status;
  var $ProjectId;
  
  function AddFile($file)
    {
    $file->DailyUpdateId = $this->Id;
    $file->Save();
    }
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "DATE": $this->Date = $value;break;
      case "COMMAND": $this->Command = $value;break;
      case "TYPE": $this->Type = $value;break;
      case "STATUS": $this->Status = $value;break;
      }
    }
    
  /** Check if exists */  
  function Exists()
    {
    // If no id specify return false
    if(!$this->Id || !$this->Date)
      {
      return false;    
      }
    
    $query = pdo_query("SELECT count(*) AS c FROM dailyupdate WHERE date='".$this->Date."' AND projectid='".$this->ProjectId."'");
    $query_array = pdo_fetch_array($query);
    if($query_array['c']==0)
      {
      return false;
      }
    
    return true;  
    }
    
  /** Save the group */
  function Save()
    {
    if(!$this->ProjectId)
      {
      echo "DailyUpdate::Save(): ProjectId not set!";
      return false;
      }
      
    if($this->Exists())
      {
      // Update the project
      $query = "UPDATE dailyupdate SET";
      $query .= " command='".$this->Command."'";
      $query .= ",type='".$this->Type."'";
      $query .= ",status='".$this->Status."'";
      $query .= " WHERE projectid='".$this->ProjectId."' AND date='".$this->Date."'";
      
      if(!pdo_query($query))
        {
        add_last_sql_error("DailyUpdate Update",$this->ProjectId);
        return false;
        }
      }
    else
      {                                              
      if(pdo_query("INSERT INTO dailyupdate (projectid,date,command,type,status)
                     VALUES ('$this->ProjectId','$this->Date','$this->Command','$this->Type','$this->Status')"))
         {
         $this->Id = pdo_insert_id("dailyupdate");
         }
       else
         {
         add_last_sql_error("DailyUpdate Insert",$this->ProjectId);
         return false;
         }
      }
  } // end function save
    
  /** Get all the authors of a file */
  function GetAuthors($filename,$onlylast=false)
    {
    if(!$this->ProjectId)
      {
      echo "DailyUpdate::GetAuthors(): ProjectId is not set<br>";
      return false;
      }
      
    // Check if the note already exists   
    $filename = pdo_real_escape_string($filename);
    
    // Remove
    if(substr($filename,0,2) == './')
      {
      $filename = substr($filename,2);
      }
    
    $sql = "";
    if($onlylast)
      {
      $sql = " ORDER BY dailyupdate.id DESC LIMIT 1";
      }
    
    $query = pdo_query("SELECT DISTINCT user2project.userid FROM user2project,dailyupdatefile,dailyupdate WHERE 
                        dailyupdatefile.dailyupdateid=dailyupdate.id AND dailyupdate.projectid=user2project.projectid
                        AND user2project.cvslogin=dailyupdatefile.author
                        AND user2project.projectid=".qnum($this->ProjectId)." AND dailyupdatefile.filename LIKE '%".$filename."'".$sql);                    
        
    if(!$query)
      {
      add_last_sql_error("DailyUpdate GetAuthors",$this->ProjectId);
      return false;
      }
    
    $authorids = array();
    while($query_array = pdo_fetch_array($query))
      {
      $authorids[] = $query_array['userid'];
      }   
    return $authorids;
    }
}
?>
