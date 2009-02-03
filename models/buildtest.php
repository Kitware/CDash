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

/** Build Test class */          
class BuildTest
{
  var $TestId;
  var $Status;
  var $Time;
  var $TimeMean;
  var $TimeStd;
  var $TimeStatus;
  var $BuildId;
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "TESTID": $this->TestId = $value;break;
      case "STATUS": $this->Status = $value;break;
      case "TIME": $this->Time = $value;break;
      case "TIMEMEAN": $this->TimeMean = $value;break;
      case "TIMESTD": $this->TimeStd = $value;break;
      case "TIMESTATUS": $this->TimeStatus = $value;break;
      }
    }    

  // Insert in the database
  function Insert()
    {
    if(!$this->BuildId)
      {
      echo "BuildTest::Insert(): BuildId is not set";
      return false;
      }

    if(!$this->TestId)
      {
      echo "BuildTest::Insert(): TestId is not set";
      return false;
      }
      
    $query = "INSERT INTO build2test (buildid,testid,status,time,timemean,timestd,timestatus)
                 VALUES ('$this->BuildId','$this->TestId','$this->Status','$this->Time','$this->TimeMean','$this->TimeStd','$this->TimeStatus')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildTest Insert");
      return false;
      }  
    return true;
    }    
}
?>
