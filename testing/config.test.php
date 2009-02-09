<?php
$configure = array(
  'urlwebsite'       => 'http://localhost/CDashTesting', 
  'outputdirectory'  => '/tmp',
  'type'             => 'Nightly',
  'site'             => 'yellowstone.kitware',
  'buildname'        => 'CDash-SVN-MySQL',
  'cdash'            => 'http://www.cdash.org/CDash',
  'svnroot'          => '/var/www/CDashTesting'
  );
  
include('cdash/config.php');  
?>
