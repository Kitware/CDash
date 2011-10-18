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
require_once("cdash/pdo.php");
include_once('cdash/common.php');
include("cdash/version.php");

include_once('models/project.php');
include_once('models/build.php');
include_once('models/site.php');
include_once('models/uploadfile.php');

if(!isset($_GET['buildid']))
  {
  echo "Build id not set";
  return;
  }

$buildid = $_GET['buildid'];
$Build = new Build();
$Build->Id = $buildid;
$Build->FillFromId($buildid);
$Site = new Site();
$Site->Id = $Build->SiteId;

$build_array = pdo_fetch_array(pdo_query("SELECT projectid FROM build WHERE id='$buildid'"));
$projectid = $build_array["projectid"];

$xml = '<?xml version="1.0"?><cdash>';
$xml .= add_XML_value("cssfile",$CDASH_CSS_FILE);
$xml .= add_XML_value("version",$CDASH_VERSION);
$xml .= get_cdash_dashboard_xml(get_project_name($projectid),$date);

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);
$xml .= add_XML_value("title","CDash - Uploaded files");
$xml .= add_XML_value("menutitle","CDash");
$xml .= add_XML_value("menusubtitle","Uploaded files");

$xml .= "<hostname>".$_SERVER['SERVER_NAME']."</hostname>";
$xml .= "<date>".date("r")."</date>";
$xml .= "<backurl>index.php</backurl>";

$xml .= "<buildid>$buildid</buildid>";
$xml .= '<buildname>'.$Build->Name.'</buildname>';
$xml .= '<buildstarttime>'.$Build->StartTime.'</buildstarttime>';
$xml .= '<siteid>'.$Site->Id.'</siteid>';
$xml .= '<sitename>'.$Site->GetName().'</sitename>';

$uploadFilesOrURLs = $Build->GetUploadedFilesOrUrls();

add_log("uploadFilesOrURLs '".var_export($uploadFilesOrURLs, true)."'", __FILE__.':'.__LINE__.' - '.__FUNCTION__, LOG_INFO);

foreach($uploadFilesOrURLs as $uploadFileOrURL)
  {
  if(!$uploadFileOrURL->IsUrl)
    {
    $xml .= '<uploadfile>';
    $xml .= '<id>'.$uploadFile->Id.'</id>';
    $xml .= '<href>'.$CDASH_DOWNLOAD_RELATIVE_URL.'/'.$uploadFileOrURL->Sha1Sum.'/'.$uploadFileOrURL->Filename.'</href>';
    $xml .= '<sha1sum>'.$uploadFileOrURL->Sha1Sum.'</sha1sum>';
    $xml .= '<filename>'.$uploadFileOrURL->Filename.'</filename>';
    $xml .= '<filesize>'.$uploadFileOrURL->Filesize.'</filesize>';

    $filesize = $uploadFileOrURL->Filesize;
    $ext = "b";
    if($filesize>1024)
      {
      $filesize /= 1024;
      $ext = "Kb";
      }
    if($filesize>1024)
      {
      $filesize /= 1024;
      $ext = "Mb";
      }
    if($filesize>1024)
      {
      $filesize /= 1024;
      $ext = "Gb";
      }
    if($dbsize>1024)
      {
      $filesize /= 1024;
      $ext = "Tb";
      }

    $xml .= '<filesizedisplay>'.$filesize.' '.$ext.'</filesizedisplay>';
    $xml .= '<isurl>'.$uploadFileOrURL->IsUrl.'</isurl>';
    $xml .= '</uploadfile>';
    }
  else
    {
    $xml .= '<uploadurl>';
    $xml .= '<id>'.$uploadFileOrURL->Id.'</id>';
    $xml .= '<filename>'.htmlspecialchars($uploadFileOrURL->Filename).'</filename>';
    $xml .= '</uploadurl>';
    }
  }

$xml .= "</cdash>";

add_log("uploadFilesOrURLs '".$xml."'", __FILE__.':'.__LINE__.' - '.__FUNCTION__, LOG_INFO);

generate_XSLT($xml, "viewFiles", true);
?>
