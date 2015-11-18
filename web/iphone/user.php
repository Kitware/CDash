<?php
require_once(dirname(dirname(__DIR__))."/config/config.php");
$NoXSLGenerate = 1;
include("web/user.php");

if (empty($xml)) {
    $xml = begin_XML_for_XSLT();
    $xml .= add_XML_value("showlogin", "1");
    $xml .= "</cdash>";
}

// Now doing the xslt transition
generate_XSLT($xml, "user");
