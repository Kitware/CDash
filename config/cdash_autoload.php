<?php

function cdash_autoload($className)
{
    $cdash_root = realpath(dirname(__FILE__) . '/..');
    $inc_dir = "{$cdash_root}/include";
    $model_dir = "{$cdash_root}/models";
    $xml_dir = "{$cdash_root}/xml_handlers";
    $filename = null;

    if (strpos($className, 'CDash\\') !== false) {
        $filename = substr($className, 5);
        $filename = preg_replace('/\\\/', '/', $filename);
        $filename = "{$inc_dir}/{$filename}.php";
    } elseif (preg_match('/Handler$/', $className)) {
        $name_parts = preg_split('/(?=[A-Z])/', $className);
        $filename = "{$xml_dir}/" . strtolower(implode('_', array_slice($name_parts, 1))) . '.php';
    } else {
        if (strpos($className, '\\') !== false) {
            $filename = preg_replace('/\\\/', '/', $className);
        } else {
            $filename = strtolower($className);
        }
        $filename = "{$model_dir}/{$filename}.php";
    }

    if (file_exists($filename)) {
        require_once $filename;
    }
}

spl_autoload_register('cdash_autoload');
