<?php
$cdash_root = dirname(dirname(__FILE__));
chdir($cdash_root);

function cdash_autoload($className)
{
    global $cdash_root;
    $inc_dir =  "{$cdash_root}/include";
    $model_dir = "{$cdash_root}/models";
    $filename = null;

    if (strpos($className, 'CDash\\') !== false) {
        $filename = substr($className, 5);
        $filename = preg_replace('/\\\/', '/', $filename);
        $filename = "{$inc_dir}/{$filename}.php";
    } else {
        $filename = strtolower($className);
        $filename = "{$model_dir}/{$filename}.php";
    }

    if (file_exists($filename)) {
        require_once $filename;
    }
}

spl_autoload_register('cdash_autoload');

// Require files that do not adhere to any naming convention below...
require_once 'xml_handlers/actionable_build_interface.php';
