<?php
// Put the CDash root directory in the path
$splitchar = '/';
if(DIRECTORY_SEPARATOR == '\\')
  {
  $splitchar='\\\\';
  }
$path = join(array_slice(split( $splitchar ,dirname(__FILE__)),0,-1),DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

include("cdash/config.php");
include("cdash/common.php");
include("cdash/do_submit.php");
require_once("cdash/pdo.php");

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);
set_time_limit(0);

$projectid = $_GET['projectid'];

if(!is_numeric($projectid))
  {
  echo "Wrong project id";
  return;
  }

// Check if someone is already processing the submission for this project
$query = pdo_query("SELECT count(*) AS c FROM submission WHERE projectid='".$projectid."' AND status=1");
if(!$query)
  {
  add_last_sql_error("ProcessSubmissions");
  return;
  } 
$query_array = pdo_fetch_array($query);
if($query['c'] > 0) // if we do we quit
  {
  return;
  }
  
$query = pdo_query("SELECT filename FROM submission WHERE projectid='".$projectid."' AND status=0 ORDER BY id LIMIT 1");
while(pdo_num_rows($query) > 0)
  {
  $query_array = pdo_fetch_array($query);
  $filename = $query_array['filename'];
  pdo_query("UPDATE submission SET status=1 WHERE projectid='".$projectid."' AND status=0 AND filename='".$filename."'");   

  $fp = fopen($filename, 'r');
  if($fp)
    {
    do_submit($fp,$projectid);
    }
  else
    {
    add_log("ProcessSubmission","Cannot open file ".$filename,LOG_ERR);
    }
  pdo_query("DELETE FROM submission WHERE projectid='".$projectid."' AND status=1 AND filename='".$filename."'");
  $query = pdo_query("SELECT filename FROM submission WHERE projectid='".$projectid."' AND status=0 ORDER BY id LIMIT 1");
  }
echo "Done";
?>
