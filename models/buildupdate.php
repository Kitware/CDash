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
// It is assumed that appropriate headers should be included before including this file
class BuildUpdateFile
{
  var $Filename;
  var $CheckinDate;
  var $Author;
  var $Email;
  var $Log;  
  var $Revision;  
  var $PriorRevision;
  var $BuildId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "FILENAME": $this->Filename = $value;break;
      case "CHECKINDATE": $this->CheckinDate = $value;break;
      case "AUTHOR": $this->Author = $value;break;
      case "EMAIL": $this->Email = $value;break;
      case "LOG": $this->Log = $value;break;
      case "REVISION": $this->Revision = $value;break;
      case "PRIORREVISION": $this->PriorRevision = $value;break;    
      }
    } 
    
  // Insert the update
  function Insert()
    {
    if(strlen($this->BuildId)==0)
      {
      echo "BuildUpdateFile:Insert BuildId not set";
      return false;
      }

    $this->Filename = pdo_real_escape_string($this->Filename);
    $this->CheckinDate = pdo_real_escape_string($this->CheckinDate);
    $this->Author = pdo_real_escape_string($this->Author);
    $this->Email = pdo_real_escape_string($this->Email);
    $this->Log = pdo_real_escape_string($this->Log);
    $this->Revision = pdo_real_escape_string($this->Revision);
    $this->PriorRevision = pdo_real_escape_string($this->PriorRevision);
    $this->BuildId = pdo_real_escape_string($this->BuildId);
    
    $query = "INSERT INTO updatefile (buildid,filename,checkindate,author,email,log,revision,priorrevision)
              VALUES (".qnum($this->BuildId).",'$this->Filename','$this->CheckinDate','$this->Author','$this->Email',
                      '$this->Log','$this->Revision','$this->PriorRevision')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildUpdateFile Insert");
      return false;
      }
    } // end function insert
}

class BuildUpdate
{
  private $Files;
  var $StartTime;
  var $EndTime;
  var $Command;
  var $Type;
  var $Status;
  var $BuildId;
  
  function AddFile($file)
    {
    $this->Files[] = $file;
    }
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "STARTTIME": $this->StartTime = $value;break;
      case "ENDTIME": $this->EndTime = $value;break;
      case "COMMAND": $this->Command = $value;break;
      case "TYPE": $this->Type = $value;break;
      case "STATUS": $this->Status = $value;break;
      }
    } 
    
  // Insert the update
  function Insert()
    {
    if(strlen($this->BuildId)==0)
      {
      echo "BuildUpdate:Insert BuildId not set";
      return false;
      }
      
    // Remove any previous updates
    $query = "DELETE FROM buildupdate WHERE buildid=".qnum($this->BuildId);
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildUpdate Insert");
      return false;
      }  
  
    $query = "DELETE FROM updatefile WHERE buildid=".qnum($this->BuildId);
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildUpdate Insert");
      return false;
      } 
          
    $this->StartTime = pdo_real_escape_string($this->StartTime);
    $this->EndTime = pdo_real_escape_string($this->EndTime);
    $this->Command = pdo_real_escape_string($this->Command);
    $this->Type = pdo_real_escape_string($this->Type);
    $this->Status = pdo_real_escape_string($this->Status);
    $this->BuildId = pdo_real_escape_string($this->BuildId);
        
    $query = "INSERT INTO buildupdate (buildid,starttime,endtime,command,type,status)
              VALUES (".qnum($this->BuildId).",'$this->StartTime','$this->EndTime','$this->Command',
                      '$this->Type','$this->Status')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildUpdate Insert");
      return false;
      }  
 
    // Add errors/warnings
    foreach($this->Files as $file)
      {
      $file->BuildId = $this->BuildId;
      $file->Insert();
      }
    return true;     
    }  // end function insert()
}
?>
