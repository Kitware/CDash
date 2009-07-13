<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');

class TestEnvTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testTestEnv()
    {
    $s = '';

    global $cdashpath;
    $s = $s . "cdashpath=[".print_r($cdashpath, true)."]\n";

    global $configure;
    $s = $s . "configure=[".print_r($configure, true)."]\n";

    global $db;
    $s = $s . "db=[".print_r($db, true)."]\n";

    global $inBrowser;
    $s = $s . "inBrowser=[".print_r($inBrowser, true)."]\n";

    global $web_report;
    $s = $s . "web_report=[".print_r($web_report, true)."]\n";

    global $isWindows;
    $s = $s . "isWindows=[".print_r($isWindows, true)."]\n";

    global $isMacOSX;
    $s = $s . "isMacOSX=[".print_r($isMacOSX, true)."]\n";

    $s = $s . "\n";

    $this->assertTrue(true, $s);
    }

}
?>
