<?php

$cdash_root = dirname(__FILE__, 2);
$cdash_root = str_replace('\\', '/', $cdash_root);
set_include_path(get_include_path() . PATH_SEPARATOR . $cdash_root);

// Require files that do not adhere to any naming convention below...
include_once dirname(__FILE__) . '/../bootstrap/cdash_autoload.php';
