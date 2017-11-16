<?php
$cdash_root = dirname(dirname(__FILE__));
$cdash_root = str_replace('\\', '/', $cdash_root);
set_include_path(get_include_path() . PATH_SEPARATOR . $cdash_root);

// Config now configures autoloader
require_once 'config/config.php';

// Require files that do not adhere to any naming convention below...
require_once 'xml_handlers/actionable_build_interface.php';
