<html>
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
include("../config.php");
include("../common.php");

$buildid = $_GET["buildid"];
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }
  
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

// Find the notes 
$note = mysql_query("SELECT * FROM buildnote WHERE buildid='$buildid' ORDER BY timestamp ASC");
while($note_array = mysql_fetch_array($note))
  {
  $userid = $note_array["userid"];
  $user_array = mysql_fetch_array(mysql_query("SELECT firstname,lastname FROM user WHERE id='$userid'"));
  $timestamp = strtotime($note_array["timestamp"]." UTC");
  switch($note_array["status"])
    {
    case 0: echo "<b>[note] </b>"; break;
    case 1: echo "<b>[fix in progress] </b>"; break;
    case 2: echo "<b>[fixed] </b>"; break;
    }
  echo "by <b>".$user_array["firstname"]." ".$user_array["lastname"]."</b>"." (".date("H:i:s T",$timestamp).")";
   echo "<pre>".substr($note_array["note"],0,100)."</pre>"; // limit 100 chars
  }
?>

</html>
