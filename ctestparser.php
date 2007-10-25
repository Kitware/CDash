<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: ctestparser.php,v $
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

/** Main function to parse the incoming xml from ctest */
function ctest_parse($xml,$projectid)
{
  $p = xml_parser_create();
  xml_parse_into_struct($p, $xml, $vals, $index);
  xml_parser_free($p);

		if($vals[1]["tag"] == "BUILD")
		  {
    parse_build($vals,$projectid);
		  }
		else	if($vals[1]["tag"] == "CONFIGURE")
		  {
    parse_configure($vals);
		  }
		else	if($vals[1]["tag"] == "TESTING")
		  {
    parse_testing($vals);
		  }
		else	if($vals[0]["tag"] == "UPDATE")
		  {
    parse_update($vals);
		  }		
		else	if($vals[1]["tag"] == "COVERAGE")
		  {
    parse_coverage($vals,$projectid);
		  }	
		else	if($vals[1]["tag"] == "NOTES")
		  {
    parse_note($vals);
		  }
}

/** Return the value given a tag or the parent tag */
function getXMLValue($xmlarray,$tag,$parenttag)
{
  if(strlen($parenttag) == 0)
		  {
				$parentlevel = 0;
		  }
		else
		  {
				$parentlevel = -1;
		  }
				
  foreach($xmlarray as $tagarray)
			{
			if($tagarray["tag"] == $parenttag)
			  {
					$parentlevel = $tagarray["level"];
			  }
			else if($tagarray["tag"] == $tag)
			  {
					if($parentlevel==0 || $tagarray["level"] == $parentlevel+1)
					  {
					  return $tagarray["value"];
			    }
					}
   }
  return "NA";
}

/** Return timestamp from string
 *  \WARNING this function needs improvement */
function str_to_time($str,$stamp)
{
  $str = str_replace("Eastern","",$str);
  $str = str_replace("Daylight","",$str);
	 $str = str_replace("Time","",$str);
		//$str = str_replace("EDT","",$str);	
			
  if(strtotime($str) == -1) // should be FALSE for php 5
		  {
				// find the hours
				$pos = strpos($str,":");
				if($pos !== FALSE)
				  {
						$str = " ".substr($str,$pos-2);
						$str = substr($stamp,0,8).$str;
				  }
		  }
				
		return strtotime($str);
}

/** Parse the build xml */
function parse_build($xmlarray,$projectid)
{			
  $sitename = $xmlarray[0]["attributes"]["NAME"]; 
  $name = $xmlarray[0]["attributes"]["BUILDNAME"];
		// Extract the type from the buildstamp
		$stamp = $xmlarray[0]["attributes"]["BUILDSTAMP"];
  $type = substr($stamp,strrpos($stamp,"-")+1);
		$generator = $xmlarray[0]["attributes"]["GENERATOR"];
		$starttime = getXMLValue($xmlarray,"STARTDATETIME","BUILD");
		
		// Convert the starttime to a timestamp
		$starttimestamp = str_to_time($starttime,$stamp);
		$elapsedminutes = getXMLValue($xmlarray,"ELAPSEDMINUTES","BUILD");
		$endtimestamp = $starttimestamp+$elapsedminutes*60;
		$command = getXMLValue($xmlarray,"BUILDCOMMAND","BUILD");
		$log = getXMLValue($xmlarray,"LOG","BUILD");
		
		include("config.php");
		include_once("common.php");
		$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);

  // First we look at the site and add it if not in the list
  $siteid = add_site($sitename);
						
		$start_time = date("Y-m-d H:i:s",$starttimestamp);
		$end_time = date("Y-m-d H:i:s",$endtimestamp);
		$submit_time = date("Y-m-d H:i:s");
		
		$buildid = add_build($projectid,$siteid,$name,$stamp,$type,$generator,$start_time,$end_time,$submit_time,$command,$log);
  
  // Add the warnings
		$error_array = array();
		$index = 0;
		$inerror = false;
		
		foreach($xmlarray as $tagarray)
			{
			if(!$inerror && (($tagarray["tag"] == "WARNING") || ($tagarray["tag"] == "ERROR") ) && ($tagarray["level"] == 3))
			  {
					$inerror = true;
					$index++;
					if($tagarray["tag"] == "WARNING")
					  {
					  $error_array[$index]["type"]=1; // warning are type 1
							}
					else
					  {
							$error_array[$index]["type"]=0;
					  }
					}
			else if(($tagarray["tag"] == "BUILDLOGLINE") && ($tagarray["level"] == 4))
			  {
					$error_array[$index]["logline"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "TEXT") && ($tagarray["level"] == 4))
			  {
					$error_array[$index]["text"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "SOURCEFILE") && ($tagarray["level"] == 4))
			  {
					$error_array[$index]["sourcefile"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "SOURCELINENUMBER") && ($tagarray["level"] == 4))
			  {
					$error_array[$index]["sourceline"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "PRECONTEXT") && ($tagarray["level"] == 4))
			  {
					$error_array[$index]["precontext"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "POSTCONTEXT") && ($tagarray["level"] == 4))
			  {
					$error_array[$index]["postcontext"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "REPEATCOUNT") && ($tagarray["level"] == 4))
			  {
					$error_array[$index]["repeatcount"]=$tagarray["value"];
			  $inerror = false;
					}
   }
			

		foreach($error_array as $error)
		  {
			 if(array_key_exists("logline",$error))
				  {
				  add_error($buildid,$error["type"],$error["logline"],$error["text"],$error["sourcefile"],$error["sourceline"],
				          $error["precontext"],$error["postcontext"],$error["repeatcount"]);
						}
		  }
}


