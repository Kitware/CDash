<?php
namespace CDash;

abstract class Singleton
{
    private static $_instances = [];

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (!isset(self::$_instances[static::class])) {
            self::$_instances[static::class] = new static;
        }
        return self::$_instances[static::class];
    }

    /**
     * @param $class
     * @param $instance
     * @return void
     */
    public static function setInstance($class, $instance)
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
