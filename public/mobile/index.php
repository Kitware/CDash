<?php
require_once(dirname(dirname(__DIR__))."/config/config.php");
$NoXSLGenerate = 1;
include("old_index.php");

$xml = generate_index_table();
// Now doing the xslt transition
generate_XSLT($xml, "index");
