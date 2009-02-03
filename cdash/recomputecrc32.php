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
include("cdash/config.php");
require_once("cdash/pdo.php");
include_once("cdash/common.php");

// Change this to 1 to remove the duplicates.
$removeduplicates = 0;

set_time_limit(0);

@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

// For each image we recompute the crc32
$img = pdo_query("SELECT id,img FROM image");
while($img_array = pdo_fetch_array($img))
  {
  $image = $img_array['img'];
  $image_id = $img_array['id'];
  $crc32 = crc32($image);
  pdo_query("UPDATE image SET checksum=".$crc32." WHERE id='$image_id'");
  }

if($removeduplicates)
{  
// Remove the duplicates (update only test2image, project's image can be reuploaded)
$previouscrc32 = 0;
$currentid = 0;
$img = pdo_query("SELECT id,checksum FROM image ORDER BY checksum,id");
while($img_array = pdo_fetch_array($img))
  {
  $id = $img_array['id'];
  $crc32 = $img_array['checksum'];
  if($crc32 != $previouscrc32)
    {
    $previouscrc32 = $crc32;
    $currentid = $id;
    }
  else if($currentid != 0)
    {  
    pdo_query("UPDATE test2image SET imgid=".$currentid." WHERE imgid='$id'");
    pdo_query("DELETE FROM image WHERE id='$id'");
    }
  }
}
  
// For each notes we recompute the crc32
$note = pdo_query("SELECT id,text,name FROM note");
while($note_array = pdo_fetch_array($note))
  {
  $text = $note_array['text'];
  $name = $note_array['name'];
  $id = $note_array['id'];
  $crc32 = crc32($text.$name);  
  pdo_query("UPDATE note SET crc32=".$crc32." WHERE id='$id'");
  }

if($removeduplicates)
{  
// Remove the duplicates
$previouscrc32 = 0;
$currentid = 0;
$note = pdo_query("SELECT id,crc32 FROM note ORDER BY crc32,id");
while($note_array = pdo_fetch_array($note))
  {
  $id = $note_array['id'];
  $crc32 = $note_array['crc32'];
  if($crc32 != $previouscrc32)
    {
    $previouscrc32 = $crc32;
    $currentid = $id;
    }
  else if($currentid != 0)
    {  
    pdo_query("UPDATE build2note SET noteid=".$currentid." WHERE noteid='$id'");
    pdo_query("DELETE FROM note WHERE id='$id'");
    }
  }
}

// For each test we recompute the crc32
$test = pdo_query("SELECT id,name,path,command,output,details FROM test");
while($test_array = pdo_fetch_array($test))
  {
  $name=$test_array['name'];
  $path=$test_array['path'];
  $command=$test_array['command'];
  $output=$test_array['output'];
  $details=$test_array['details'];
  $id=$test_array['id'];
  
  $buffer = $name.$path.$command.$output.$details; 
  
  $measurement = pdo_query("SELECT name,type,value FROM testmeasurement WHERE testid='$id'");
  while($measurement_array = pdo_fetch_array($measurement))
    {
    $buffer .= $measurement_array['type'].$measurement_array['name'].$measurement_array['value'];
    }
  $crc32 = crc32($buffer);
  pdo_query("UPDATE test SET crc32=".$crc32." WHERE id='$id'");
  }

if($removeduplicates)
{  
// Remove the duplicates
$previouscrc32 = 0;
$currentid = 0;
$test = pdo_query("SELECT id,crc32 FROM test ORDER BY crc32,id");
while($test_array = pdo_fetch_array($test))
  {
  $id = $test_array['id'];
  $crc32 = $test_array['crc32'];
  if($crc32 != $previouscrc32)
    {
    $previouscrc32 = $crc32;
    $currentid = $id;
    }
  else if($currentid != 0)
    {  
    pdo_query("UPDATE build2test SET testid=".$currentid." WHERE testid='$id'");
    pdo_query("DELETE FROM test2image WHERE testid='$id'");
    pdo_query("DELETE FROM testmeasurement WHERE testid='$id'");
    pdo_query("DELETE FROM test WHERE id='$id'");
    }
  }
}
  
?>
