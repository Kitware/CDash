<?php

$cdash_root = dirname(__FILE__, 2);
$cdash_root = str_replace('\\', '/', $cdash_root);
set_include_path(get_include_path() . PATH_SEPARATOR . $cdash_root);
