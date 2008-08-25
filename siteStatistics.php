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
include("config.php");
require_once("pdo.php");
include('login.php');
include("version.php");

if($session_OK) 
  {
  include_once('common.php');
  include_once("ctestparser.php");

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

checkUserPolicy(@$_SESSION['cdash']['loginid'],0); // only admin
  
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Sites Statistics</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Site Statistics</menusubtitle>";

$query = pdo_query("SELECT siteid,sitename, SEC_TO_TIME(SUM(elapsed)) AS busytime FROM
(
SELECT site.id AS siteid,site.name AS sitename, project.name AS projectname, build.name AS buildname, build.type, 
AVG(TIME_TO_SEC(TIMEDIFF(submittime, buildupdate.starttime))) AS elapsed
FROM build, buildupdate, project, site
WHERE
  submittime > TIMESTAMPADD(HOUR, -168, NOW())
  AND buildupdate.buildid = build.id
  AND site.id = build.siteid
  AND build.projectid = project.id
  GROUP BY sitename,projectname,buildname,type
  ORDER BY elapsed DESC
)
AS summary
GROUP BY sitename
ORDER BY busytime DESC
");

echo pdo_error();
while($query_array = pdo_fetch_array($query))
{
  $xml .= "<site>";
  $xml .= add_XML_value("id",$query_array["siteid"]);
  $xml .= add_XML_value("name",$query_array["sitename"]);
  $xml .= add_XML_value("busytime",$query_array["busytime"]);
  $xml .= "</site>";
}

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"siteStatistics");

} // end session
?>
