<?php

include_once 'cdash/common.php';
include_once 'cdash/pdo.php';

if(!isset($_GET['id']))
  {
  echo "Id not set.<br>";
  exit();
  }

$id = $_GET['id'];

$result = pdo_query("SELECT file, filesize, filename FROM uploadfile WHERE id='$id'");
if(pdo_num_rows($result) == 0)
  {
  echo "Invalid file id.<br>";
  exit();
  }

$fileInfo = pdo_fetch_array($result);
$fileSize = $fileInfo['filesize'];
$filename = $fileInfo['filename'];

$modified = gmdate('D, d M Y H:i:s').' GMT';
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Last-Modified: $modified");

$contentType = 'application/octet-stream';

$agent = $_SERVER['HTTP_USER_AGENT'];
if (preg_match('%Opera(/| )([0-9].[0-9]{1,2})%', $agent) || preg_match('/MSIE ([0-9].[0-9]{1,2})/', $agent))
  {
  header('Content-Type: '.$contentType);
  header("Content-Disposition: attachment; filename=\"".$filename."\";");
  header("Expires: 0");
  header('Accept-Ranges: bytes');
  header("Cache-Control: private", false);
  header("Pragma: private");
  header("Content-Length: ".$fileSize);
  }
else
  {
  header('Accept-Ranges: bytes');
  header("Expires: 0");
  header("Content-Type: ".$contentType);
  header("Content-Length: ".$fileSize);
  if(!isset($enableContentDisposition) || $enableContentDisposition==true)
    {
    header("Content-Disposition: attachment; filename=\"".$filename."\";");
    }
  }
@ob_end_clean();
@ob_start();

echo $fileInfo['file'];

exit ((connection_status() == 0) && !connection_aborted());
?>
