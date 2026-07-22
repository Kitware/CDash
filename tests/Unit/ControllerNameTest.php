<?php

namespace Tests\Unit;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use RegexIterator;
use Tests\TestCase;

/**
 * CDash has a policy that all abstract controllers should start with "Abstract".  All non-abstract
 * controllers should be marked as final, and should not contain "Abstract" in their name.
 */
class ControllerNameTest extends TestCase
{
    /**
     * Returns an array of reflection objects corresponding to each controller.
     *
     * @return array<ReflectionClass<object>>
     *
     * @throws Exception
     */
    private static function getControllers(): array
    {
        $dir_iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app/Http/Controllers')));
        $file_iterator = new RegexIterator($dir_iterator, '/.*\.php/', RegexIterator::GET_MATCH);
        $files = [];
        foreach ($file_iterator as $file) {
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

    public function testAllControllersAbstractOrFinal(): void
    {
        foreach (self::getControllers() as $controller) {
            self::assertTrue(
                $controller->isAbstract() || $controller->isFinal(),
                "Controller error ({$controller->getName()}): All controllers must be either abstract or final."
            );
        }
    }

    public function testAbstractControllers(): void
    {
        foreach (self::getControllers() as $controller) {
            if ($controller->isAbstract()) {
                self::assertStringStartsWith(
                    'Abstract',
                    $controller->getShortName(),
                    "Controller error ({$controller->getName()}): Abstract controller names must begin with 'Abstract'."
                );
            } else {
                self::assertStringStartsNotWith(
                    'Abstract',
                    $controller->getShortName(),
                    "Controller error ({$controller->getName()}): Final controller names must not begin with 'Abstract'."
                );
            }
        }
    }
}
