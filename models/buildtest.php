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
                 VALUES (".qnum($this->BuildId).",".qnum($this->TestId).",'$this->Status',".qnum($this->Time).","
                          .qnum($this->TimeMean).",".qnum($this->TimeStd).",".qnum($this->TimeStatus).")";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("BuildTest Insert");
      return false;
      }  
    return true;
    } 
    
  /** Get the number of tests that are failing */
  function GetNumberOfFailures($checktesttiming,$testtimemaxstatus)
    {
    if(!$this->BuildId)
      {
      echo "BuildTest::GetNumberOfFailures(): BuildId not set";
      return false;    
      }
    // Find if the build has any test failings
    if($checktesttiming)
      {
      $sql = "SELECT count(testid) FROM build2test WHERE buildid=".qnum($this->BuildId)." AND (status='failed' OR status='notrun' OR timestatus>".qnum($testtimemaxstatus).")";
      }
    else
      {
      $sql = "SELECT count(testid) FROM build2test WHERE buildid=".qnum($this->BuildId)." AND status='failed' OR status='notrun'";
      }  
    
    $query = pdo_query($sql);
    if(!$query)
      {
      add_last_sql_error("BuildTest GetNumberOfFailures");
      return false;
      }  
      
    $nfail_array = pdo_fetch_array($query);
    return $nfail_array[0];
    } // end GetNumberOfFailures()   
}
?>
