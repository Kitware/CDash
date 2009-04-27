<?php
// Put the CDash root directory in the path
$path = join(array_slice(split( "/" ,dirname(__FILE__)),0,-1),"/");
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

$NoXSLGenerate = 1;
include("../index.php");

$xml = generate_index_table();
// Now doing the xslt transition
generate_XSLT($xml,"index");

?>