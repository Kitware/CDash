<?php

use CDash\Config;
use CDash\Log;
use CDash\Test\CDashTestCase;

use Illuminate\Support\Facades\Log as LogFacade;

class LogTest extends CDashTestCase
{
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
    }

    public function setUp()
    {
        parent::setUp();
        $this->log = LogFacade::getLogger()->getHandlers()[0]->getUrl();
        file_put_contents($this->log, '');
    }

    public function tearDown()
    {
        parent::tearDown();
        file_put_contents($this->log, '');
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
        $output = file_get_contents($this->log);
        $this->assertContains('INFO: TESTING Log::info', $output);
        $this->assertContains('"function":"testInfo"', $output);
    }

    public function testError()
    {
        $log = Log::getInstance();
        $e = new Exception("TESTING Log::error");
        $log->error($e);
        $output = file_get_contents($this->log);
        $this->assertContains('ERROR: TESTING Log::error', $output);
        $this->assertContains('"function":"testError"', $output);
    }

    public function testDebug()
    {
        $log = Log::getInstance();
        $e = new Exception("TESTING Log::debug");
        $log->debug($e);
        $output = file_get_contents($this->log);
        $this->assertContains('DEBUG: TESTING Log::debug', $output);
        $this->assertContains('"function":"testDebug"', $output);
    }
}