/** Parse the configure xml */
function parse_configure($xmlarray)
{
		include_once("common.php");
  $name = $xmlarray[0]["attributes"]["BUILDNAME"];
		$stamp = $xmlarray[0]["attributes"]["BUILDSTAMP"];
		
		// Find the build id
		$buildid = get_build_id($name,$stamp);
		if($buildid<0)
		  {
				return;
		  }

		$starttime = getXMLValue($xmlarray,"STARTDATETIME","CONFIGURE");
		$starttimestamp = str_to_time($starttime,$stamp);
		$elapsedminutes = getXMLValue($xmlarray,"ELAPSEDMINUTES","CONFIGURE");
		$endtimestamp = $starttimestamp+$elapsedminutes*60;
		$command = getXMLValue($xmlarray,"BUILDCOMMAND","CONFIGURE");
		$log = getXMLValue($xmlarray,"LOG","CONFIGURE");
		$command = getXMLValue($xmlarray,"CONFIGURECOMMAND","CONFIGURE");
		$status = getXMLValue($xmlarray,"CONFIGURESTATUS","CONFIGURE");
		

		$start_time = date("Y-m-d H:i:s",$starttimestamp);
		$end_time = date("Y-m-d H:i:s",$endtimestamp);
		
		add_configure($buildid,$start_time,$end_time,$command,$log,$status);
}

/** Parse the testing xml */
function parse_testing($xmlarray)
{
		include_once("common.php");
  $name = $xmlarray[0]["attributes"]["BUILDNAME"];
		$stamp = $xmlarray[0]["attributes"]["BUILDSTAMP"];
		
		// Find the build id
		$buildid = get_build_id($name,$stamp);
		if($buildid<0)
		  {
				return;
		  }
				
		//print_r($xmlarray);
		
		$test_array = array();
		$index = 0;
		
		foreach($xmlarray as $tagarray)
			{
			if(($tagarray["tag"] == "TEST") && ($tagarray["level"] == 3) && isset($tagarray["attributes"]["STATUS"]))
			  {
					$index++;
					$test_array[$index]["status"]=$tagarray["attributes"]["STATUS"];
					}
			else if(($tagarray["tag"] == "NAME") && ($tagarray["level"] == 4))
			  {
					$test_array[$index]["name"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "PATH") && ($tagarray["level"] == 4))
			  {
					$test_array[$index]["path"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "FULLNAME") && ($tagarray["level"] == 4))
			  {
					$test_array[$index]["fullname"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "FULLCOMMANDLINE") && ($tagarray["level"] == 4))
			  {
					$test_array[$index]["fullcommandline"]=$tagarray["value"];
			  }	
   }
			
		foreach($test_array as $test)
		  {
				add_test($buildid,$test["name"],$test["status"],$test["path"],$test["fullname"],$test["fullcommandline"]);
		  }
}

/** Parse the coverage xml */
function parse_coverage($xmlarray,$projectid)
{
		include_once("common.php");
  $name = $xmlarray[0]["attributes"]["BUILDNAME"];
		$stamp = $xmlarray[0]["attributes"]["BUILDSTAMP"];
		
		// Find the build id
		$buildid = get_build_id($name,$stamp);
		if($buildid<0)
		  {
		  $sitename = $xmlarray[0]["attributes"]["NAME"]; 

				// Extract the type from the buildstamp
				$stamp = $xmlarray[0]["attributes"]["BUILDSTAMP"];
				$type = substr($stamp,strrpos($stamp,"-")+1);
				$generator = $xmlarray[0]["attributes"]["GENERATOR"];
				$starttime = getXMLValue($xmlarray,"STARTDATETIME","COVERAGE");
				
				// Convert the starttime to a timestamp
				$starttimestamp = str_to_time($starttime,$stamp);
				$elapsedminutes = getXMLValue($xmlarray,"ELAPSEDMINUTES","COVERAGE");
				$endtimestamp = $starttimestamp+$elapsedminutes*60;
				
				include("config.php");
				include_once("common.php");
				$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
				mysql_select_db("$CDASH_DB_NAME",$db);
		
				// First we look at the site and add it if not in the list
				$siteid = add_site($sitename);
								
				$start_time = date("Y-m-d H:i:s",$starttimestamp);
				$end_time = date("Y-m-d H:i:s",$endtimestamp);
				$submit_time = date("Y-m-d H:i:s");
				
				$buildid = add_build($projectid,$siteid,$name,$stamp,$type,$generator,$start_time,$end_time,$submit_time,"","");
				}
				
		//print_r($xmlarray);
		
		$LOCTested = getXMLValue($xmlarray,"LOCTESTED","COVERAGE");
		$LOCUntested = getXMLValue($xmlarray,"LOCUNTESTED","COVERAGE");
		$LOC = getXMLValue($xmlarray,"LOC","COVERAGE");
		$PercentCoverage = getXMLValue($xmlarray,"PERCENTCOVERAGE","COVERAGE");
						
		add_coverage($buildid,$LOCTested,$LOCUntested,$LOC,$PercentCoverage);
		
		/*$coverage_array = array();
		$index = 0;
		
		foreach($xmlarray as $tagarray)
			{
			if(($tagarray["tag"] == "TEST") && ($tagarray["level"] == 3) && isset($tagarray["attributes"]["STATUS"]))
			  {
					$index++;
					$test_array[$index]["status"]=$tagarray["attributes"]["STATUS"];
					}
			else if(($tagarray["tag"] == "NAME") && ($tagarray["level"] == 4))
			  {
					$test_array[$index]["name"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "PATH") && ($tagarray["level"] == 4))
			  {
					$test_array[$index]["path"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "FULLNAME") && ($tagarray["level"] == 4))
			  {
					$test_array[$index]["fullname"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "FULLCOMMANDLINE") && ($tagarray["level"] == 4))
			  {
					$test_array[$index]["fullcommandline"]=$tagarray["value"];
			  }	
   }
			
		foreach($test_array as $test)
		  {
				add_test($buildid,$test["name"],$test["status"],$test["path"],$test["fullname"],$test["fullcommandline"]);
		  }*/
}

