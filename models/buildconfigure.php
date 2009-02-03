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
include_once('models/buildconfigureerror.php');
include_once('models/buildconfigureerrordiff.php');

/** BuildConfigure class */
class BuildConfigure
{
  var $StartTime;
  var $EndTime;
  var $Command;
  var $Log;
  var $Status;
  var $BuildId;
  
  function AddError($error)
    {
    $error->BuildId = $this->BuildId;
    $error->Save();
    }
  
  function AddErrorDifference($diff)
    {
    $diff->BuildId = $this->BuildId;
    $diff->Save();
    }
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "STARTTIME": $this->StartTime = $value;break;
      case "ENDTIME": $this->EndTime = $value;break;
      case "COMMAND": $this->Command = $value;break;
      case "LOG": $this->Log = $value;break;
      case "STATUS": $this->Status = $value;break;
      }
    }
      
  // Save in the database
  function Insert()
    {
    if(!$this->BuildId)
      {
      echo "BuildConfigure::Insert(): BuildId not set";
      return false;    
      }
    
    $command = pdo_real_escape_string($this->Command);
    $log = pdo_real_escape_string($this->Log);
    $status = pdo_real_escape_string($this->Status);
    
    $query = "INSERT INTO configure (buildid,starttime,endtime,command,log,status)
              VALUES (".qnum($this->BuildId).",'$this->StartTime','$this->EndTime','$command','$log','$status')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildConfigure Insert()");
      return false;
      }  
    return true;
    }  // end insert            
    
  /** Compute the errors from the log */
  function ComputeErrors()
    {
    // Add the warnings in the configurewarningtable
    $position = strpos($this->Log,'Warning:',0);
    while($position !== false)
      {
      $warning = "";
      $endline = strpos($this->Log,'\n',$position);
      if($endline !== false)
        {
        $warning = substr($this->Log,$position,$endline-$position);
        }
      else
        {
        $warning = substr($this->Log,$position);
        }
        
      $warning = pdo_real_escape_string($warning);
    
      pdo_query ("INSERT INTO configureerror (buildid,type,text) 
                  VALUES ('$this->BuildId','1','$warning')");
      add_last_sql_error("BuildConfigure ComputeErrors()");
      $position = strpos($this->Log,'Warning:',$position+1);
      }
    } // end ComputeErrors()  
}
?>