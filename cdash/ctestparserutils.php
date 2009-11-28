<?php
/** Add a new build */
function add_build($build)
{
  require_once('models/buildgroup.php');
  if(!is_numeric($build->ProjectId) || !is_numeric($build->SiteId))
    {
    return;
    }  

  //add_log('subprojectname: '.$build->SubProjectName, 'add_build');
  $buildid = $build->GetIdFromName($build->SubProjectName);
  if($buildid > 0 && !$build->Append)
    {
    remove_build($buildid);
    }

  // Move this into a Build::SetAppend($append, $buildid) method:
  //
  if (!$build->Exists() && $build->Append && empty($build->Id))
    {
    $build->Id = $buildid;
    }

  // Find the groupid
  $buildGroup = new BuildGroup();
  $build->GroupId = $buildGroup->GetGroupIdFromRule($build);

  $build->Save();

  return $build->Id;
}

function init_db()
{
  include 'cdash/config.php';
  require_once 'cdash/pdo.php';
  require_once 'cdash/common.php';
  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME", $db);  
}

/** Extract the type from the build stamp */
function extract_type_from_buildstamp($buildstamp)
{
  // We assume that the time stamp is always of the form
  // 20080912-1810-this-is-a-type
  return substr($buildstamp,strpos($buildstamp,"-",strpos($buildstamp,"-")+1)+1);
}

/** Return timestamp from string
 *  \WARNING this function needs improvement */
function str_to_time($str,$stamp)
{
  $str = str_replace("Eastern Standard Time","EST",$str);
  $str = str_replace("Eastern Daylight Time","EDT",$str);
  
  // For some reasons the Australian time is not recognized by php
  // Actually an open bug in PHP 5.
  $offset = 0; // no offset by default
  if(strpos($str,"AEDT") !== FALSE)
    {
    $str = str_replace("AEDT","UTC",$str);
    $offset = 3600*11;
    }
 // We had more custom dates
 else if(strpos($str,"Paris, Madrid") !== FALSE)
    {
    $str = str_replace("Paris, Madrid","UTC",$str);
    $offset = 3600*1;
    }
 else if(strpos($str,"W. Europe Standard Time") !== FALSE)
    {
    $str = str_replace("W. Europe Standard Time","UTC",$str);
    $offset = 3600*1;
    }
  
  // The year is always at the end of the string if it exists (from CTest)
  $stampyear = substr($stamp,0,4);
  $year = substr($str,strlen($str)-4,2);
  
  if($year!="19" && $year!="20")
    {
    // No year is defined we add it
    // find the hours
    $pos = strpos($str,":");
    if($pos !== FALSE)
      {
      $tempstr = $str;
      $str = substr($tempstr,0,$pos-2);
      $str .= $stampyear." ".substr($tempstr,$pos-2);
      }
    }
  
  $strtotimefailed = 0;
  if(PHP_VERSION>=5.1)
    {  
    if(strtotime($str) === FALSE)
      {
      $strtotimefailed = 1;
      }
    }
  else
    {
    if(strtotime($str) == -1)
      {
      $strtotimefailed = 1;
      }
    }
    
  // If it's still failing we assume GMT and put the year at the end
  if($strtotimefailed)
    {
    // find the hours
    $pos = strpos($str,":");
    if($pos !== FALSE)
      {
      $tempstr = $str;
      $str = substr($tempstr,0,$pos-2);
      $str .= substr($tempstr,$pos-2,5);   
      }
    } 
  
  return strtotime($str)-$offset;
}

/** Add the difference between the numbers of errors and warnings
 *  for the previous and current build */
