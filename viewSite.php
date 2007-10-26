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
include("config.php");
include("common.php");

@$siteid = $_GET["siteid"];

include("config.php");
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
  
$site_array = mysql_fetch_array(mysql_query("SELECT * FROM site WHERE id='$siteid'"));  
$sitename = $site_array["name"];

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$sitename."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<dashboard>";
$xml .= "<title>CDash</title>";

// Find the correct google map key
foreach($CDASH_GOOGLE_MAP_API_KEY as $key=>$value)
  {
  if(strstr($_SERVER['HTTP_HOST'],$key) !== FALSE)
    {
    $apikey = $value;
    break;
    }
  } 
  
$xml .=  add_XML_value("googlemapkey",$apikey);
$xml .= "</dashboard>";
$xml .= "<site>";
$xml .= add_XML_value("name",$site_array["name"]);
$xml .= add_XML_value("description",$site_array["description"]);
$xml .= add_XML_value("processor",$site_array["processor"]);
$xml .= add_XML_value("numprocessors",$site_array["numprocessors"]);
$xml .= add_XML_value("ip",$site_array["ip"]);
$xml .= add_XML_value("latitude",$site_array["latitude"]);
$xml .= add_XML_value("longitude",$site_array["longitude"]);
$xml .= "</site>";
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewSite");
?>
