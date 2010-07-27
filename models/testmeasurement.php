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
// It is assumed that appropriate headers should be included before including this file

/** Test Measurement */
class TestMeasurement
{
  var $Name;
  var $Type;
  var $Value;
  var $TestId;
      
  // Save in the database
  function Insert()
    {
    $name = pdo_real_escape_string($this->Name);
    $type = pdo_real_escape_string($this->Type);
    $value = pdo_real_escape_string($this->Value);

    $query = "INSERT INTO testmeasurement (testid,name,type,value)
              VALUES ('$this->TestId','$name','$type','$value')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("TestMeasurement Insert");
      return false;
      }  
    return true;
    }  // end Insert  
    
}

?>
