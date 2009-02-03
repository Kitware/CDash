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
/** BuildError */
class BuildError
{
  var $Type;
  var $LogLine;
  var $Text;
  var $SourceFile;
  var $SourceLine;
  var $PreContext;
  var $PostContext;
  var $RepeatCount;
  var $BuildId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "TYPE": $this->Type = $value;break;
      case "LOGLINE": $this->LogLine = $value;break;
      case "TEXT": $this->Text = $value;break;
      case "SOURCEFILE": $this->SourceFile = $value;break;
      case "SOURCELINE": $this->SourceLine = $value;break;
      case "PRECONTEXT": $this->PreContext = $value;break;
      case "POSTCONTEXT": $this->PostContext = $value;break;
      case "REPEATCOUNT": $this->RepeatCount = $value;break;
      }
    }
      
  // Insert in the database (no update possible)
  function Insert()
    {
    if(!$this->BuildId)
      {
      echo "BuildError::Insert(): BuildId not set<br>";
      return false;    
      }
    
    $text = addslashes($this->Text);
    $precontext = addslashes($this->PreContext);
    $postcontext = addslashes($this->PostContext);
      
    $query = "INSERT INTO builderror (buildid,type,logline,text,sourcefile,sourceline,precontext,postcontext,repeatcount)
              VALUES (".qnum($this->BuildId).",'$this->Type','$this->LogLine','$text','$this->SourceFile','$this->SourceLine',
              '$precontext','$postcontext','$this->RepeatCount')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildError Insert");
      return false;
      }  
    return true;
    } // end insert
}
?>
