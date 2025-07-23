<?php

function cdash_autoload($className)
{
    $cdash_root = realpath(dirname(__FILE__) . '/..');
    $inc_dir = "{$cdash_root}/include";
    $app_dir = "{$cdash_root}/app";
    $model_dir = "{$cdash_root}/models";
    $filenames = null;

    if (str_contains($className, 'CDash\\')) {
        $loc1 = substr($className, 5);
        $loc1 = $loc2 = preg_replace('/\\\/', '/', $loc1);
        $loc1 = "{$inc_dir}/{$loc1}.php";
        $loc2 = "{$app_dir}/{$loc2}.php";

        $filenames = [realpath($loc1), realpath($loc2)];
    } else {
        if (str_contains($className, '\\')) {
            $filenames = preg_replace('/\\\/', '/', $className);
        } else {
            $filenames = strtolower($className);
        }
        $filenames = "{$model_dir}/{$filenames}.php";
    }

    // TODO: based on CakePHP (il)logic, remove a soon as possible locations become standardized
    foreach ((array) $filenames as $file) {
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

spl_autoload_register('cdash_autoload');
