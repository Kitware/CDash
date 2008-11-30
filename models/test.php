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
    $query = pdo_query("SELECT count(*) FROM test2image WHERE imgid='".$this->Id."' AND testid='".$this->TestId."' AND role='".$this->Role."'");  
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']>0)
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

/** Test Measurement */
class TestMeasurement
{
  var $Name;
  var $Type;
  var $Value;
  var $TestId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "NAME": $this->Name = $value;break;
      case "TYPE": $this->Type = $value;break;
      case "VALUE": $this->Value = $value;break;
      }
    }  
  
  /** Return if exists */
  function Exists()
    {
    $query = pdo_query("SELECT count(*) FROM testmeasurement WHERE testid='".$this->TestId."' AND name='".$this->Name."' AND 
                        type='".$this->Type."' AND value='".$this->Value."'");  
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']>0)
      {
      return true;
      }
    return false;
    }      
      
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


/** Test */
class Test
{
  var $Id;
  var $Crc32;
  var $Name;
  var $Path;
  var $Command;
  var $Details;
  var $Output;
  
  var $Images;
  var $Measurements;
  
  function __construct()
    {
    $this->Measurements = array();
    $this->Images = array();
    }
  
  function AddMeasurement($measurement)
    {
    $measurement->TestId = $this->Id;
    $this->Measurements[] = $measurement;
    }
   
  function AddImage($image)
    {
    $image->TestId = $this->Id;
    $this->Images[] = $image;
    }
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "CRC32": $this->Crc32 = $value;break;
      case "NAME": $this->Name = $value;break;
      case "PATH": $this->Path = $value;break;
      case "COMMAND": $this->Command = $value;break;
      case "DETAILS": $this->Details = $value;break;
      case "OUTPUT": $this->Output = $value;break;
      }
    }

  /** Get the CRC32 */
  function GetCrc32()
    {
    if(strlen($this->Crc32)>0)
      {
      return $this->Crc32;
      }
    
    $command = pdo_real_escape_string($this->Command);
    $output = pdo_real_escape_string($this->Output);
    $name = pdo_real_escape_string($this->Name);  
    $path = pdo_real_escape_string($this->Path);     
    $details = pdo_real_escape_string($this->Details);
    
    // CRC32 is computed with the measurements name and type and value
    $buffer = $name.$path.$command.$output.$details; 
    
    foreach($this->Measurements as $measurement)
      {
      $buffer .= $measurement->Type.$measurement->Name.$measurement->Value;
      }
    $this->Crc32 = crc32($buffer);
    return $this->Crc32;
    }

  /** Return if exists */
  function Exists()
    {
    $crc32 = $this->GetCrc32();
    $query = pdo_query("SELECT id FROM test WHERE crc32='".$crc32."'");  
    if(pdo_num_rows($query)>0)
      {
      $query_array = pdo_fetch_array($query);
      $this->Id = $query_array['id'];
      return true;
      }
    return false;
    }      
      
  // Save in the database
  function Insert()
    {
    if($this->Exists())
      {
      return true;
      }

    $command = pdo_real_escape_string($this->Command);
    $output = pdo_real_escape_string($this->Output);
    $name = pdo_real_escape_string($this->Name);  
    $path = pdo_real_escape_string($this->Path);  
    $details = pdo_real_escape_string($this->Details);

    $id = "";
    $idvalue = "";
    if($this->Id)
      {
      $id = "id,";
      $idvalue = "'".$this->Id."',";
      }
        
    $query = "INSERT INTO test (".$id."crc32,name,path,command,details,output)
              VALUES (".$idvalue."'$this->Crc32','$name','$path','$command','$details','$output')";                     
    if(!pdo_query($query))
      {
      add_last_sql_error("Test Insert");
      return false;
      }  
    
    $this->Id = pdo_insert_id("test");
    
    // Add the measurements
    foreach($this->Measurements as $measurement)
      {
      $measurement->TestId = $this->Id;
      $measurement->Insert();
      }
    
    // Add the images
    foreach($this->Images as $image)
      {
      $image->TestId = $this->Id;
      $image->Insert();
      }
    
    return true;
    }  // end Insert  
  
  
    
}
?>
