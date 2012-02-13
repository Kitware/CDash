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
    $query = pdo_query("SELECT updateid FROM build2update WHERE buildid=".qnum($this->BuildId));
    if(pdo_num_rows($query)>0)
      {
      $query_array = pdo_fetch_array($query);
      $updateid = $query_array['updateid'];

      $query = "DELETE FROM buildupdate WHERE id=".qnum($updateid);
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildUpdate Insert",0,$this->BuildId);
        return false;
        }

      $query = "DELETE FROM updatefile WHERE updateid=".qnum($updateid);
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildUpdate Insert",0,$this->BuildId);
        return false;
        }

      $query = "DELETE FROM build2update WHERE updateid=".qnum($updateid);
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildUpdate Insert",0,$this->BuildId);
        return false;
        }
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

    $query = "INSERT INTO buildupdate (starttime,endtime,command,type,status,nfiles,warnings,
                                       revision,priorrevision,path)
              VALUES ('$this->StartTime','$this->EndTime','$this->Command',
                      '$this->Type','$this->Status',$nfiles,$nwarnings,
                      '$this->Revision','$this->PriorRevision','$this->Path')";
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildUpdate Insert",0,$this->BuildId);
      return false;
      }

    $updateid = pdo_insert_id("buildupdate");
    $query = "INSERT INTO build2update (buildid,updateid)
              VALUES (".qnum($this->BuildId).",".qnum($updateid).")";
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildUpdate Insert",0,$this->BuildId);
      return false;
      }

    foreach($this->Files as $file)
      {
      $file->UpdateId = $updateid;
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

    $builderror = pdo_query("SELECT status FROM buildupdate AS u, build2update AS b2u WHERE u.id=b2u.updateid AND b2u.buildid=".qnum($this->BuildId));
    $updatestatus_array = pdo_fetch_array($builderror);

    if(strlen($updatestatus_array["status"]) > 0 &&
       $updatestatus_array["status"]!="0")
      {
     return 1;
      }

    return 0;
    } // end GetNumberOfErrors()


  /** Associate a buildupdate to a build. */
  function AssociateBuild($siteid, $name, $stamp)
    {
    if(!$this->BuildId)
      {
      echo "BuildUpdate::AssociateBuild(): BuildId not set";
      return false;
      }

    // Find the update id from a similar build
    $query = pdo_query("SELECT updateid FROM build2update AS b2u, build AS b
                        WHERE b.id=b2u.buildid AND b.stamp='".$stamp."'
                          AND b.siteid=".qnum($siteid)." AND b.name='".$name."'
                          AND b.id!=".qnum($this->BuildId));
    if(!$query)
      {
      add_last_sql_error("BuildUpdate AssociateBuild",0,$this->BuildId);
      return false;
      }
    if(pdo_num_rows($query)>0)
      {
      $query_array = pdo_fetch_array($query);
      $updateid = $query_array['updateid'];

      pdo_query("INSERT INTO build2update (buildid,updateid) VALUES
                   (".qnum($this->BuildId).",".qnum($updateid));
      }
     return true;
    } // end AssociateBuild()
}
?>
