<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: viewUpdate.php,v $
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

@$buildid = $_GET["buildid"];
@$date = $_GET["date"];

include("config.php");
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
  
$build_array = mysql_fetch_array(mysql_query("SELECT * FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];
$date = date("Ymd", strtotime($build_array["starttime"]));
    
$project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)>0)
  {
  $project_array = mysql_fetch_array($project);
  $svnurl = $project_array["cvsurl"];
  $projectname = $project_array["name"];  
  }

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);

  // Build
  $xml .= "<build>";
  $build = mysql_query("SELECT * FROM build WHERE id='$buildid'");
  $build_array = mysql_fetch_array($build); 
  $siteid = $build_array["siteid"];
  $site_array = mysql_fetch_array(mysql_query("SELECT name FROM site WHERE id='$siteid'"));
  $xml .= add_XML_value("site",$site_array["name"]);
  $xml .= add_XML_value("buildname",$build_array["name"]);
  $xml .= add_XML_value("buildid",$build_array["id"]);
  $xml .= add_XML_value("buildtime",date("D, d M Y H:i:s T",strtotime($build_array["starttime"]." UTC")));  
   
  $xml .= "</build>";

  $xml .= "<updates>";

  
	// Return the status
	
  $status_array = mysql_fetch_array(mysql_query("SELECT status FROM buildupdate WHERE buildid='$buildid'"));
  $xml .= add_XML_value("status",$status_array["status"]);

  $xml .= "<javascript>";
  // Regretfully this is not correct and need to be fixed
  $updatedfiles = mysql_query("SELECT * FROM updatefile WHERE buildid='$buildid'
	                             ORDER BY REVERSE(RIGHT(REVERSE(filename),LOCATE('/',REVERSE(filename)))) ");
  
  function sort_array_by_directory($a,$b)
    { 
    return $a>$b ? 1:0;
    }
    
  function sort_array_by_filename($a,$b)
    {
    // Extract directory
    $filenamea = $a['filename'];
    $filenameb = $b['filename'];  
    return $filenamea>$filenameb ? 1:0;
    }
    
  $directoryarray = array();
  $updatearray1 = array();
  // Create an array so we can sort it
  while($file_array = mysql_fetch_array($updatedfiles))
    {
    $file = array();
    $file['filename'] = $file_array["filename"];
    $file['author'] = $file_array["author"];
    $file['email'] = $file_array["email"];
    $file['log'] = $file_array["log"];
    $file['revision'] = $file_array["revision"];
    $updatearray1[] = $file;
    $directoryarray[] = substr($file_array["filename"],0,strrpos($file_array["filename"],"/"));
    }
  
  $directoryarray = array_unique($directoryarray);
  usort($directoryarray, "sort_array_by_directory");
  usort($updatearray1, "sort_array_by_filename");
  
  $updatearray = array();
  
  foreach($directoryarray as $directory)
    {
    foreach($updatearray1 as $update)
      {
      $filename = $update['filename'];
      if(substr($filename,0,strrpos($filename,"/")) == $directory)
        {
        $updatearray[] = $update;
        }
      }
    }
  
  
  $xml .= "dbAdd (true, \"Updated files  (".mysql_num_rows($updatedfiles).")\", \"\", 0, \"\", \"1\", \"\", \"\", \"\")\n";
  $previousdir = "";

  $projecturl = $svnurl;
    // locally cached query result same as get_project_property($projectname, "cvsurl");

  foreach($updatearray as $file)
    {
    $filename = $file['filename'];
    $directory = substr($filename,0,strrpos($filename,"/"));
    $filename = substr($filename,strrpos($filename,"/")+1);
    
    if($directory != $previousdir)
      {
      $xml .= " dbAdd (true, \"&lt;b&gt;".$directory."&lt;/b&gt;\", \"\", 1, \"\", \"1\", \"\", \"\", \"\")\n";
      $previousdir = $directory;
      }
      
    $author = $file['author'];
    $email = $file['email'];
    $log = $file['log'];
    $revision = $file['revision'];
    $log = str_replace("\r"," ",$log);
    $log = str_replace("\n", " ", $log);
    // Do this twice so that <something> ends up as
    // &amp;lt;something&amp;gt; because it gets sent to a 
    // java script function not just displayed as html
    $log = XMLStrFormat($log);
    $log = XMLStrFormat($log);

    $diff_url = get_diff_url($projecturl, $directory, $filename, $revision);
    $diff_url = XMLStrFormat($diff_url);

    $xml .= " dbAdd ( false, \"".$filename." Revision: ".$revision."\",\"".$diff_url."\",2,\"\",\"1\",\"".$author."\",\"".$email."\",\"".$log."\")\n";
    }
  $xml .= "dbAdd (true, \"Modified files  (0)\", \"\", 0, \"\", \"1\", \"\", \"\", \"\")\n";
  $xml .= "dbAdd (true, \"Conflicting files  (0)\", \"\", 0, \"\", \"1\", \"\", \"\", \"\")\n";

  $xml .= "</javascript>";
  $xml .= "</updates>";
  $xml .= "</cdash>";
 

// Now doing the xslt transition
generate_XSLT($xml,"viewUpdate");
?>
