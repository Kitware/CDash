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
class CoverageFileLog
{  
  var $BuildId;
  var $FileId;
  var $Lines;
  
  
  function __construct()
    {
    $this->Lines = array();
    }
    
  function AddLine($number,$code)
    {
    $this->Lines[$number] = $code;
    }
  
  /** Update the content of the file */
  function Insert()
    {
    if(!$this->BuildId || !is_numeric($this->BuildId))
      {
      add_log("BuildId not set","CoverageFileLog::Insert()",LOG_ERR);
      return false;    
      }
   
    if(!$this->FileId || !is_numeric($this->FileId))
      {
      add_log("FileId not set for buildid=".$this->BuildId,"CoverageFileLog::Insert()",LOG_ERR);
      return false;    
      }
      
    $log = '';
    foreach($this->Lines as $lineNumber=>$code)
      {
      $log .= $lineNumber.':'.$code.';';
      }
    
    if($log != '')
      { 
      $sql = "INSERT INTO coveragefilelog (buildid,fileid,log) VALUES ";
      $sql.= "(".qnum($this->BuildId).",".qnum($this->FileId).",'".$log."')";  
      pdo_query($sql);
      add_last_sql_error("CoverageFileLog::Insert()");
      }
    return true;
    }
}
?>
