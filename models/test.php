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
include_once('models/testimage.php');
include_once('models/testmeasurement.php');
include_once('models/buildtestdiff.php');
include_once('models/buildtest.php');
include_once('models/label.php');

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
  var $Labels;
  var $Measurements;
  
  function __construct()
    {
    $this->Images = array();
    $this->Labels = array();
    $this->Measurements = array();
    }

  function AddMeasurement($measurement)
    {
    $measurement->TestId = $this->Id;
    $this->Measurements[] = $measurement;

    if ($measurement->Name == 'Label')
      {
      $label = new Label();
      $label->SetText($measurement->Value);
      $this->AddLabel($label);
      }
    }

  function AddImage($image)
    {
    $image->TestId = $this->Id;
    $this->Images[] = $image;
    }

  function AddLabel($label)
    {
    $label->TestId = $this->Id;
    $this->Labels[] = $label;
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


  function InsertLabelAssociations()
    {
    if($this->Id)
      {
      foreach($this->Labels as $label)
        {
        $label->TestId = $this->Id;
        $label->Insert();
        }
      }
    else
      {
      add_log('No Test::Id - cannot call $label->Insert...',
        'Test::InsertLabelAssociations');
      }
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
      // Even if the test already exists, insert the label associations since
      // this run may include a different set of labels than prior runs:
      //
      $this->InsertLabelAssociations();

      // But then short-circuit the rest of this method because the test is
      // already in the database.
      //
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

    // Add the labels
    $this->InsertLabelAssociations();

    return true;
    }  // end Insert 
}
?>
