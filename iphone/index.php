<?php
// Put the CDash root directory in the path
$splitchar = '/';
if (DIRECTORY_SEPARATOR == '\\') {
    $splitchar='\\\\';
}
$path = join(array_slice(split($splitchar, dirname(__FILE__)), 0, -1), DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR . $path);


$NoXSLGenerate = 1;
include("../index.php");

$xml = generate_index_table();
// Now doing the xslt transition
generate_XSLT($xml, "index");
