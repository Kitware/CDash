<?php

$cdash_root = dirname(dirname(dirname(dirname(__FILE__))));
chdir($cdash_root);

function autoload ($className) {
    global $cdash_root;
    $msg_dir =  "{$cdash_root}/include";
    $model_dir = "{$cdash_root}/models";
    $filename = null;

    if (strpos($className, 'CDash\\') !== false) {
        $filename = substr($className, 5);
        $filename = preg_replace('/\\\/', '/', $filename);
        $filename = "{$msg_dir}/{$filename}.php";

    } else {
        $filename = strtolower($className);
        $filename = "{$model_dir}/{$filename}.php";
    }

    if (file_exists($filename)) {
        require_once $filename;
    }
}

spl_autoload_register('autoload');

require_once 'xml_handlers/actionable_build_interface.php';
