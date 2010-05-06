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
include_once('models/dynamicanalysisdefect.php');
include_once('models/label.php');

class DynamicAnalysis
{
  var $Id;
  var $Status;
  var $Checker;
  var $Name;
  var $Path;
  var $FullCommandLine;
  var $Log;
  private $Defects;
  var $BuildId;
  var $Labels;


  /** Add a defect */
  function AddDefect($defect)
    {
    $defect->DynamicAnalysisId = $this->Id;
    $this->Defects[] = $defect;
    }


  function AddLabel($label)
    {
    $label->DynamicAnalysisId = $this->Id;
    $this->Labels[] = $label;
    }


  /** Remove all the dynamic analysis associated with a buildid */
  function RemoveAll()
    {
    include("cdash/config.php");
    
    if(strlen($this->BuildId)==0)
      {
      echo "DynamicAnalysis::RemoveAll BuildId not set";
      return false;
      } 

    if($CDASH_DB_TYPE == 'pgsql') // postgresql doesn't support multiple delete
      {
      $query = "BEGIN";
      $query = "DELETE FROM dynamicanalysisdefect WHERE dynamicanalysis.buildid=".qnum($this->BuildId)."
                AND dynamicanalysis.id=dynamicanalysisdefect.dynamicanalysisid";
      $query = "DELETE FROM dynamicanalysis WHERE dynamicanalysis.buildid=".qnum($this->BuildId);
      $query = "COMMIT";
      }
    else
      {
      $query = "DELETE dynamicanalysisdefect,dynamicanalysis FROM dynamicanalysisdefect, dynamicanalysis 
               WHERE dynamicanalysis.buildid=".qnum($this->BuildId)."
               AND dynamicanalysis.id=dynamicanalysisdefect.dynamicanalysisid";
      }
    
    if(!pdo_query($query))
      {
      add_last_sql_error("DynamicAnalysis RemoveAll",0,$this->BuildId);
      return false;
      }

    $query = "DELETE FROM dynamicanalysis WHERE buildid=".qnum($this->BuildId);
    if(!pdo_query($query))
      {
      add_last_sql_error("DynamicAnalysis RemoveAll",0,$this->BuildId);
      return false;
      }
        
    }

  /** Insert labels */
  function InsertLabelAssociations()
    {
    if(empty($this->Labels))
      {
      return;
      }
      
    if($this->Id)
      {
      foreach($this->Labels as $label)
        {
        $label->DynamicAnalysisId = $this->Id;
        $label->Insert();
        }
      }
    else
      {
      add_log('No DynamicAnalysis::Id - cannot call $label->Insert...',
              'DynamicAnalysis::InsertLabelAssociations',LOG_ERR,
              0,$this->BuildId,CDASH_OBJECT_DYNAMICANALYSIS,$this->Id);
      }
    }

  // Insert the DynamicAnalysis
  function Insert()
    {
    if(strlen($this->BuildId)==0)
      {
      echo "DynamicAnalysis::Insert BuildId not set";
      return false;
      } 

    $id = "";
    $idvalue = "";
    if($this->Id)
      {
      $id = "id,";
      $idvalue = qnum($this->Id).",";
      }
        
    $this->Status = pdo_real_escape_string($this->Status);
    $this->Checker = pdo_real_escape_string($this->Checker);
    $this->Name = pdo_real_escape_string($this->Name);
    $path = pdo_real_escape_string(substr($this->Path,0,255));
    $fullCommandLine = pdo_real_escape_string(substr($this->FullCommandLine,0,255));
    $this->Log = pdo_real_escape_string($this->Log);
    $this->BuildId = pdo_real_escape_string($this->BuildId);
    
    $query = "INSERT INTO dynamicanalysis (".$id."buildid,status,checker,name,path,fullcommandline,log)
              VALUES (".$idvalue.qnum($this->BuildId).",'$this->Status','$this->Checker','$this->Name','".$path."',
                      '".$fullCommandLine."','$this->Log')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("DynamicAnalysis Insert",0,$this->BuildId);
      return false;
      }
    
    $this->Id = pdo_insert_id("dynamicanalysis");
    
    // Add the defects
    if(!empty($this->Defects))
      {
      foreach($this->Defects as $defect)
        {
        $defect->DynamicAnalysisId = $this->Id;
        $defect->Insert();
        }
      }    

    // Add the labels
    $this->InsertLabelAssociations();

    return true;
    } // end function insert
}
?>
