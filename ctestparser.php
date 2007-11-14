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
    parse_configure($vals,$projectid);
		  }
		else	if($vals[1]["tag"] == "TESTING")
		  {
    parse_testing($vals,$projectid);
		  }
		else	if($vals[0]["tag"] == "UPDATE")
		  {
    parse_update($vals,$projectid);
		  }		
		else	if($vals[1]["tag"] == "COVERAGE")
		  {
    parse_coverage($vals,$projectid);
		  }	
		else	if($vals[1]["tag"] == "COVERAGELOG")
		  {
    parse_coveragelog($vals,$projectid);
		  }
		else	if($vals[1]["tag"] == "NOTES")
		  {
    parse_note($vals,$projectid);
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

function create_build($xmlarray,$projectid)
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
		
		return $buildid;
}

/** Parse the configure xml */
function parse_configure($xmlarray,$projectid)
{
		include_once("common.php");
  $name = $xmlarray[0]["attributes"]["BUILDNAME"];
		$stamp = $xmlarray[0]["attributes"]["BUILDSTAMP"];
		
		// Find the build id
		$buildid = get_build_id($name,$stamp,$projectid);
		if($buildid<0)
		  {
				$buildid = create_build($xmlarray,$projectid);
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
function parse_testing($xmlarray,$projectid)
{
  include_once("common.php");
  $name = $xmlarray[0]["attributes"]["BUILDNAME"];
  $stamp = $xmlarray[0]["attributes"]["BUILDSTAMP"];

  // Find the build id
  $buildid = get_build_id($name,$stamp,$projectid);
  if($buildid<0)
    {
    return;
    }

  $test_array = array();
  $index = 0;
  $getTimeNext = FALSE;
  $getDetailsNext = FALSE;
  $getSummaryNext = FALSE;
  $getImageNext = FALSE;
  $imageType = "";
  $imageRole = "";
  foreach($xmlarray as $tagarray)
    {
    $key = $tagarray["tag"];
    $val = $tagarray["value"];
    if(($tagarray["tag"] == "TEST") && ($tagarray["level"] == 3) && isset($tagarray["attributes"]["STATUS"]))
      {
      $index++;
      $test_array[$index]["status"]=$tagarray["attributes"]["STATUS"];
      $test_array[$index]["images"] = array();
      }
    else if( ($tagarray["level"] == 5) && ($tagarray["attributes"]["NAME"] == "Execution Time") )
      {
      $getTimeNext = TRUE;
      }
    else if( ($tagarray["level"] == 5) && ($tagarray["attributes"]["NAME"] == "Completion Status") )
      {
      $getDetailsNext = TRUE;
      }
    else if( ($tagarray["level"] == 5) && ($tagarray["tag"] == "MEASUREMENT") )
      {
      $getSummaryNext = TRUE;
      }
    else if( ($tagarray["level"] == 5) && (strpos($tagarray["attributes"]["TYPE"], "image") !== FALSE) )
      {
      $getImageNext = TRUE;
      $imageType = $tagarray["attributes"]["TYPE"];
      $imageRole = $tagarray["attributes"]["NAME"];
      }
    else if( ($tagarray["level"] == 6) && $getTimeNext)
      {
      $test_array[$index]["executiontime"]=$tagarray["value"];
      $getTimeNext = FALSE;
      }
    else if( ($tagarray["level"] == 6) && $getDetailsNext)
      {
      $test_array[$index]["details"]=$tagarray["value"];
      $getDetailsNext = FALSE;
      }
    else if( ($tagarray["level"] == 6) && $getSummaryNext)
      {
      $test_array[$index]["output"]=$tagarray["value"];
      $getSummaryNext = FALSE;
      }
    else if( ($tagarray["level"] == 6) && $getImageNext)
      {
      $imgid = store_test_image($tagarray["value"], $imageType);
      $test_array[$index]["images"][] =
        array("id" => $imgid, "role" => $imageRole);
      $getImageNext = FALSE;
      $imageType = "";
      $imageRole = "";
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
    add_test($buildid,$test["name"],$test["status"],$test["path"],$test["fullname"],$test["fullcommandline"], $test["executiontime"], $test["details"], $test["output"], $test["images"]);
    }
}

/** Parse the coverage xml */
function parse_coverage($xmlarray,$projectid)
{
		include_once("common.php");
  $name = $xmlarray[0]["attributes"]["BUILDNAME"];
		$stamp = $xmlarray[0]["attributes"]["BUILDSTAMP"];
		
		// Find the build id
		$buildid = get_build_id($name,$stamp,$projectid);
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
		add_coveragesummary($buildid,$LOCTested,$LOCUntested);
		
		$coverage_array = array();
		$index = 0;
		
		foreach($xmlarray as $tagarray)
			{
			if(($tagarray["tag"] == "FILE") && ($tagarray["level"] == 3) && isset($tagarray["attributes"]))
			  {
					$index++;
					$coverage_array[$index]["fullpath"]=$tagarray["attributes"]["FULLPATH"];
					$coverage_array[$index]["filename"]=$tagarray["attributes"]["NAME"];
					$coverage_array[$index]["covered"]=1;
					if($tagarray["attributes"]["COVERED"] == "false")
					  {
					  $coverage_array[$index]["covered"]=0;
			    }
			  }
			else if(($tagarray["tag"] == "LOCTESTED") && ($tagarray["level"] == 4))
			  {
					$coverage_array[$index]["loctested"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "LOCUNTESTED") && ($tagarray["level"] == 4))
			  {
					$coverage_array[$index]["locuntested"]=$tagarray["value"];
			  }
			else if(($tagarray["tag"] == "BRANCHSTESTED") && ($tagarray["level"] == 4))
			  {
					$coverage_array[$index]["branchstested"]=$tagarray["value"];
			  }	
			else if(($tagarray["tag"] == "BRANCHSUNTESTED") && ($tagarray["level"] == 4))
			  {
					$coverage_array[$index]["branchsuntested"]=$tagarray["value"];
			  }				
			else if(($tagarray["tag"] == "FUNCTIONSTESTED") && ($tagarray["level"] == 4))
			  {
					$coverage_array[$index]["functionstested"]=$tagarray["value"];
			  }	
			else if(($tagarray["tag"] == "FUNCTIONSUNTESTED") && ($tagarray["level"] == 4))
			  {
					$coverage_array[$index]["functionsuntested"]=$tagarray["value"];
			  }									
   }
			
		foreach($coverage_array as $coverage)
		  {
				@add_coverage($buildid,$coverage["filename"],$coverage["fullpath"],
				              $coverage["covered"],$coverage["loctested"],$coverage["locuntested"],
																		$coverage["branchstested"],$coverage["branchsuntested"],						
																		$coverage["functionstested"],$coverage["functionsuntested"]);
		  }
}

/** Parse the coveragelog xml */
function parse_coveragelog($xmlarray,$projectid)
{
		include_once("common.php");
  $name = $xmlarray[0]["attributes"]["BUILDNAME"];
		$stamp = $xmlarray[0]["attributes"]["BUILDSTAMP"];
		
		// Find the build id
		$buildid = get_build_id($name,$stamp,$projectid);
		if($buildid<0)
		  {
		  return; // we really need a build id
				}
				
		$coveragelog_array = array();
		$index = 0;
		
		foreach($xmlarray as $tagarray)
			{
			if(($tagarray["tag"] == "FILE") && ($tagarray["level"] == 3) && isset($tagarray["attributes"]))
			  {
					$index++;
					$coveragelog_array[$index]["fullpath"] = $tagarray["attributes"]["FULLPATH"];
					$coveragelog_array[$index]["filename"] = $tagarray["attributes"]["NAME"];
					$coveragelog_array[$index]["file"] = "";
			  }
			else if(($tagarray["tag"] == "LINE") && ($tagarray["level"] == 5) && isset($tagarray["value"]))
					{
					if($tagarray["attributes"]["COUNT"]>=0)
					  {
							$coveragelog_array[$index]["lines"][$tagarray["attributes"]["NUMBER"]] = $tagarray["attributes"]["COUNT"];
				  	}			
					$coveragelog_array[$index]["file"] .= $tagarray["value"]."<br>";
					}
   }
		
		foreach($coveragelog_array as $coverage)
		  {
				$filecontent = addslashes($coverage["file"]);
				$fileid = add_coveragefile($buildid,$coverage["fullpath"],$filecontent);
		  }
}

/** Parse the update xml */
function parse_update($xmlarray,$projectid)
{
    include_once("common.php");

    $buildname = getXMLValue($xmlarray,"BUILDNAME","UPDATE");
    $stamp = getXMLValue($xmlarray,"BUILDSTAMP","UPDATE");
	    
    // Find the build id
    $buildid = get_build_id($buildname,$stamp,$projectid);
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
function parse_note($xmlarray,$projectid)
{
  include_once("common.php");
  $name = $xmlarray[0]["attributes"]["BUILDNAME"];
		$stamp = $xmlarray[0]["attributes"]["BUILDSTAMP"];
		
		// Find the build id
		$buildid = get_build_id($name,$stamp,$projectid);
		if($buildid<0)
		  {
				return;
		  }
	
	 foreach($xmlarray as $tagarray)
		 	{
		  if(($tagarray["tag"] == "NOTE") && ($tagarray["level"] == 3) && isset($tagarray["attributes"]["NAME"]))
			   {
						$name=$tagarray["attributes"]["NAME"];
					 }
				}
				
  $date = getXMLValue($xmlarray,"DATETIME","NOTE");
  $timestamp = str_to_time($date,$stamp);
		$time = date("Y-m-d H:i:s",$timestamp);
		
		$text = getXMLValue($xmlarray,"TEXT","NOTE");
  
		add_note($buildid,$text,$time,$name);
}
      
      
function store_test_image($encodedImg, $type)
{
  include("config.php");
  include_once("common.php");
  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);
  $imgStr = base64_decode($encodedImg);
  $img = imagecreatefromstring($imgStr);
 
  ob_start();
  switch($type)
    {
    case "image/jpg":
      imagejpeg($img);
      break;
    case "image/jpeg":
      imagejpeg($img);
      break;
    case "image/gif":
      imagegif($img);
      break;
    case "image/png":
      imagepng($img);
      break;
    default:
      echo "Unknown image type: $type";
      return;
    }
  $imageVariable = addslashes(ob_get_contents());
  ob_end_clean();

  //don't store the image if there's already a copy of it in the database
  $checksum = crc32($imageVariable);
  $query = "SELECT id FROM image WHERE checksum = '$checksum'";
  $result = mysql_query("$query");
  if($row = mysql_fetch_array($result))
    {
    return $row["id"];
    }

  //if we get this far this is a new image
  $query = "INSERT INTO image(img,extension,checksum)
            VALUES('$imageVariable','$type', '$checksum')";
  if(mysql_query("$query"))
    {
    return mysql_insert_id();
    }
  else
    {
    echo mysql_error();
    }
  return 0;
}

?>
