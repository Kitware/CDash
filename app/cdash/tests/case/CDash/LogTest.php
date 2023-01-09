<?php

use CDash\Config;
use CDash\Log;
use CDash\Test\CDashTestCase;

use Illuminate\Support\Facades\Log as LogFacade;

class LogTest extends CDashTestCase
{
    public static function setUpBeforeClass() : void
    {
        parent::setUpBeforeClass();
    }

    public function setUp() : void
    {
        parent::setUp();
        $this->log = LogFacade::getLogger()->getHandlers()[0]->getUrl();
        file_put_contents($this->log, '');
    }

    public function tearDown() : void
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
        $this->assertStringContainsString('INFO: TESTING Log::info', $output);
        $this->assertStringContainsString('"function":"testInfo"', $output);
    }

    public function testError()
    {
        $log = Log::getInstance();
        $e = new Exception("TESTING Log::error");
        $log->error($e);
        $output = file_get_contents($this->log);
        $this->assertStringContainsString('ERROR: TESTING Log::error', $output);
        $this->assertStringContainsString('"function":"testError"', $output);
    }

    public function testDebug()
    {
        $log = Log::getInstance();
        $e = new Exception("TESTING Log::debug");
        $log->debug($e);
        $output = file_get_contents($this->log);
        $this->assertStringContainsString('DEBUG: TESTING Log::debug', $output);
        $this->assertStringContainsString('"function":"testDebug"', $output);
    }
}
