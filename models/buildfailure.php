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
class BuildFailure
{
  var $BuildId;
  var $Type;
  var $WorkingDirectory;
  var $Arguments;
  var $StdOutput;
  var $StdError;
  var $ExitCondition;
  var $Language;
  var $TargetName;
  var $SourceFile;
  var $OutputFile;
  var $OutputType;
  var $Labels;

  function __construct()
    {
    $this->Arguments = array();
    }
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "TYPE": $this->Type = $value;break;
      case "WORKINGDIRECTORY": $this->WorkingDirectory = $value;break;
      case "ARGUMENT": $this->AddArgument($value);break; // Concatenate the arguments
      case "STDOUT": $this->StdOutput = $value;break;
      case "STDERR": $this->StdError = $value;break;
      case "EXITCONDITION": $this->ExitCondition = $value;break;
      case "LANGUAGE": $this->Language = $value;break;
      case "TARGETNAME": $this->TargetName = $value;break;
      case "SOURCEFILE": $this->SourceFile = $value;break;
      case "OUTPUTFILE": $this->OutputFile = $value;break;
      case "OUTPUTTYPE": $this->OutputType = $value;break;
      }
    }


  function AddLabel($label)
    {
    if(!isset($this->Labels))
      {
      $this->Labels = array();
      }

    $this->Labels[] = $label;
    }


  // Add an argument to the buildfailure
  function AddArgument($argument)
    {
    $this->Arguments[]  = $argument;
    }      


  function InsertLabelAssociations($id)
    {
    if($id)
      {
      foreach($this->Labels as $label)
        {
        $label->BuildFailureId = $id;
        $label->Insert();
        }
      }
    else
      {
      add_log('No BuildFailure id - cannot call $label->Insert...',
        'BuildFailure::InsertLabelAssociations');
      }
    }

  // Insert in the database (no update possible)
  function Insert()
    {
    if(!$this->BuildId)
      {
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
     
    $query = "INSERT INTO buildfailure (buildid,type,workingdirectory,stdoutput,stderror,exitcondition,
              language,targetname,outputfile,outputtype,sourcefile)
              VALUES (".qnum($this->BuildId).",".qnum($this->Type).",'$workingDirectory',
              '$stdOutput','$stdError',".qnum($exitCondition).",
              '$language','$targetName','$outputFile','$outputType','$sourceFile')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildFailure Insert");
      return false;
      }  
   
    $id = pdo_insert_id("buildfailure");
   
    // Insert the arguments
    $query = "INSERT INTO buildfailureargument (buildfailureid,argument) VALUES ";

   $i=0;
    foreach($this->Arguments as $argument)
      {
      if($i>0)
        {
        $query .= ",";
        }
      $argument = pdo_real_escape_string($argument);    
      $query .= "(".qnum($id).",'$argument')";   
      $i++;
      } 

    if(!pdo_query($query))
      {
      add_last_sql_error("BuildFailure Insert");
      return false;
      }

    $this->InsertLabelAssociations($id);

    return true;
    } // end insert
}
?>
