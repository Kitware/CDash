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
/** Build Test Diff */
class BuildTestDiff
{
  var $Type;
  var $Difference;
  var $BuildId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "TESTDIFF": $this->Difference = $value;break;
      }
    }
    
  // Insert in the database
  function Insert()
    {
    if(!$this->BuildId)
      {
      echo "BuildTestDiff::Insert(): BuildId is not set<br>";
      return false;
      }

    if(empty($this->Type))
      {
      echo "BuildTestDiff::Insert(): Type is not set<br>";
      return false;
      }
      
    $query = "INSERT INTO testdiff (buildid,type,difference_positive) VALUES ('$this->BuildId','$this->Type','$this->Difference')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildTestDiff Insert",0,$this->BuildId);
      return false;
      }  
    return true;
    }      
}

?>
