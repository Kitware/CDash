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

/** Test Image 
 *  Actually stores just the image id. The image is supposed to be already in the image table */
class TestImage
{
  var $Id;
  var $Role;
  var $TestId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "IMAGE": $this->Role = $value;break;
      }
    }    
  
  /** Return if exists */
  function Exists()
    {
    $query = pdo_query("SELECT count(*) AS c FROM test2image WHERE imgid='".$this->Id."' AND testid='".$this->TestId."' AND role='".$this->Role."'");  
    $query_array = pdo_fetch_array($query);
    if($query_array['c']>0)
      {
      return true;
      }
    return false;
    }      
      
  // Save in the database
  function Insert()
    {
    $role = pdo_real_escape_string($this->Role);

    $query = "INSERT INTO test2image (imgid,testid,role)
              VALUES ('$this->Id','$this->TestId','$role')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("TestImage Insert");
      return false;
      }
    return true;
    }  // end Insert  
}
?>
