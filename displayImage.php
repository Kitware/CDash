<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include "config.php";

$imgid = $_GET["imgid"];
// Checks
if(!isset($imgid) || !is_numeric($imgid))
  {
  echo "Not a valid imgid!";
  return;
  }

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

$result = mysql_query("SELECT * FROM image WHERE id='$imgid '");
$img_array = mysql_fetch_array($result);

switch($img_array["extension"])
  {
  case "image/jpg":
    header("Content-type: image/jpeg");
//    imagejpeg($img_array["img"]);
    break;
  case "image/jpeg":
    header("Content-type: image/jpeg");
//    imagejpeg($img_array["img"]);
    break;
  case "image/gif":
    header("Content-type: image/gif");
//    imagegif($img_array["img"]);
    break;
  case "image/png":
    header("Content-type: image/png");
//    imagepng($img_array["img"]);
    break;
  default:
    echo "Unknown image type: ";
    echo $img_array["extension"];
    exit();
  }
echo $img_array["img"];
exit();
?>
