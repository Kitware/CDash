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
  var $Status; //MODIFIED | CONFLICTING | UPDATED
  var $BuildId;

  // Insert the update
  function Insert()
    {
    if(strlen($this->BuildId)==0)
      {
      echo "BuildUpdateFile:Insert BuildId not set";
      return false;
      }

    $this->Filename = pdo_real_escape_string($this->Filename);
    
    // Sometimes the checkin date is not found in that case we put the usual date
    if($this->CheckinDate == "Unknown")
      {
      $this->CheckinDate = "1980-01-01";
      }
      
    if(strtotime($this->CheckinDate) === false && is_numeric($this->CheckinDate))
      {
      $this->CheckinDate = date(FMT_DATETIME,$this->CheckinDate);
      }
    else if(strtotime($this->CheckinDate) !== false)
      {  
      $this->CheckinDate = date(FMT_DATETIME,strtotime($this->CheckinDate));
      }
    else
      {
      $this->CheckinDate = "1980-01-01"; 
      }  
    $this->Author = pdo_real_escape_string($this->Author);

    // Check if we have a robot file for this build
    $robot = pdo_query("SELECT authorregex FROM projectrobot,build 
                WHERE projectrobot.projectid=build.projectid
                AND build.id=".qnum($this->BuildId)." AND robotname='".$this->Author."'");

    if(pdo_num_rows($robot)>0)
      {
      $robot_array = pdo_fetch_array($robot);
      $regex = $robot_array['authorregex'];
      preg_match($regex,$this->Log,$matches);
      if(isset($matches[1]))
        {
        $this->Author = $matches[1];
        }
      }

    $this->Email = pdo_real_escape_string($this->Email);
    $this->Log = pdo_real_escape_string($this->Log);
    $this->Revision = pdo_real_escape_string($this->Revision);
    $this->PriorRevision = pdo_real_escape_string($this->PriorRevision);
    $this->BuildId = pdo_real_escape_string($this->BuildId);

    $query = "INSERT INTO updatefile (buildid,filename,checkindate,author,email,log,revision,priorrevision,status)
              VALUES (".qnum($this->BuildId).",'$this->Filename','$this->CheckinDate','$this->Author','$this->Email',
                      '$this->Log','$this->Revision','$this->PriorRevision','$this->Status')";
    
    
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildUpdateFile Insert",0,$this->BuildId);
      return false;
      }
    } // end function insert
}

?>
