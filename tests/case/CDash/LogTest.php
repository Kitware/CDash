<?php

use CDash\Config;
use CDash\Log;

class LogTest extends PHPUnit_Framework_TestCase
{
    private static $log;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$log = Config::getInstance()->get('CDASH_LOG_FILE');
    }

    public function setUp()
    {
        parent::setUp();
        file_put_contents(self::$log, '');
    }

    public function tearDown()
    {
        parent::tearDown();
        file_put_contents(self::$log, '');
    }

    public function testInstance()
    {
        $log = Log::getInstance();
        $this->assertInstanceOf(Log::class, $log);

        $reflection = new ReflectionClass(\CDash\Singleton::class);
        $property = $reflection->getProperty('_instances');
        $property->setAccessible(true);
        $instances = $property->getValue();

        $this->assertSame($instances[Log::class], $log);
    }

    public function testInfo()
    {
        $log = Log::getInstance();
        $e = new Exception("TESTING Log::info");
        $log->info($e);
        $output = file_get_contents(self::$log);
        $this->assertContains("cdash.INFO: TESTING Log::info", $output);
        $this->assertContains('"function":"testInfo"', $output);
    }

    public function testError()
    {
        $log = Log::getInstance();
        $e = new Exception("TESTING Log::error");
        $log->error($e);
        $output = file_get_contents(self::$log);
        $this->assertContains('cdash.ERROR: TESTING Log::error', $output);
        $this->assertContains('"function":"testError"', $output);
    }

    public function testDebug()
    {
        $log = Log::getInstance();
        $e = new Exception("TESTING Log::debug");
        $log->debug($e);
        $output = file_get_contents(self::$log);
        $this->assertContains('cdash.DEBUG: TESTING Log::debug', $output);
        $this->assertContains('"function":"testDebug"', $output);
    }
}
