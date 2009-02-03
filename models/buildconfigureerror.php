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

/** BuildConfigureError class */
class BuildConfigureError
{
  var $Type;
  var $Text;
  var $BuildId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "TYPE": $this->Type = $value;break;
      case "TEXT": $this->Text = $value;break;
      }
    }
  
  /** Return if exists */
  function Exists()
    {
    $query = pdo_query("SELECT count(*) FROM configureerror WHERE buildid='".$this->BuildId."'
                         AND type='".$this->Type."' AND text='".$this->Text."'");  
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']>0)
      {
      return true;
      }
    return false;
    }      
      
  /** Save in the database */
  function Save()
    {
    if(!$this->BuildId)
      {
      echo "BuildConfigureError::Save(): BuildId not set";
      return false;    
      }
      
    if(!$this->Exists())
      {
      $text = pdo_real_escape_string($this->Text);
      $query = "INSERT INTO configureerror (buildid,type,text)
                VALUES (".qnum($this->BuildId).",".qnum($this->Type).",'$text')";                     
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildConfigureError Save");
        return false;
        }  
      }
    return true;
    }        
}

/** BuildConfigureErrorDiff class */
class BuildConfigureErrorDiff
{
  var $Type;
  var $Difference;
  var $BuildId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "BUILDERRORDIFF": $this->Difference = $value;break;
      case "TYPE": $this->Type = $value;break;
      }
    }
    /** Return if exists */
  function Exists()
    {
    $query = pdo_query("SELECT count(*) FROM configureerrordiff WHERE buildid=".qnum($this->BuildId));  
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']>0)
      {
      return true;
      }
    return false;
    }      
      
  /** Save in the database */
  function Save()
    {
    if(!$this->BuildId)
      {
      echo "BuildConfigureErrorDiff::Save(): BuildId not set";
      return false;    
      }
      
    if($this->Exists())
      {
      // Update
      $query = "UPDATE configureerrordiff SET";
      $query .= " type=".qnum($this->Type);
      $query .= ",difference=".qnum($this->Difference);
      $query .= " WHERE buildid=".qnum($this->BuildId);
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildConfigureErrorDiff Update");
        return false;
        }
      }
    else // insert  
      {
      $query = "INSERT INTO configureerrordiff (buildid,type,difference)
                 VALUES (".qnum($this->BuildId).",".qnum($this->Type).",".qnum($this->Difference).")";                     
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildConfigureErrorDiff Create");
        return false;
        }  
      }
    return true;
    }        
}

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
