<?php

session_name("CDash");
session_start();

if( empty($_SESSION['cdash']) || empty($_SESSION['cdash']['state']) ) {
  echo "";
  return;
}

echo $_SESSION['cdash']['state'];

?>
