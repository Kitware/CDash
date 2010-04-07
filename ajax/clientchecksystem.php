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
require_once("../cdash/config.php");
require_once("../cdash/pdo.php");
include("../cdash/common.php");
$noforcelogin = 1;
include('../login.php');
  
if(!isset($_SESSION['cdash']))
  {
  echo "Not valid id";
  return;
  }

$siteids = $_POST["site"];
$cmakeids = $_POST["cmake"];
$compilerids = $_POST["compiler"];
$osids = $_POST["os"];
$libraryids = $_POST["library"];

// Checks
if(!isset($siteids) || !isset($cmakeids) || !isset($compilerids) || !isset($osids)
   || !isset($libraryids))
  {
  echo "Not a valid request!";
  return;
  }
    
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$siteids = explode(",",$siteids);
$cmakeids = explode(",",$cmakeids);
$compilerids = explode(",",$compilerids);
$osids = explode(",",$osids);
$libraryids = explode(",",$libraryids);

$extrasql = "";
$tables = "";
if(!empty($siteids[0])) {$extrasql.=" AND (";}
foreach($siteids as $key=>$siteid)
  {
  if(!empty($siteid))
    {  
    if($key>0) {$extrasql.=" OR ";}  
    $extrasql .= "s.id=".qnum($siteid);
    }
  }
if(!empty($siteids[0])) {$extrasql.=")";}

// CMake
if(!empty($cmakeids[0])) {$extrasql.=" AND (";}
foreach($cmakeids as $key=>$cmakeid)
  {
  if(!empty($cmakeid))
    {  
    if($key>0) {$extrasql.=" OR ";}  
    $extrasql .= "client_site2cmake.cmakeid=".qnum($cmakeid);
    }
  }
if(!empty($cmakeids[0])) {$extrasql.=")";}

// Compiler
if(!empty($compilerids[0])) {$extrasql.=" AND (";}
foreach($compilerids as $key=>$compilerid)
  {
  if(!empty($compilerid))
    {  
    if($key>0) {$extrasql.=" OR ";}  
    $extrasql .= "client_site2compiler.compilerid=".qnum($compilerid);
    }
  }
if(!empty($compilerids[0])) {$extrasql.=")";}

// OS
if(!empty($osids[0])) {$extrasql.=" AND (";}
foreach($osids as $key=>$osid)
  {
  if(!empty($osid))
    {
    if($key>0) {$extrasql.=" OR ";}  
    $extrasql .= "os.id=".qnum($osid);
    }
  }
if(!empty($osids[0])) {$extrasql.=")";}

// Libraries (should have all of them)

if(!empty($libraryids[0])) 
  {
  $tables .= ",client_site2library ";
  $extrasql.=" AND client_site2library.siteid=s.id AND (";
  }
foreach($libraryids as $key=>$libraryid)
  {
  if(!empty($libraryid))
    {
    if($key>0) {$extrasql.=" AND ";}  
    $extrasql .= "client_site2library.libraryid=".qnum($libraryid);
    }
  }
if(!empty($libraryids[0])) {$extrasql.=")";}

// Contruct the query
$sql = "SELECT COUNT(DISTINCT s.id) FROM client_site AS s, client_os AS os, 
                    client_site2cmake,client_site2compiler".$tables."
                    WHERE s.osid=os.id AND client_site2cmake.siteid=s.id 
                    AND client_site2compiler.siteid=s.id ".$extrasql;

$query = pdo_query($sql);
echo pdo_error();
$query_array = pdo_fetch_array($query);
if($query_array[0] == 0)
  {
  echo "<br/><b>No site currently match these settings. Please modify the settings unless you know what you are doing.</b><br/>";  
  }
else
  {
  echo "<br/><b>".$query_array[0]."</b> site";
  if($query_array[0]>1) {echo 's';}
  echo " currently match these settings.<br/>";
  }
exit();
