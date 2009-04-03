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
  var $Labels;


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


  function AddLabel($label)
    {
    if(!isset($this->Labels))
      {
      $this->Labels = array();
      }

    $label->BuildId = $this->BuildId;
    $this->Labels[] = $label;
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


  /** Check if the configure exists */ 
  function Exists()
    {
    if(!$this->BuildId)
      {
      echo "BuildConfigure::Exists(): BuildId not set";
      return false;    
      }
    
    if(!is_numeric($this->BuildId))
      {
      echo "BuildConfigure::Exists(): Buildid is not numeric";
      return false;
      }
    
    $query = pdo_query("SELECT COUNT(*) FROM configure WHERE buildid=".qnum($this->BuildId));                     
    if(!$query)
      {
      add_last_sql_error("BuildConfigure Exists()");
      return false;
      }
    
    $query_array = pdo_fetch_array($query);
    if($query_array[0] > 0)
      {
      return true;
      }
    return false;
    } 


  /** Delete a current configure given a buildid */ 
  function Delete()
    {
    if(!$this->BuildId)
      {
      echo "BuildConfigure::Delete(): BuildId not set";
      return false;    
      }
    
    $query = pdo_query("DELETE FROM configure WHERE buildid=".qnum($this->BuildId));                     
    if(!$query)
      {
      add_last_sql_error("BuildConfigure Delete()");
      return false;
      }
    return true;
    }


  function InsertLabelAssociations()
    {
    if($this->BuildId)
      {
      if(!isset($this->Labels))
        {
        return;
        }
      
      foreach($this->Labels as $label)
        {
        $label->BuildId = $this->BuildId;
        $label->Insert();
        }
      }
    else
      {
      add_log('No BuildConfigure::BuildId - cannot call $label->Insert...',
              'BuildConfigure::InsertLabelAssociations',LOG_ERR);
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
     
     if($this->Exists())
      {
      echo "BuildConfigure::Exists(): Cannot insert new configure. Use Delete() first";
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

    $this->InsertLabelAssociations();

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
  
  /** Get the number of configure error for a build */
  function GetNumberOfErrors()
    {
    if(!$this->BuildId)
      {
      echo "BuildConfigure::GetNumberOfErrors(): BuildId not set";
      return false;    
      }
   
    $nerrors = 0;
    $configure = pdo_query("SELECT status FROM configure WHERE buildid=".qnum($this->BuildId));
    if(!$configure)
      {
      add_last_sql_error("BuildConfigure GetNumberOfErrors");
      return false;
      }  
    $configure_array = pdo_fetch_array($configure);
    if($configure_array["status"]!=0)
      {
      $nerrors = 1;
      }
    
    return $nerrors;  
    } // end GetNumberOfErrors() 
    
    
    
}
?>
