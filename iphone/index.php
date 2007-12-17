<?php
$NoXSLGenerate = 1;
include("../index.php");

$xml = generate_index_table();
// Now doing the xslt transition
generate_XSLT($xml,"index");

?>