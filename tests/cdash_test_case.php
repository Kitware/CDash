<?php
//
// After including this file, all subsequent require_once calls are
// relative to the top of the CDash source tree...
//
// All tests in this directory should include this file first as so:
//
//   require_once(dirname(__FILE__).'/cdash_test_case.php');
//

// To be able to access files in this CDash installation regardless
// of getcwd() value:
//
global $cdashpath;
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

require_once 'tests/config.test.php';
require_once 'tests/kwtest/kw_web_tester.php'; // KWWebTestCase
require_once 'tests/kwtest/kw_db.php';