function compute_error_difference($buildid,$previousbuildid,$warning)
{
  // Look at the number of errors and warnings differences
  $errors = pdo_query("SELECT count(*) FROM builderror WHERE type='$warning' 
                                   AND buildid='$buildid'");
  $errors_array  = pdo_fetch_array($errors);
  $nerrors = $errors_array[0]; 
    
  $previouserrors = pdo_query("SELECT count(*) FROM builderror WHERE type='$warning' 
                                   AND buildid='$previousbuildid'");
  $previouserrors_array  = pdo_fetch_array($previouserrors);
  $npreviouserrors = $previouserrors_array[0];
    
  // Don't log if no diff
  $errordiff = $nerrors-$npreviouserrors;
  if($errordiff != 0)
    {
    pdo_query("INSERT INTO builderrordiff (buildid,type,difference) 
                           VALUES('$buildid','$warning','$errordiff')");
    add_last_sql_error("compute_error_difference");
    }
}

/** Add the difference between the numbers of configure warnings
 *  for the previous and current build */
function compute_configure_difference($buildid,$previousbuildid,$warning)
{
  // Look at the number of errors and warnings differences
  $errors = pdo_query("SELECT count(*) FROM configureerror WHERE type='$warning' 
                                   AND buildid='$buildid'");
  $errors_array  = pdo_fetch_array($errors);
  $nerrors = $errors_array[0]; 
    
  $previouserrors = pdo_query("SELECT count(*) FROM configureerror WHERE type='$warning' 
                                   AND buildid='$previousbuildid'");
  $previouserrors_array  = pdo_fetch_array($previouserrors);
  $npreviouserrors = $previouserrors_array[0];
   
  // Don't log if no diff
  $errordiff = $nerrors-$npreviouserrors;
  if($errordiff != 0)
    {
    pdo_query("INSERT INTO configureerrordiff (buildid,type,difference) 
                           VALUES('$buildid','$warning','$errordiff')");
    add_last_sql_error("compute_configure_difference");
    }
}

/** Add the difference between the numbers of tests
 *  for the previous and current build */
function compute_test_difference($buildid,$previousbuildid,$testtype,$projecttestmaxstatus)
{
  $sql="";
  if($testtype == 0)
    {
    $status="notrun";
    }
  else if($testtype == 1)
    {
    $status="failed";
    }
  else if($testtype == 2)
    {
    $status="passed";
    }
  else if($testtype == 3)
    {
    $status="passed";
    $sql = " AND timestatus>".$projecttestmaxstatus;
    }
      
  // Look at the difference positive and negative test errors
  $sqlquery = "UPDATE build2test SET newstatus=1 WHERE buildid=".$buildid." AND testid=
               (SELECT testid FROM (SELECT test.id AS testid,name FROM build2test,test WHERE build2test.buildid=".$buildid."
               AND build2test.testid=test.id AND build2test.status=".$status.$sql.") AS testa 
               LEFT JOIN (SELECT name as name2 FROM build2test,test WHERE build2test.buildid=".$previousbuildid." 
               AND build2test.testid=test.id AND build2test.status=".$status.$sql.")
               AS testb ON testa.name=testb.name2 WHERE testb.name2 IS NULL)";
  pdo_query($sqlquery);
  
  // Maybe we can get that from the query (don't know).
  $positives = pdo_query("SELECT count(*) FROM build2test WHERE buildid=".$buildid." AND newstatus=1");
  $positives_array  = pdo_fetch_array($positives);
  $npositives = $positives_array[0];
  
  // Count the difference between the number of tests that were passing (or failing)
  // and now that have a different one
  $sqlquery = "SELECT count(*)
               FROM (SELECT name FROM build2test,test WHERE build2test.buildid=".$previousbuildid."
               AND build2test.testid=test.id AND build2test.status=".$status.$sql.") AS testa 
               LEFT JOIN (SELECT name as name2 FROM build2test,test WHERE build2test.buildid=".$buildid." 
               AND build2test.testid=test.id AND build2test.status=".$status.$sql.")
               AS testb ON testa.name=testb.name2 WHERE testb.name2 IS NULL";
  
  $negatives = pdo_query($sqlquery);
  $negatives_array  = pdo_fetch_array($negatives);
  $nnegatives = $negatives_array[0]; 
  
  // Don't log if no diff
  if($npositives != 0 || $negatives != 0)
    {
    pdo_query("INSERT INTO testdiff (buildid,type,difference_positive,difference_negative) 
                 VALUES('$buildid','$testtype','$npositives','$nnegatives')");
    add_last_sql_error("compute_test_difference");
    }
}

/** Add the difference between the numbers of loc tested and untested
 *  for the previous and current build */
function compute_coverage_difference($buildid)
{
  // Find the previous build
  $build = pdo_query("SELECT projectid,starttime,siteid,name,type FROM build WHERE id='$buildid'");
  $build_array = pdo_fetch_array($build);                           
  $buildname = $build_array["name"];
  $buildtype = $build_array["type"];
  $starttime = $build_array["starttime"];
  $siteid = $build_array["siteid"];
  $projectid = $build_array["projectid"];
  
  // Find the previous build
  $previousbuildid = get_previous_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
  if($previousbuildid == 0)
    {
    return;
    }
  
  // Look at the number of errors and warnings differences
  $coverage = pdo_query("SELECT loctested,locuntested FROM coveragesummary WHERE buildid='$buildid'");
  $coverage_array  = pdo_fetch_array($coverage);
  $loctested = $coverage_array['loctested']; 
  $locuntested = $coverage_array['locuntested']; 
    
  $previouscoverage = pdo_query("SELECT loctested,locuntested FROM coveragesummary WHERE buildid='$previousbuildid'");
  if(pdo_num_rows($previouscoverage)>0)
    {
    $previouscoverage_array = pdo_fetch_array($previouscoverage);
    $previousloctested = $previouscoverage_array['loctested']; 
    $previouslocuntested = $previouscoverage_array['locuntested']; 

    // Don't log if no diff
    $loctesteddiff = $loctested-$previousloctested;
    $locuntesteddiff = $locuntested-$previouslocuntested;
    
    if($loctesteddiff != 0 && $locuntesteddiff != 0)
      {
      pdo_query("INSERT INTO coveragesummarydiff (buildid,loctested,locuntested) 
                             VALUES('$buildid','$loctesteddiff','$locuntesteddiff')");
      add_last_sql_error("compute_coverage_difference");
      }
    }
}

      
function store_test_image($encodedImg, $type)
{
  include("cdash/config.php");
  require_once("cdash/pdo.php");
  include_once("cdash/common.php");
  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME",$db);
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
  $result = pdo_query("$query");
  if($row = pdo_fetch_array($result))
    {
    return $row["id"];
    }

  //if we get this far this is a new image
  $query = "INSERT INTO image(img,extension,checksum)
            VALUES('$imageVariable','$type', '$checksum')";
  if(pdo_query("$query"))
    {
    return pdo_insert_id("image");
    }
  else
    {
    echo pdo_error();
    }
  return 0;
}
?>
