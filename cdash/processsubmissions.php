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
  exit();
  }
  
$max_files = 2; // 2 files max per submission

for($i=0;$i<$max_files;$i++)
  {
  $query = pdo_query("SELECT filename FROM submission WHERE projectid='".$projectid."' AND status=0 ORDER BY id LIMIT 1");
  if(!$query)
    {
    add_last_sql_error("ProcessSubmissions");
    exit();
    } 
    
  if($query_array = pdo_fetch_array($query))
    {
    $filename = $query_array['filename'];
    pdo_query("UPDATE submission SET status=1 WHERE projectid='".$projectid."' AND status=0 AND filename='".$filename."'");   
    
    $fullfilename = $path.DIRECTORY_SEPARATOR.$filename;    

    $fp = fopen($fullfilename, 'r');
    if(!$fp)
      {
      echo "Cannot open file: ".$fullfilename;
      exit();
      }
    do_submit($fp,$projectid);
    pdo_query("DELETE FROM submission WHERE projectid='".$projectid."' AND status=1 AND filename='".$filename."'");
    }
  else
    {
    // Nothing else to do, we quit
    exit();
    }
  }
  
?>