/** Parse the update xml */
function parse_update($xmlarray)
{
		include_once("common.php");
  
		$buildname = getXMLValue($xmlarray,"BUILDNAME","UPDATE");
		$stamp = getXMLValue($xmlarray,"BUILDSTAMP","UPDATE");
			
		// Find the build id
		$buildid = get_build_id($buildname,$stamp);
		if($buildid<0)
		  {
				return;
		  }
		
		$starttime = getXMLValue($xmlarray,"STARTDATETIME","UPDATE");
		$starttimestamp = str_to_time($starttime,$stamp);
		$elapsedminutes = getXMLValue($xmlarray,"ELAPSEDMINUTES","UPDATE");
		$endtimestamp = $starttimestamp+$elapsedminutes*60;
		$command = getXMLValue($xmlarray,"UPDATECOMMAND","UPDATE");
		$type = getXMLValue($xmlarray,"UPDATETYPE","UPDATE");
		
		$start_time = date("Y-m-d H:i:s",$starttimestamp);
		$end_time = date("Y-m-d H:i:s",$endtimestamp);

		//add_update($buildid,$start_time,$end_time,$command,$type);
		
		$files_array = array();
		$index = 0;
		$inupdate = 0;
		
		foreach($xmlarray as $tagarray)
			{
			if(!$inupdate && ($tagarray["tag"] == "UPDATED") && ($tagarray["level"] == 3))
			  {
					$index++;
					$inupdate = 1;
					}
			else if(($tagarray["tag"] == "FULLNAME") && ($tagarray["level"] == 4))
			  {
					$files_array[$index]["filename"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "CHECKINDATE") && ($tagarray["level"] == 4))
			  {
					$files_array[$index]["checkindate"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "AUTHOR") && ($tagarray["level"] == 4))
			  {
					$files_array[$index]["author"]=$tagarray["value"];
			  }		
			else if(($tagarray["tag"] == "EMAIL") && ($tagarray["level"] == 4))
			  {
					$files_array[$index]["email"]=$tagarray["value"];
			  }	
			else if(($tagarray["tag"] == "LOG") && ($tagarray["level"] == 4))
			  {
					$files_array[$index]["log"]=$tagarray["value"];
			  }				
		 else if(($tagarray["tag"] == "REVISION") && ($tagarray["level"] == 4))
			  {
					$files_array[$index]["revision"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "PRIORREVISION") && ($tagarray["level"] == 4))
			  {
					$files_array[$index]["priorrevision"]=$tagarray["value"];
			  }		
			else if(($tagarray["tag"] == "REVISIONS") && ($tagarray["level"] == 4))
			  {
					$inupdate = 0;
			  }													
   }
			
		foreach($files_array as $file)
		  {
				add_updatefile($buildid,$file["filename"],$file["checkindate"],$file["author"],
				                        $file["email"],$file["log"],$file["revision"],$file["priorrevision"]);
		  }
}

/** Parse the notes xml */
function parse_note($xmlarray)
{
		include_once("common.php");
  $name = $xmlarray[0]["attributes"]["BUILDNAME"];
		$stamp = $xmlarray[0]["attributes"]["BUILDSTAMP"];
		
		// Find the build id
		$buildid = get_build_id($name,$stamp);
		/*if($buildid<0)
		  {
				return;
		  }*/
		$text = getXMLValue($xmlarray,"TEXT","NOTE");
  
		add_note($buildid,$text);
		
}
?>
