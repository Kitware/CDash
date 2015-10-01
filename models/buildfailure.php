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
include_once('models/label.php');

/** BuildFailure */
class buildfailure
{
    public $BuildId;
    public $Type;
    public $WorkingDirectory;
    public $Arguments;
    public $StdOutput;
    public $StdError;
    public $ExitCondition;
    public $Language;
    public $TargetName;
    public $SourceFile;
    public $OutputFile;
    public $OutputType;
    public $Labels;

    public function __construct()
    {
        $this->Arguments = array();
    }

    public function AddLabel($label)
    {
        if (!isset($this->Labels)) {
            $this->Labels = array();
        }

        $this->Labels[] = $label;
    }


  // Add an argument to the buildfailure
  public function AddArgument($argument)
  {
      $this->Arguments[]  = $argument;
  }


    public function InsertLabelAssociations($id)
    {
        if (empty($this->Labels)) {
            return;
        }
      
        if ($id) {
            foreach ($this->Labels as $label) {
                $label->BuildFailureId = $id;
                $label->Insert();
            }
        } else {
            add_log('No BuildFailure id - cannot call $label->Insert...',
              'BuildFailure::InsertLabelAssociations', LOG_ERR, 0, $this->BuildId);
        }
    }

  // Insert in the database (no update possible)
  public function Insert()
  {
      if (!$this->BuildId) {
          echo "BuildFailure::Insert(): BuildId not set<br>";
          return false;
      }
    
      $workingDirectory = pdo_real_escape_string($this->WorkingDirectory);
      $stdOutput = pdo_real_escape_string($this->StdOutput);
      $stdError = pdo_real_escape_string($this->StdError);
      $exitCondition = pdo_real_escape_string($this->ExitCondition);
      $language = pdo_real_escape_string($this->Language);
      $targetName = pdo_real_escape_string($this->TargetName);
      $outputFile = pdo_real_escape_string($this->OutputFile);
      $outputType = pdo_real_escape_string($this->OutputType);
      $sourceFile = pdo_real_escape_string($this->SourceFile);

    // Compute the crc32
    $crc32 = crc32($outputFile.$stdOutput.$stdError.$sourceFile);
      $query = "INSERT INTO buildfailure (buildid,type,workingdirectory,stdoutput,stderror,exitcondition,
              language,targetname,outputfile,outputtype,sourcefile,newstatus,crc32)
              VALUES (".qnum($this->BuildId).",".qnum($this->Type).",'$workingDirectory',
              '$stdOutput','$stdError','$exitCondition',
              '$language','$targetName','$outputFile','$outputType','$sourceFile',0,".qnum($crc32).")";
      if (!pdo_query($query)) {
          add_last_sql_error("BuildFailure Insert", 0, $this->BuildId);
          return false;
      }
   
      $id = pdo_insert_id("buildfailure");
   
    // Insert the arguments
    $argumentids = array();
    
      foreach ($this->Arguments as $argument) {
          // Limit the argument to 255
      $argumentescaped = pdo_real_escape_string(substr($argument, 0, 255));

      // Check if the argument exists
      $query = pdo_query("SELECT id FROM buildfailureargument WHERE argument='".$argumentescaped."'");
          if (!$query) {
              add_last_sql_error("BuildFailure Insert", 0, $this->BuildId);
              return false;
          }

          if (pdo_num_rows($query)>0) {
              $argumentarray = pdo_fetch_array($query);
              $argumentids[] = $argumentarray['id'];
          } else {
              // insert the argument

        $query = "INSERT INTO buildfailureargument (argument) VALUES ('".$argumentescaped."')";
              if (!pdo_query($query)) {
                  add_last_sql_error("BuildFailure Insert", 0, $this->BuildId);
                  return false;
              }
        
              $argumentids[] = pdo_insert_id("buildfailureargument");
          }
      }
    
    // Insert the argument
    $query = "INSERT INTO buildfailure2argument (buildfailureid,argumentid,place) VALUES ";
      $i=0;
      foreach ($argumentids as $argumentid) {
          if ($i>0) {
              $query .= ",";
          }
          $query .= "(".qnum($id).",".qnum($argumentid).",".qnum($i).")";
          $i++;
      }

      if (!pdo_query($query)) {
          add_last_sql_error("BuildFailure Insert", 0, $this->BuildId);
          return false;
      }

      $this->InsertLabelAssociations($id);

      return true;
  } // end insert
}
