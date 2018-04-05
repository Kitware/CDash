<?php

function cdash_autoload($className)
{
    $cdash_root = realpath(dirname(__FILE__) . '/..');
    $inc_dir = "{$cdash_root}/include";
    $app_dir = "{$cdash_root}/app";
    $model_dir = "{$cdash_root}/models";
    $xml_dir = "{$cdash_root}/xml_handlers";
    $filenames = null;

    if (strpos($className, 'CDash\\') !== false) {
        $loc1 = substr($className, 5);
        $loc1 = $loc2 = preg_replace('/\\\/', '/', $loc1);
        $loc1 = "{$inc_dir}/{$loc1}.php";
        $loc2 = "{$app_dir}/{$loc2}.php";

        $filenames = [realpath($loc1), realpath($loc2)];
    } elseif (preg_match('/Handler$/', $className)) {
        $name_parts = preg_split('/(?=[A-Z])/', $className);
        $filenames = "{$xml_dir}/" . strtolower(implode('_', array_slice($name_parts, 1))) . '.php';
    } else {
        if (strpos($className, '\\') !== false) {
            $filenames = preg_replace('/\\\/', '/', $className);
        } else {
            $filenames = strtolower($className);
        }
        $filenames = "{$model_dir}/{$filenames}.php";
    }

    // TODO: based on CakePHP (il)logic, remove a soon as possible locations become standardized
    foreach ((array)$filenames as $file) {
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

spl_autoload_register('cdash_autoload');
