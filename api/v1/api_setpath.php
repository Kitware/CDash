<?php
// To be able to access files in this CDash installation regardless
// of getcwd() value:
//
$cdashpath = str_replace('\\', '/', dirname(dirname(dirname(__FILE__))));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());
