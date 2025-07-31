<?php

namespace CDash;

abstract class Singleton
{
    private static $_instances = [];

    public static function getInstance(): static
    {
        if (!isset(self::$_instances[static::class])) {
            self::$_instances[static::class] = new static();
        }
        return self::$_instances[static::class];
    }

    public static function setInstance($class, $instance): void
    {
        self::$_instances[$class] = $instance;
    }

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public function __sleep()
    {
    }

    public function __wakeup()
    {
    }
}
