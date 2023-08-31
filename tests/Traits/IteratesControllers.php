<?php

namespace Tests\Traits;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use RegexIterator;

trait IteratesControllers
{
    /**
     * Returns an array of reflection objects corresponding to each controller.
     *
     * @return array<ReflectionClass<object>>
     * @throws Exception
     */
    private static function getControllers(): array
    {
        $dir_iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app/Http/Controllers')));
        $file_iterator = new RegexIterator($dir_iterator, '/.*\.php/', RegexIterator::GET_MATCH);
        $files = [];
        foreach($file_iterator as $file) {
            $files = array_merge($files, $file);
        }

        $controllers = [];
        foreach ($files as $file) {
            $file = str_replace('.php', '', $file);
            $file = str_replace(base_path('app'), 'App', $file);
            $file = str_replace('/', '\\', $file);
            try {
                $controllers[] = new ReflectionClass($file);
            } catch (ReflectionException) {
                throw new Exception('Class name must match file name for all classes.');
            }
        }

        return $controllers;
    }
}
