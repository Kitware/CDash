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
include_once('models/constants.php');

class ClientJob
{
  var $Id;
  var $ScheduleId;
  var $OsId;
  var $SiteId;
  var $StartDate;
  var $EndDate;
  var $Status;
  var $CMakeId;
  var $CompilerId;
  var $Output;

  /** Get ScheduleId */
  // commenting out until it's actually used 
  /*
  function GetScheduleId()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetScheduleId()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT scheduleid FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
  */
    
  /** Get StartingDate */
  function GetStartDate()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetStartDate","Id not set");
      return;
      }
    $sys = pdo_query("SELECT startdate FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }

  /** Get End Date */
  function GetEndDate()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetEndDate","Id not set");
      return;
      }
    $sys = pdo_query("SELECT enddate FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
    
  /** Get Status */
  function GetStatus()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetStatus","Id not set");
      return;
      }
    $sys = pdo_query("SELECT status FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
  
  /** Get Site */
  function GetSite()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::GetSite","Id not set");
      return;
      }
    $sys = pdo_query("SELECT siteid FROM client_job WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
      
  /** Set the job has finished */
  function SetFinished()
    {
    $now = date('Y-m-d H:i:s');
    $sql = "UPDATE client_job SET status=".CDASH_JOB_FINISHED.",enddate='".$now."' WHERE siteid=".$this->SiteId." AND status=".CDASH_JOB_RUNNING;
    pdo_query($sql);
    add_last_sql_error("ClientJob::SetFinished");
    }

  /** Set the job has failed */
  function SetFailed()
    {
    $now = date('Y-m-d H:i:s');
    $sql = "UPDATE client_job SET status=".CDASH_JOB_FAILED.",enddate='".$now."' WHERE siteid=".$this->SiteId." AND status=".CDASH_JOB_RUNNING;
    pdo_query($sql);
    add_last_sql_error("ClientJob::SetFailed");
    }
    
  /** Save a job */  
  function Save()
    {     
    $sql = "INSERT INTO client_job (scheduleid,osid,siteid,startdate,enddate,status,output,cmakeid,compilerid) 
            VALUES ('".$this->ScheduleId."','".$this->OsId."','".$this->SiteId."','".$this->StartDate."','".$this->EndDate
            ."','".$this->Status."','".$this->Output."','".$this->CMakeId."','".$this->CompilerId."')";
    pdo_query($sql);
    $this->Id = pdo_insert_id('client_job');
    add_last_sql_error("ClientJob::Save");
    }   // end Save

  /** Remove a job */
  function Remove()
    {
    if(!$this->Id)
      {
      add_log("ClientJob::Remove","Id not set");
      return;
      }
    pdo_query("DELETE FROM client_job WHERE id=".qnum($this->Id));
    add_last_sql_error("ClientJob::Remove");
    }   // end Remove

} // end class proJob
