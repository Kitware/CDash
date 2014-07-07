<?php

session_name("CDash");
session_start();

if( empty($_SESSION['cdash']) ) {
  echo "Expired";
}
else if ($_SESSION['cdash']['valid'] != 1)  {
  echo json_encode($_SESSION['cdash']);
}
else {
  echo "Active";
}

?>
