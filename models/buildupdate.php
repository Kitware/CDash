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
include_once('models/buildupdatefile.php');

class BuildUpdate
{
  private $Files;
  var $StartTime;
  var $EndTime;
  var $Command;
  var $Type;
  var $Status;
  var $Revision;
  var $PriorRevision;
  var $Path;
  var $BuildId;

  public function __construct()
    {
    $this->Files = array();
    $this->Command = "";
    }

  function AddFile($file)
    {
    $this->Files[] = $file;
    }

  // Insert the update
  function Insert()
    {
    if(strlen($this->BuildId)==0 || !is_numeric($this->BuildId))
      {
      echo "BuildUpdate:Insert BuildId not set";
      return false;
      }

    // Remove any previous updates
    $query = "DELETE FROM buildupdate WHERE buildid=".qnum($this->BuildId);
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildUpdate Insert",0,$this->BuildId);
      return false;
      }

    $query = "DELETE FROM updatefile WHERE buildid=".qnum($this->BuildId);
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildUpdate Insert",0,$this->BuildId);
      return false;
      }

    $this->StartTime = pdo_real_escape_string($this->StartTime);
    $this->EndTime = pdo_real_escape_string($this->EndTime);
    $this->Command = pdo_real_escape_string($this->Command);

    $this->Type = pdo_real_escape_string($this->Type);
    if(strlen($this->Type)>4)
      {
      $this->Type = 'NA';
      }
    $this->Status = pdo_real_escape_string($this->Status);
    $this->Revision = pdo_real_escape_string($this->Revision);
    $this->PriorRevision = pdo_real_escape_string($this->PriorRevision);
    $this->Path = pdo_real_escape_string($this->Path);

    $nfiles = count($this->Files);
    $nwarnings = 0;

    foreach($this->Files as $file)
      {
      if($file->Author == 'Local User' && $file->Revision==-1)
        {
        $nwarnings++;
        }
      }

    $query = "INSERT INTO buildupdate (buildid,starttime,endtime,command,type,status,nfiles,warnings,
                                       revision,priorrevision,path)
              VALUES (".qnum($this->BuildId).",'$this->StartTime','$this->EndTime','$this->Command',
                      '$this->Type','$this->Status',$nfiles,$nwarnings,
                      '$this->Revision','$this->PriorRevision','$this->Path')";
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildUpdate Insert",0,$this->BuildId);
      return false;
      }

    foreach($this->Files as $file)
      {
      $file->BuildId = $this->BuildId;
      $file->Insert();
      }
    return true;
    }  // end function insert()

  /** Get the number of errors for a build */
  function GetNumberOfErrors()
    {
    if(!$this->BuildId)
      {
      echo "BuildUpdate::GetNumberOfErrors(): BuildId not set";
      return false;
      }

    $buildid_clause = get_updates_buildid_clause($this->BuildId);

    $builderror = pdo_query("SELECT status FROM buildupdate WHERE ".$buildid_clause);
    $updatestatus_array = pdo_fetch_array($builderror);

    if(strlen($updatestatus_array["status"]) > 0 &&
       $updatestatus_array["status"]!="0")
      {
     return 1;
      }

    return 0;
    } // end GetNumberOfErrors()

}
?>
